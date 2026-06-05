<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Payments\Models\Payment;
use App\Modules\Pos\Models\CashRegisterSession;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the demo dataset: a fresh `db:seed` must produce data exercising EVERY
 * MVP module so the product can be demoed end-to-end.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function full_seed_covers_every_mvp_module(): void
    {
        $this->seed(DatabaseSeeder::class);

        // ── Tenants / users / subscriptions ──────────────────────────────────
        $this->assertDatabaseCount('tenants', 3);
        $this->assertGreaterThanOrEqual(14, User::count());
        $this->assertDatabaseHas('subscriptions', ['status' => 'trialing']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'active']);

        // ── Catalogue (produits simples + déclinaisons + attributs) ──────────
        $this->assertGreaterThan(40, Product::count());
        $this->assertGreaterThanOrEqual(9, ProductVariant::count());          // 3 / tenant
        // Money convention: variant price stored in centimes (15 000 → 1 500 000).
        $this->assertDatabaseHas('product_variants', ['sku' => 'DEMO-VAR-001-Rouge-S', 'price_amount' => 1500000]);

        // ── Stock : entrepôts + mouvements (traçabilité) ─────────────────────
        $this->assertGreaterThanOrEqual(3, Warehouse::count());
        $this->assertGreaterThan(0, StockMovement::count());

        // ── Caisse POS (session ouverte + clôturée rapprochée) ───────────────
        $this->assertGreaterThanOrEqual(6, CashRegisterSession::count());     // 2 / tenant
        $this->assertDatabaseHas('cash_register_sessions', ['status' => 'open']);
        $this->assertDatabaseHas('cash_register_sessions', ['status' => 'closed']);

        // ── Commandes / paiements / livraisons / retours ─────────────────────
        $this->assertGreaterThan(0, Order::where('status', Order::STATUS_FULFILLED)->count());
        $this->assertGreaterThan(0, Payment::count());
        $this->assertGreaterThan(0, Delivery::count());
        $this->assertGreaterThanOrEqual(3, OrderReturn::count());

        // ── Marketplace / Billing (promo + paiement manuel) ──────────────────
        $this->assertGreaterThanOrEqual(3, MarketplaceListing::count());
        $this->assertDatabaseHas('promotions', ['code' => 'BIENVENUE20']);
        $this->assertDatabaseHas('manual_payments', ['status' => 'pending']);
    }

    #[Test]
    public function re_seeding_does_not_duplicate_reference_or_demo_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tenants  = Tenant::count();
        $variants = ProductVariant::count();
        $warehouses = Warehouse::count();
        $returns  = OrderReturn::count();

        $this->seed(DatabaseSeeder::class); // second run — must be idempotent

        $this->assertSame($tenants, Tenant::count(), 'Tenants must not duplicate.');
        $this->assertSame($variants, ProductVariant::count(), 'Variants must not duplicate.');
        $this->assertSame($warehouses, Warehouse::count(), 'Warehouses must not duplicate.');
        $this->assertSame($returns, OrderReturn::count(), 'Returns must not duplicate.');
        // 3 distinct returns (one per tenant) — proves the per-tenant number is unique.
        $this->assertSame(3, OrderReturn::count());
    }
}
