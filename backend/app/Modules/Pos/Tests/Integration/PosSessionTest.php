<?php

namespace App\Modules\Pos\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Models\CashRegisterSession;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosSessionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $cashier;
    private User $viewer;
    private Product $product;
    private string $token;
    private string $viewerToken;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'manager', 'viewer', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create([
            'name' => 'Boutique POS', 'slug' => 'boutique-pos', 'plan' => 'starter',
            'status' => 'active', 'settings' => ['currency' => 'XOF'],
        ]);

        $this->cashier = User::create([
            'name' => 'Caissier', 'email' => 'caisse@boutique-pos.sn',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->cashier->assignTenantRole('cashier');
        $this->token = $this->cashier->createToken('api')->plainTextToken;

        $this->viewer = User::create([
            'name' => 'Spectateur', 'email' => 'viewer@boutique-pos.sn',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->viewer->assignTenantRole('viewer');
        $this->viewerToken = $this->viewer->createToken('api')->plainTextToken;

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'POS-0001',
            'name' => 'Savon de Marseille', 'price_amount' => 25000, // 250,00 XOF (centimes)
            'price_currency' => 'XOF', 'status' => 'active',
        ]);

        $stock = app(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null);
        app(StockService::class)->moveIn($stock, 100);
    }

    private function auth(string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    private function stockQty(): int
    {
        return app(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null)->fresh()->quantity;
    }

    // ── Session lifecycle ──────────────────────────────────────────────────────

    #[Test]
    public function a_cashier_can_open_a_session_with_an_opening_float(): void
    {
        $res = $this->withHeaders($this->auth())
            ->postJson('/api/pos/sessions', ['opening_float_cents' => 1000000, 'label' => 'Caisse 1']);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_float_cents', 1000000);

        $this->assertDatabaseHas('cash_register_sessions', [
            'tenant_id' => $this->tenant->id, 'opened_by' => $this->cashier->id, 'status' => 'open',
        ]);
    }

    #[Test]
    public function opening_a_second_session_while_one_is_open_is_rejected(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 0])->assertStatus(201);

        $this->withHeaders($this->auth())
            ->postJson('/api/pos/sessions', ['opening_float_cents' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');
    }

    #[Test]
    public function current_returns_the_open_session_then_null_after_close(): void
    {
        $open = $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 0])->json('data');

        $this->withHeaders($this->auth())->getJson('/api/pos/sessions/current')
            ->assertOk()->assertJsonPath('data.id', $open['id']);

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$open['id']}/close", ['counted_cash_cents' => 0])->assertOk();

        $this->withHeaders($this->auth())->getJson('/api/pos/sessions/current')
            ->assertOk()->assertJsonPath('data', null);
    }

    // ── Checkout ───────────────────────────────────────────────────────────────

    #[Test]
    public function checkout_creates_a_paid_fulfilled_order_and_decrements_stock(): void
    {
        $session = $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 0])->json('data');

        $res = $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 2]],
            'method' => 'cash',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.order.status', 'fulfilled')
            ->assertJsonPath('data.order.total_amount', 50000)      // 2 × 25 000
            ->assertJsonPath('data.payment.amount_cents', 50000)
            ->assertJsonPath('data.payment.method', 'cash');

        $this->assertSame(98, $this->stockQty());                    // 100 − 2

        // Session tallies updated, sale linked back to the session.
        $this->assertDatabaseHas('cash_register_sessions', [
            'id' => $session['id'], 'sales_count' => 1, 'cash_sales_cents' => 50000, 'total_sales_cents' => 50000,
        ]);
        $this->assertDatabaseHas('orders', [
            'cash_register_session_id' => $session['id'], 'status' => 'fulfilled',
        ]);
    }

    #[Test]
    public function checkout_is_rejected_and_rolled_back_when_stock_is_insufficient(): void
    {
        $session = $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 0])->json('data');

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 500]], // only 100 in stock
            'method' => 'cash',
        ])->assertStatus(422);

        // Nothing persisted: stock intact, no order, session untouched.
        $this->assertSame(100, $this->stockQty());
        $this->assertDatabaseMissing('orders', ['cash_register_session_id' => $session['id']]);
        $this->assertDatabaseHas('cash_register_sessions', ['id' => $session['id'], 'sales_count' => 0]);
    }

    #[Test]
    public function a_non_cash_sale_does_not_inflate_the_expected_cash(): void
    {
        $session = $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 10000])->json('data');

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 1]],
            'method' => 'mobile_money',
        ])->assertStatus(201);

        // total_sales rises, but cash_sales stays at the opening float level.
        $this->assertDatabaseHas('cash_register_sessions', [
            'id' => $session['id'], 'total_sales_cents' => 25000, 'cash_sales_cents' => 0,
        ]);
    }

    // ── Reconciliation ─────────────────────────────────────────────────────────

    #[Test]
    public function closing_computes_expected_cash_and_the_signed_difference(): void
    {
        $session = $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 10000])->json('data');

        // One cash sale of 25 000 → expected = 10 000 + 25 000 = 35 000.
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/checkout", [
            'items'  => [['product_id' => $this->product->id, 'quantity' => 1]],
            'method' => 'cash',
        ])->assertStatus(201);

        // Cashier counts 34 000 → short by 1 000.
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/close", [
            'counted_cash_cents' => 34000,
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_cents', 35000)
            ->assertJsonPath('data.counted_cash_cents', 34000)
            ->assertJsonPath('data.difference_cents', -1000);
    }

    #[Test]
    public function a_closed_session_cannot_be_closed_again(): void
    {
        $session = $this->withHeaders($this->auth())->postJson('/api/pos/sessions', ['opening_float_cents' => 0])->json('data');
        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/close", ['counted_cash_cents' => 0])->assertOk();

        $this->withHeaders($this->auth())->postJson("/api/pos/sessions/{$session['id']}/close", ['counted_cash_cents' => 0])
            ->assertStatus(422);
    }

    // ── Authorization & isolation ──────────────────────────────────────────────

    #[Test]
    public function a_viewer_cannot_operate_the_till(): void
    {
        $this->withHeaders($this->auth($this->viewerToken))
            ->postJson('/api/pos/sessions', ['opening_float_cents' => 0])
            ->assertStatus(403);
    }

    #[Test]
    public function sessions_are_isolated_across_tenants(): void
    {
        // Tenant A's session created directly (no request) so the only authenticated
        // requests below are tenant B's — switching auth users mid-test would make the
        // guard resolve the wrong user.
        $sessionA = CashRegisterSession::create([
            'tenant_id' => $this->tenant->id, 'status' => CashRegisterSession::STATUS_OPEN,
            'opening_float_cents' => 0, 'opened_by' => $this->cashier->id, 'opened_at' => now(),
        ]);

        $otherTenant = Tenant::create(['name' => 'Autre', 'slug' => 'autre-pos', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF']]);
        $otherCashier = User::create(['name' => 'C2', 'email' => 'c2@autre-pos.sn', 'password' => Hash::make('x'), 'tenant_id' => $otherTenant->id]);
        $otherCashier->assignTenantRole('cashier');
        $otherToken = $otherCashier->createToken('api')->plainTextToken;

        // Tenant B sees no open session of its own…
        $this->withHeaders($this->auth($otherToken))->getJson('/api/pos/sessions/current')
            ->assertOk()->assertJsonPath('data', null);

        // …and cannot close tenant A's session (scoped out → 404, not 403, avoids leaking existence).
        $this->withHeaders($this->auth($otherToken))->postJson("/api/pos/sessions/{$sessionA->id}/close", ['counted_cash_cents' => 0])
            ->assertStatus(404);
    }
}
