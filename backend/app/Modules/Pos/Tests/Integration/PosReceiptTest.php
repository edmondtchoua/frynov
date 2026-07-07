<?php

namespace App\Modules\Pos\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-19 — ticket de caisse : payload structuré (en-tête boutique, lignes, paiements splittés,
 * totaux), RBAC caisse, isolation multitenant.
 */
class PosReceiptTest extends TestCase
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
            'name' => 'Boutique Ticket', 'slug' => 'boutique-ticket', 'plan' => 'starter',
            'status' => 'active',
            'settings' => ['currency' => 'XOF', 'address' => 'Marché Sandaga, Dakar', 'phone' => '+221 77 000 00 00'],
        ]);

        $this->cashier = User::create([
            'name' => 'Awa Caissière', 'email' => 'awa@boutique-ticket.sn',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->cashier->assignTenantRole('cashier');
        $this->token = $this->cashier->createToken('api')->plainTextToken;

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'TIC-1',
            'name' => 'Café Touba', 'price_amount' => 25000,
            'price_currency' => 'XOF', 'status' => 'active',
        ]);

        $stock = app(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null);
        app(StockService::class)->moveIn($stock, 50);
    }

    private function auth(string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    /**
     * Vend 2 unités en split cash + mobile money VIA LE SERVICE (aucune requête HTTP) : le guard
     * Sanctum cache le premier utilisateur résolu par requête de test — les tests qui authentifient
     * ensuite un AUTRE utilisateur doivent donc préparer la vente sans HTTP (cf. PosSessionTest).
     */
    private function sellSplit(): string
    {
        $svc     = app(\App\Modules\Pos\Services\PosService::class);
        $session = $svc->openSession(['label' => 'Caisse 1'], $this->tenant->id, $this->cashier->id);

        $res = $svc->checkout($session, [
            'items'    => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payments' => [
                ['method' => 'cash',         'amount_cents' => 30000],
                ['method' => 'mobile_money', 'amount_cents' => 20000, 'reference' => 'OM-777'],
            ],
        ], $this->tenant->id, $this->cashier->id);

        return $res['order']->id;
    }

    #[Test]
    public function the_receipt_carries_business_header_lines_split_payments_and_totals(): void
    {
        $orderId = $this->sellSplit();

        $res = $this->withHeaders($this->auth())->getJson("/api/pos/orders/{$orderId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Boutique Ticket')
            ->assertJsonPath('data.business.address', 'Marché Sandaga, Dakar')
            ->assertJsonPath('data.business.currency', 'XOF')
            ->assertJsonPath('data.cashier', 'Awa Caissière')
            ->assertJsonPath('data.session.label', 'Caisse 1')
            ->assertJsonPath('data.totals.total_cents', 50000)
            ->assertJsonPath('data.totals.paid_cents', 50000)
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonCount(2, 'data.payments');

        $line = $res->json('data.lines.0');
        $this->assertSame(['Café Touba', 'TIC-1', 2, 25000, 50000],
            [$line['name'], $line['sku'], $line['quantity'], $line['unit_price_cents'], $line['total_cents']]);

        // Les deux legs du split figurent sur le ticket, avec la référence Mobile Money.
        $methods = array_column($res->json('data.payments'), 'method');
        $this->assertEqualsCanonicalizing(['cash', 'mobile_money'], $methods);
        $this->assertContains('OM-777', array_column($res->json('data.payments'), 'reference'));
    }

    #[Test]
    public function a_viewer_cannot_read_receipts(): void
    {
        $orderId = $this->sellSplit();

        $viewer = User::create([
            'name' => 'V', 'email' => 'v@boutique-ticket.sn',
            'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id,
        ]);
        $viewer->assignTenantRole('viewer');

        $this->withHeaders($this->auth($viewer->createToken('api')->plainTextToken))
            ->getJson("/api/pos/orders/{$orderId}/receipt")
            ->assertStatus(403);
    }

    #[Test]
    public function receipts_are_isolated_across_tenants(): void
    {
        $orderId = $this->sellSplit();

        $other        = Tenant::create(['name' => 'Autre', 'slug' => 'autre-ticket', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF']]);
        $otherCashier = User::create(['name' => 'C2', 'email' => 'c2@autre-ticket.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $otherCashier->assignTenantRole('cashier');

        // Scopé hors du tenant → 404 (pas de fuite d'existence).
        $this->withHeaders($this->auth($otherCashier->createToken('api')->plainTextToken))
            ->getJson("/api/pos/orders/{$orderId}/receipt")
            ->assertStatus(404);
    }
}
