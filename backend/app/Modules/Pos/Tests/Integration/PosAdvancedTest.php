<?php

namespace App\Modules\Pos\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-16 "caisse approfondie" — split payments, cash-drawer movements, and till refunds.
 * Complements PosSessionTest (which still covers the single-payment happy path unchanged).
 */
class PosAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $cashier;
    private Product $product;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'manager', 'viewer', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create([
            'name' => 'Boutique POS+', 'slug' => 'boutique-pos-plus', 'plan' => 'starter',
            'status' => 'active', 'settings' => ['currency' => 'XOF'],
        ]);

        $this->cashier = User::create([
            'name' => 'Caissier', 'email' => 'caisse@boutique-pos-plus.sn',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->cashier->assignTenantRole('cashier');
        $this->token = $this->cashier->createToken('api')->plainTextToken;

        // A default warehouse so the RMA restock path (refund) can resolve stock.
        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Boutique', 'code' => 'WH-1', 'is_default' => true]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'POS-9001',
            'name' => 'Thé Kinkeliba', 'price_amount' => 25000, // 250,00 XOF
            'price_currency' => 'XOF', 'status' => 'active',
        ]);

        $stock = app(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null);
        app(StockService::class)->moveIn($stock, 100);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function openSession(int $float = 0): array
    {
        return $this->withHeaders($this->auth())
            ->postJson('/api/pos/sessions', ['opening_float_cents' => $float])
            ->json('data');
    }

    private function stockQty(): int
    {
        return app(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null)->fresh()->quantity;
    }

    // ── Split payments ───────────────────────────────────────────────────────

    #[Test]
    public function a_split_payment_records_each_leg_and_only_cash_counts_to_the_drawer(): void
    {
        $session = $this->openSession();

        $res = $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'    => [['product_id' => $this->product->id, 'quantity' => 2]], // 2 × 25 000 = 50 000
            'payments' => [
                ['method' => 'cash',         'amount_cents' => 30000],
                ['method' => 'mobile_money', 'amount_cents' => 20000, 'reference' => 'WAVE-42'],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.order.total_amount', 50000)
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.session.cash_sales_cents', 30000)     // only the cash leg
            ->assertJsonPath('data.session.total_sales_cents', 50000)
            ->assertJsonPath('data.session.expected_cash_cents', 30000); // float 0 + 30 000 cash

        $this->assertSame(98, $this->stockQty());
        $this->assertDatabaseCount('payments', 2);
    }

    #[Test]
    public function a_split_payment_that_does_not_sum_to_the_total_is_rejected_and_rolled_back(): void
    {
        $session = $this->openSession();

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'    => [['product_id' => $this->product->id, 'quantity' => 2]], // total 50 000
            'payments' => [
                ['method' => 'cash',         'amount_cents' => 20000],
                ['method' => 'mobile_money', 'amount_cents' => 20000], // sums to 40 000 ≠ 50 000
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('payments');

        // Fully rolled back: stock intact, no order, no payment, session untouched.
        $this->assertSame(100, $this->stockQty());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('cash_register_sessions', ['id' => $session['id'], 'sales_count' => 0]);
    }

    // ── Cash-drawer movements ────────────────────────────────────────────────

    #[Test]
    public function a_payout_reduces_and_a_payin_increases_the_expected_cash(): void
    {
        $session = $this->openSession(100000); // fond de caisse 1 000,00

        // Withdrawal of 200,00 → expected 800,00
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/cash-movement", [
            'direction' => 'out', 'amount_cents' => 20000, 'reason' => 'withdrawal',
        ])->assertStatus(201)->assertJsonPath('data.session.expected_cash_cents', 80000);

        // Float top-up of 50,00 → expected 850,00
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/cash-movement", [
            'direction' => 'in', 'amount_cents' => 5000, 'reason' => 'float_add',
        ])->assertStatus(201)
            ->assertJsonPath('data.session.expected_cash_cents', 85000)
            ->assertJsonPath('data.session.net_cash_movements_cents', -15000);

        // Closing at the expected amount → no difference.
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/close", [
            'counted_cash_cents' => 85000,
        ])->assertOk()
            ->assertJsonPath('data.expected_cash_cents', 85000)
            ->assertJsonPath('data.difference_cents', 0);
    }

    #[Test]
    public function a_payout_larger_than_the_cash_on_hand_is_rejected(): void
    {
        $session = $this->openSession(10000); // only 100,00 in the drawer

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/cash-movement", [
            'direction' => 'out', 'amount_cents' => 20000, 'reason' => 'withdrawal',
        ])->assertStatus(422)->assertJsonValidationErrors('amount_cents');

        $this->assertDatabaseCount('cash_movements', 0);
    }

    // ── Refund at the till ───────────────────────────────────────────────────

    #[Test]
    public function a_cash_refund_restocks_the_items_and_pays_out_of_the_drawer(): void
    {
        $session = $this->openSession();

        // Sell 2 for cash → stock 98, cash in drawer 50 000.
        $sale = $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 2]],
            'method' => 'cash',
        ])->assertStatus(201)->json('data');

        $orderId = $sale['order']['id'];
        $lineId  = $sale['order']['lines'][0]['id'];
        $this->assertSame(98, $this->stockQty());

        // Refund both units in cash.
        $refund = $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/refund", [
            'order_id' => $orderId,
            'lines'    => [['order_line_id' => $lineId, 'quantity' => 2, 'condition' => 'resalable']],
            'reason'   => 'Client insatisfait',
        ]);

        $refund->assertStatus(201)
            ->assertJsonPath('data.return.refund_amount_cents', 50000)
            ->assertJsonPath('data.movement.direction', 'out')
            ->assertJsonPath('data.movement.amount_cents', 50000)
            ->assertJsonPath('data.session.expected_cash_cents', 0); // 50 000 cash − 50 000 refund

        // Resalable units are back in stock; a cash pay-out is recorded.
        $this->assertSame(100, $this->stockQty());
        $this->assertDatabaseHas('cash_movements', [
            'session_id' => $session['id'], 'direction' => 'out', 'amount_cents' => 50000, 'reason' => 'refund',
        ]);
    }

    #[Test]
    public function a_non_cash_refund_leaves_the_drawer_untouched(): void
    {
        $session = $this->openSession();

        $sale = $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 1]],
            'method' => 'cash',
        ])->assertStatus(201)->json('data');

        // Refund via Mobile Money reversal → no cash leaves the drawer.
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/refund", [
            'order_id'      => $sale['order']['id'],
            'lines'         => [['order_line_id' => $sale['order']['lines'][0]['id'], 'quantity' => 1]],
            'reason'        => 'Retour',
            'refund_method' => 'mobile_money',
        ])->assertStatus(201)
            ->assertJsonPath('data.movement', null)
            ->assertJsonPath('data.session.expected_cash_cents', 25000); // unchanged cash sale

        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertSame(100, $this->stockQty()); // still restocked
    }

    // ── RC-22 — idempotence du checkout (rejeu offline / retry réseau) ────────

    #[Test]
    public function replaying_a_checkout_with_the_same_idempotency_key_returns_the_same_sale(): void
    {
        $session = $this->openSession();
        $key     = 'offline-sale-0001';
        $payload = [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 2]],
            'method' => 'cash',
        ];

        $first = $this->withHeaders($this->auth() + ['X-Idempotency-Key' => $key])
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)
            ->assertStatus(201)->json('data');

        // Rejeu (la réponse du 1er appel s'est « perdue ») : MÊME commande, rien de recréé.
        $second = $this->withHeaders($this->auth() + ['X-Idempotency-Key' => $key])
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)
            ->assertStatus(201)->json('data');

        $this->assertSame($first['order']['id'], $second['order']['id']);
        $this->assertSame(98, $this->stockQty());                        // décrémenté UNE fois
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('cash_register_sessions', [
            'id' => $session['id'], 'sales_count' => 1, 'cash_sales_cents' => 50000,
        ]);
    }

    #[Test]
    public function the_replay_still_returns_the_sale_after_the_session_was_closed(): void
    {
        // Scénario offline réel : vente encaissée, réponse perdue, caisse clôturée le soir,
        // resynchronisation le lendemain → le rejeu doit renvoyer la vente actée, pas un 422.
        $session = $this->openSession();
        $key     = 'offline-sale-0002';
        $payload = ['items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'method' => 'cash'];

        $first = $this->withHeaders($this->auth() + ['X-Idempotency-Key' => $key])
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)
            ->assertStatus(201)->json('data');

        $this->withHeaders($this->auth())
            ->postJson("/api/pos/sessions/{$session['id']}/close", ['counted_cash_cents' => 25000])->assertOk();

        $replay = $this->withHeaders($this->auth() + ['X-Idempotency-Key' => $key])
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)
            ->assertStatus(201)->json('data');

        $this->assertSame($first['order']['id'], $replay['order']['id']);
        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function different_keys_create_distinct_sales_and_no_key_keeps_the_legacy_behaviour(): void
    {
        $session = $this->openSession();
        $payload = ['items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'method' => 'cash'];

        $this->withHeaders($this->auth() + ['X-Idempotency-Key' => 'k-1'])
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)->assertStatus(201);
        $this->flushHeaders()->withHeaders($this->auth() + ['X-Idempotency-Key' => 'k-2'])
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)->assertStatus(201);
        // withHeaders PERSISTE entre appels du même test → purge avant l'appel « sans clé ».
        $this->flushHeaders()->withHeaders($this->auth())
            ->postJson("/api/pos/sessions/{$session['id']}/checkout", $payload)->assertStatus(201);

        $this->assertDatabaseCount('orders', 3);
        $this->assertSame(97, $this->stockQty());
    }

    // ── Backward compatibility ───────────────────────────────────────────────

    #[Test]
    public function a_movement_on_a_closed_session_is_rejected(): void
    {
        $session = $this->openSession();
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/close", ['counted_cash_cents' => 0])->assertOk();

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/cash-movement", [
            'direction' => 'in', 'amount_cents' => 1000, 'reason' => 'float_add',
        ])->assertStatus(422);
    }
}
