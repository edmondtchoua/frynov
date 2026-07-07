<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P5 — back-office des plans : création (brouillon), publication, archivage, analytics. Un plan non
 * `active` n'est JAMAIS sélectionnable par un tenant (Phase 13 sécurité).
 */
class AdminPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $a = User::factory()->create();
        $a->forceFill(['is_super_admin' => true])->save();

        return $a;
    }

    private function tenantUser(): User
    {
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => Plan::CODE_STARTER, 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status' => Subscription::STATUS_TRIALING, 'interval' => 'monthly',
            'currency' => 'XOF', 'market_code' => 'waemu',
            'current_period_start' => now(), 'current_period_end' => now()->addDays(14),
        ]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    #[Test]
    public function admin_creates_a_draft_plan_that_tenants_cannot_select(): void
    {
        $this->seed(PlansSeeder::class);

        Sanctum::actingAs($this->admin());
        $id = $this->postJson('/api/admin/plans', [
            'code' => 'growth-plus', 'name' => 'Growth+', 'price_monthly_cents' => 1500000,
        ])->assertCreated()->assertJsonPath('status', 'draft')->json('id');

        // Brouillon → absent du pricing public.
        $codes = collect($this->getJson('/api/public/pricing')->json('data'))->pluck('code');
        $this->assertFalse($codes->contains('growth-plus'));

        // Brouillon → non sélectionnable par un tenant (404).
        Sanctum::actingAs($this->tenantUser());
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => 'growth-plus', 'interval' => 'monthly',
        ])->assertNotFound();

        // Publication → sélectionnable.
        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/admin/plans/{$id}", ['status' => 'active', 'is_public' => true])->assertOk();

        Sanctum::actingAs($this->tenantUser());
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => 'growth-plus', 'interval' => 'monthly',
        ])->assertOk()->assertJsonPath('net_payable_minor', 1500000);
    }

    #[Test]
    public function archiving_a_plan_removes_it_from_selection(): void
    {
        $this->seed(PlansSeeder::class);
        $enterprise = Plan::where('code', Plan::CODE_ENTERPRISE)->first();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/admin/plans/{$enterprise->id}")->assertOk()->assertJsonPath('status', 'archived');

        Sanctum::actingAs($this->tenantUser());
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ENTERPRISE, 'interval' => 'monthly',
        ])->assertNotFound();
    }

    #[Test]
    public function non_admins_cannot_manage_plans(): void
    {
        $this->seed(PlansSeeder::class);
        Sanctum::actingAs($this->tenantUser());

        $this->postJson('/api/admin/plans', ['code' => 'x', 'name' => 'X', 'price_monthly_cents' => 0])
            ->assertForbidden();
    }

    #[Test]
    public function analytics_reports_adoption_by_plan(): void
    {
        $this->seed(PlansSeeder::class);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/plans/analytics')
            ->assertOk()
            ->assertJsonStructure(['plans' => [['code', 'name', 'status', 'active_subscriptions', 'revenue_minor']], 'change_requests_by_status']);
    }
}
