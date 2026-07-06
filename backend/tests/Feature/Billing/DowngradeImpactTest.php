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
 * P3 — l'aperçu d'impact d'un changement de plan expose les modules retirés et les quotas dépassés
 * AVANT confirmation (aucune donnée supprimée). Critère d'acceptation #17.
 */
class DowngradeImpactTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $planCode, int $extraUsers = 0): array
    {
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => $planCode, 'status' => 'active',
            'subscription_status' => Subscription::STATUS_ACTIVE,
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', $planCode)->value('id'),
            'status' => Subscription::STATUS_ACTIVE, 'interval' => Subscription::INTERVAL_MONTHLY,
            'currency' => 'XOF', 'market_code' => 'waemu',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        for ($i = 0; $i < $extraUsers; $i++) {
            User::factory()->create(['tenant_id' => $tenant->id]);
        }

        return [$tenant, $user];
    }

    #[Test]
    public function a_downgrade_reports_quota_overages(): void
    {
        $this->seed(PlansSeeder::class);
        // Tenant sur Croissance (pro) avec 2 utilisateurs → passage à Découverte (starter, 1 user max).
        [, $user] = $this->tenantOnPlan(Plan::CODE_PRO, extraUsers: 1);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/me/subscription/downgrade-impact', [
            'plan_code' => Plan::CODE_STARTER,
        ])->assertOk()
            ->assertJsonPath('is_downgrade', true)
            ->assertJsonPath('has_impact', true);

        $overages = collect($res->json('quota_overages'));
        $users = $overages->firstWhere('resource', 'users');
        $this->assertNotNull($users, 'Le dépassement du nombre d\'utilisateurs doit être signalé.');
        $this->assertSame(2, $users['usage']);
        $this->assertSame(1, $users['limit']);
        $this->assertSame(1, $users['excess']);
    }

    #[Test]
    public function an_upgrade_has_no_impact(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantOnPlan(Plan::CODE_STARTER);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/downgrade-impact', [
            'plan_code' => Plan::CODE_ENTERPRISE,   // illimité → aucun dépassement
        ])->assertOk()
            ->assertJsonPath('is_downgrade', false)
            ->assertJsonPath('has_impact', false)
            ->assertJsonPath('quota_overages', []);
    }
}
