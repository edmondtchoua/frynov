<?php

namespace App\Modules\Warranties\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
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
 * RC-6F — garanties+ : durées en jours/mois/années, un contrat PAR EXEMPLAIRE pour l'agrégé,
 * extension (payante) d'un contrat.
 */
class WarrantyPlusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Wplus', 'slug' => 'wplus-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@wplus.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function sellAggregate(WarrantyPolicy $policy, int $qty): Order
    {
        $p = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'BLND-' . uniqid(), 'name' => 'Blender', 'price_amount' => 30000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
            'warranty_policy_id' => $policy->id,
        ]);
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $p->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 10);

        $order = $this->orders->create(['items' => [['product_id' => $p->id, 'quantity' => $qty]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    #[Test]
    public function a_policy_in_days_computes_ends_at_in_days(): void
    {
        $policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G 90 jours',
            'duration_months' => 90, 'duration_unit' => 'day', 'is_active' => true,
        ]);

        $order = $this->sellAggregate($policy, 1);
        $c = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();

        $this->assertEquals(90, round($c->starts_at->diffInDays($c->ends_at)));
    }

    #[Test]
    public function a_policy_in_years_computes_ends_at_in_years(): void
    {
        $policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G 2 ans',
            'duration_months' => 2, 'duration_unit' => 'year', 'is_active' => true,
        ]);

        $order = $this->sellAggregate($policy, 1);
        $c = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();

        $this->assertEquals(2, round($c->starts_at->diffInYears($c->ends_at)));
    }

    #[Test]
    public function an_aggregate_sale_issues_one_contract_per_unit_sold(): void
    {
        $policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G12',
            'duration_months' => 12, 'duration_unit' => 'month', 'is_active' => true,
        ]);

        // RC-6F : qty 3 → 3 contrats (un par exemplaire), plus un seul par ligne.
        $order = $this->sellAggregate($policy, 3);

        $this->assertSame(3, WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_contract_can_be_extended_and_the_extension_is_audited(): void
    {
        $policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G12',
            'duration_months' => 12, 'duration_unit' => 'month', 'is_active' => true,
        ]);
        $order = $this->sellAggregate($policy, 1);
        $c = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();
        $before = $c->ends_at->copy();

        $this->postJson("/api/warranties/contracts/{$c->id}/extend", [
            'duration' => 6, 'unit' => 'month', 'reason' => 'Extension vendue ORD-XXX',
        ], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertTrue($c->fresh()->ends_at->equalTo($before->addMonths(6)));
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => 'warranty.extended']);
    }

    #[Test]
    public function a_void_contract_cannot_be_extended(): void
    {
        $policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G12',
            'duration_months' => 12, 'duration_unit' => 'month', 'is_active' => true,
        ]);
        $order = $this->sellAggregate($policy, 1);
        $c = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();
        $c->update(['status' => WarrantyContract::STATUS_VOID]);

        $this->postJson("/api/warranties/contracts/{$c->id}/extend", ['duration' => 6, 'unit' => 'month'], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function an_expired_contract_can_be_extended_back_to_active(): void
    {
        $policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G12',
            'duration_months' => 12, 'duration_unit' => 'month', 'is_active' => true,
        ]);
        $order = $this->sellAggregate($policy, 1);
        $c = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();
        $c->update(['ends_at' => now()->subDay(), 'status' => WarrantyContract::STATUS_EXPIRED]);

        // Extension d'un contrat expiré : repart de MAINTENANT (pas de la vieille échéance) et réactive.
        $this->postJson("/api/warranties/contracts/{$c->id}/extend", ['duration' => 3, 'unit' => 'month'], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $fresh = $c->fresh();
        $this->assertTrue($fresh->ends_at->isFuture());
        $this->assertEquals(3, round(now()->diffInMonths($fresh->ends_at)));
    }
}
