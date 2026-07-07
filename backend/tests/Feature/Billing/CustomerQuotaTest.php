<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\PlanLimit;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Audit RBAC P1 — le quota clients (`max_customers`) du plan est désormais appliqué côté backend
 * (middleware quota:customers), pas seulement affiché.
 */
class CustomerQuotaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_customer_beyond_the_plan_limit_is_blocked(): void
    {
        $this->seed(PlansSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Plafond clients ramené à 1 pour le plan Découverte (test rapide).
        PlanLimit::where('plan_id', Plan::where('code', 'starter')->value('id'))->update(['max_customers' => 1]);

        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => 'starter', 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        // 1er client : OK.
        $this->postJson('/api/customers', ['name' => 'Client 1'])->assertCreated();

        // 2e client : bloqué par le quota (402 Payment Required).
        $this->postJson('/api/customers', ['name' => 'Client 2'])
            ->assertStatus(402)
            ->assertJsonPath('error', 'quota_exceeded');
    }
}
