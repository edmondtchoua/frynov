<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Models\SubscriptionNotification;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Option — l'admin peut DEMANDER UNE CORRECTION (non destructif) au lieu de rejeter. */
class RequestCorrectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_requests_a_correction_and_the_tenant_is_notified(): void
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

        Sanctum::actingAs($user);
        $crId = $this->postJson('/api/me/manual-payments', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly',
            'payment_method' => 'orange_money', 'market_code' => 'waemu', 'consent' => true,
        ])->assertCreated()->json('change_request_id');

        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();

        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/manual-payments/{$payment->id}/request-correction", [
            'reason' => 'Preuve floue, merci de renvoyer une image nette.',
        ])->assertOk();

        // Le paiement reste EN ATTENTE ; la demande repasse en pending_payment avec la consigne.
        $payment->refresh();
        $this->assertSame(ManualPayment::STATUS_PENDING, $payment->status);

        $cr = SubscriptionChangeRequest::withoutTenantScope()->findOrFail($crId);
        $this->assertSame(SubscriptionChangeRequest::STATUS_PENDING_PAYMENT, $cr->status);
        $this->assertSame('Preuve floue, merci de renvoyer une image nette.', $cr->metadata['correction_note']);

        // Le tenant a une notification in-app de correction.
        $this->assertTrue(SubscriptionNotification::withoutTenantScope()
            ->where('change_request_id', $crId)->where('type', 'correction')->exists());
    }
}
