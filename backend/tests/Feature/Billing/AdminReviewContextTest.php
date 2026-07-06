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
 * Écran admin de validation — la revue expose le CONTEXTE de la demande (plan cible, prise d'effet,
 * consentement recueilli), pas seulement le montant, pour valider en connaissance de cause.
 */
class AdminReviewContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_admin_payment_list_carries_the_change_request_context(): void
    {
        $this->seed(PlansSeeder::class);

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
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Le tenant soumet (crée paiement + demande + consentement).
        Sanctum::actingAs($user);
        $this->postJson('/api/me/manual-payments', [
            'plan_code' => Plan::CODE_ENTERPRISE, 'interval' => 'monthly',
            'payment_method' => 'orange_money', 'market_code' => 'waemu', 'consent' => true,
        ])->assertCreated();

        // L'admin voit le contexte de la demande dans la liste de validation.
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/manual-payments?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.change_request.to_plan_code', Plan::CODE_ENTERPRISE)
            ->assertJsonPath('data.0.change_request.effective', 'immediate')
            ->assertJsonPath('data.0.change_request.consent_captured', true);
    }
}
