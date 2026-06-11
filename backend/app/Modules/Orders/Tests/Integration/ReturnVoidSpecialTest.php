<?php

namespace App\Modules\Orders\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryUnitService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Services\OrderReturnService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Models\WarrantyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5H — au retour (RMA restock) : annulation des garanties (void), révocation des accès digitaux,
 * et remise en stock / marquage `returned` des unités sérialisées.
 */
class ReturnVoidSpecialTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '66666666-6666-4666-8666-666666666666';

    private Tenant $tenant;
    private User $user;
    private Warehouse $wh;
    private OrderService $orders;
    private OrderReturnService $returns;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Ret', 'slug' => 'ret-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@ret.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-RET', 'is_default' => true]);

        $this->orders  = $this->app->make(OrderService::class);
        $this->returns = $this->app->make(OrderReturnService::class);
    }

    private function sell(Product $p, int $qty = 1): Order
    {
        $order = $this->orders->create(['customer_id' => self::CUSTOMER_ID, 'items' => [['product_id' => $p->id, 'quantity' => $qty]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    private function fullReturn(Order $order, string $condition = 'resalable'): OrderReturn
    {
        $line = $order->fresh('lines')->lines->first();
        $return = $this->returns->create($order, [
            ['order_line_id' => $line->id, 'quantity' => $line->quantity, 'condition' => $condition, 'reason' => 'defective'],
        ], 'defective', $this->user->id);
        $this->returns->approve($return, $this->user->id);
        $this->returns->restock($return, $this->user->id);

        return $return->fresh('lines');
    }

    private function serializedPhone(): Product
    {
        $policy = WarrantyPolicy::create(['tenant_id' => $this->tenant->id, 'name' => 'G12', 'duration_months' => 12, 'is_active' => true]);
        $phone = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'IPHONE', 'name' => 'iPhone', 'price_amount' => 800000, 'cost_amount' => 500000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
            'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED, 'warranty_policy_id' => $policy->id,
        ]);
        $this->app->make(InventoryUnitService::class)->registerMany($this->tenant->id, $phone->id, [
            ['serial_type' => 'imei', 'serial_value' => '359000000000001', 'warehouse_id' => $this->wh->id, 'unit_cost_cents' => 500000],
        ], $this->user->id);

        return $phone;
    }

    #[Test]
    public function a_resalable_serialized_return_restocks_the_unit_and_voids_the_warranty(): void
    {
        $phone = $this->serializedPhone();
        $order = $this->sell($phone);

        $unit = InventoryUnit::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertSame(InventoryUnit::STATUS_SOLD, $unit->status);

        $this->fullReturn($order, 'resalable');

        $unit->refresh();
        $this->assertSame(InventoryUnit::STATUS_IN_STOCK, $unit->status);
        $this->assertNull($unit->order_id);
        $this->assertNull($unit->order_line_id);
        $this->assertNull($unit->warranty_ends_at);

        // Garantie annulée.
        $this->assertSame(WarrantyContract::STATUS_VOID, WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->first()->status);
        // Stock agrégé miroir réabondé (1 unité de retour).
        $this->assertDatabaseHas('stocks', ['tenant_id' => $this->tenant->id, 'product_id' => $phone->id, 'quantity' => 1]);
    }

    #[Test]
    public function a_non_resalable_serialized_return_marks_the_unit_returned_without_restocking(): void
    {
        $phone = $this->serializedPhone();
        $order = $this->sell($phone);

        $this->fullReturn($order, 'damaged');

        $unit = InventoryUnit::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertSame(InventoryUnit::STATUS_RETURNED, $unit->status); // conservée pour traçabilité
        $this->assertSame(WarrantyContract::STATUS_VOID, WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->first()->status);
        // Non resalable → pas de réabondement (le stock vendu reste sorti).
        $this->assertDatabaseHas('stocks', ['tenant_id' => $this->tenant->id, 'product_id' => $phone->id, 'quantity' => 0]);
    }

    #[Test]
    public function returning_a_digital_product_revokes_the_entitlement_without_crashing(): void
    {
        $ebook = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'EBOOK', 'name' => 'Ebook', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
        ]);
        $order = $this->sell($ebook);
        $this->assertSame(DigitalEntitlement::STATUS_ACTIVE, DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first()->status);

        $this->fullReturn($order, 'resalable'); // produit non stockable → aucun moveIn

        $this->assertSame(DigitalEntitlement::STATUS_REVOKED, DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first()->status);
    }

    #[Test]
    public function returning_an_aggregate_product_under_warranty_voids_its_contract_by_line(): void
    {
        $policy = WarrantyPolicy::create(['tenant_id' => $this->tenant->id, 'name' => 'G24', 'duration_months' => 24, 'is_active' => true]);
        $blender = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'BLEND', 'name' => 'Blender', 'price_amount' => 30000, 'cost_amount' => 12000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE, 'warranty_policy_id' => $policy->id,
        ]);
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $blender->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 5, \App\Modules\Inventory\Models\StockMovement::REASON_DELIVERY, null, null, $this->user->id, 12000);

        $order = $this->sell($blender, 1);
        $contract = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertNull($contract->inventory_unit_id); // garantie agrégée, par ligne
        $this->assertSame(WarrantyContract::STATUS_ACTIVE, $contract->status);

        $this->fullReturn($order, 'resalable');

        $this->assertSame(WarrantyContract::STATUS_VOID, $contract->fresh()->status);
    }
}
