<?php

namespace App\Modules\Warranties\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryUnitService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Models\WarrantyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5F — réclamations SAV : ouverture gardée par la période (override audité), transitions, isolation.
 */
class WarrantyClaimTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '44444444-4444-4444-8444-444444444444';

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Warehouse $wh;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Sav', 'slug' => 'sav-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@sav.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-SAV', 'is_default' => true]);
        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** Vend un téléphone sérialisé sous garantie et renvoie [order, contrat]. */
    private function sellWithWarranty(int $months = 12): array
    {
        $policy = WarrantyPolicy::create(['tenant_id' => $this->tenant->id, 'name' => "Garantie {$months}m", 'duration_months' => $months, 'is_active' => true]);
        $phone  = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'IPHONE', 'name' => 'iPhone 15', 'price_amount' => 800000,
            'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_SIMPLE, 'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED,
            'warranty_policy_id' => $policy->id,
        ]);
        $this->app->make(InventoryUnitService::class)->registerMany($this->tenant->id, $phone->id, [
            ['serial_type' => 'imei', 'serial_value' => '359000000000001', 'warehouse_id' => $this->wh->id],
        ], $this->user->id);

        $order = $this->orders->create(['customer_id' => self::CUSTOMER_ID, 'items' => [['product_id' => $phone->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $order = $this->orders->fulfill($order, $this->user->id);

        $contract = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();

        return [$order, $contract];
    }

    #[Test]
    public function it_opens_a_claim_on_an_active_contract(): void
    {
        [, $contract] = $this->sellWithWarranty();

        $this->postJson("/api/warranties/contracts/{$contract->id}/claims", [
            'reason' => 'defect', 'description' => 'Écran noir au démarrage',
        ], $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.status', WarrantyClaim::STATUS_OPEN)
            ->assertJsonPath('data.out_of_warranty', false);

        $claim = WarrantyClaim::withoutTenantScope()->where('warranty_contract_id', $contract->id)->first();
        $this->assertSame(self::CUSTOMER_ID, $claim->customer_id);
        $this->assertSame($contract->inventory_unit_id, $claim->inventory_unit_id);
    }

    #[Test]
    public function it_walks_a_claim_through_repair_to_resolution(): void
    {
        [, $contract] = $this->sellWithWarranty();
        $claim = $this->app->make(\App\Modules\Warranties\Services\WarrantyClaimService::class)
            ->open($contract, ['reason' => 'malfunction'], $this->user->id);

        $this->postJson("/api/warranties/claims/{$claim->id}/transition", ['status' => 'in_repair', 'diagnostic' => 'Nappe HS'], $this->auth())
            ->assertOk()->assertJsonPath('data.status', 'in_repair');

        $this->postJson("/api/warranties/claims/{$claim->id}/transition", ['status' => 'resolved', 'resolution' => 'repair'], $this->auth())
            ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.resolution', 'repair');

        $this->assertNotNull($claim->fresh()->resolved_at);
    }

    #[Test]
    public function a_terminal_claim_cannot_be_reopened(): void
    {
        [, $contract] = $this->sellWithWarranty();
        $claim = $this->app->make(\App\Modules\Warranties\Services\WarrantyClaimService::class)
            ->open($contract, ['reason' => 'other'], $this->user->id);
        $this->app->make(\App\Modules\Warranties\Services\WarrantyClaimService::class)
            ->transition($claim, 'rejected', [], $this->user->id);

        $this->postJson("/api/warranties/claims/{$claim->id}/transition", ['status' => 'in_repair'], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function an_expired_contract_refuses_a_claim_without_override(): void
    {
        [, $contract] = $this->sellWithWarranty();
        $contract->update(['ends_at' => now()->subDay()]); // garantie expirée

        $this->postJson("/api/warranties/contracts/{$contract->id}/claims", ['reason' => 'defect'], $this->auth())
            ->assertStatus(422);

        // Avec override → acceptée et tracée hors garantie.
        $this->postJson("/api/warranties/contracts/{$contract->id}/claims", ['reason' => 'defect', 'override' => true], $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.out_of_warranty', true);
    }

    #[Test]
    public function a_void_contract_refuses_a_claim_even_with_override(): void
    {
        [, $contract] = $this->sellWithWarranty();
        $contract->update(['status' => WarrantyContract::STATUS_VOID]);

        $this->postJson("/api/warranties/contracts/{$contract->id}/claims", ['reason' => 'defect', 'override' => true], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function it_lists_claims_for_an_order(): void
    {
        [$order, $contract] = $this->sellWithWarranty();
        $this->app->make(\App\Modules\Warranties\Services\WarrantyClaimService::class)
            ->open($contract, ['reason' => 'breakage'], $this->user->id);

        $this->getJson("/api/warranties/orders/{$order->id}/claims", $this->auth())
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.reason', 'breakage');
    }

    #[Test]
    public function an_invalid_reason_is_rejected(): void
    {
        [, $contract] = $this->sellWithWarranty();

        $this->postJson("/api/warranties/contracts/{$contract->id}/claims", ['reason' => 'banana'], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function claims_are_isolated_per_tenant(): void
    {
        [, $contract] = $this->sellWithWarranty();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-sav', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherUser = User::create(['name' => 'O', 'email' => 'o@sav.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $otherUser->assignTenantRole('manager');
        $otherToken = $otherUser->createToken('api')->plainTextToken;

        $this->postJson("/api/warranties/contracts/{$contract->id}/claims", ['reason' => 'defect'],
            ['Authorization' => "Bearer {$otherToken}"])
            ->assertStatus(404);
    }
}
