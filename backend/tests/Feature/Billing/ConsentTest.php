<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Models\SubscriptionConsent;
use App\Modules\Billing\Services\ConsentService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P2 — le consentement est OBLIGATOIRE et TRACÉ (texte, version, IP, entité liée) à la soumission.
 */
class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private function actingTenant(): array
    {
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => Plan::CODE_STARTER, 'status' => 'active',
            'subscription_status' => Subscription::STATUS_TRIALING,
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status' => Subscription::STATUS_TRIALING, 'interval' => Subscription::INTERVAL_MONTHLY,
            'currency' => 'XOF', 'market_code' => 'waemu', 'amount_paid_minor' => 0,
            'trial_ends_at' => now()->addDays(14), 'current_period_start' => now(), 'current_period_end' => now()->addDays(14),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user);

        return [$tenant, $user];
    }

    #[Test]
    public function submitting_without_consent_is_rejected(): void
    {
        $this->seed(PlansSeeder::class);
        $this->actingTenant();

        $this->postJson('/api/me/manual-payments', [
            'plan_code'      => Plan::CODE_ESSENTIAL,
            'interval'       => 'monthly',
            'payment_method' => 'orange_money',
            'market_code'    => 'waemu',
            // pas de consent
        ])->assertStatus(422)->assertJsonValidationErrors(['consent']);

        $this->assertDatabaseCount('subscription_consents', 0);
    }

    #[Test]
    public function submitting_with_consent_records_an_immutable_trace(): void
    {
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->actingTenant();

        $crId = $this->postJson('/api/me/manual-payments', [
            'plan_code'      => Plan::CODE_ESSENTIAL,
            'interval'       => 'monthly',
            'payment_method' => 'orange_money',
            'market_code'    => 'waemu',
            'consent'        => true,
        ])->assertCreated()->json('change_request_id');

        $consent = SubscriptionConsent::withoutTenantScope()->firstOrFail();
        $this->assertSame($tenant->id, $consent->tenant_id);
        $this->assertSame($user->id, $consent->user_id);
        $this->assertSame(SubscriptionConsent::ACTION_PLAN_CHANGE, $consent->action_type);
        $this->assertSame(SubscriptionConsent::SOURCE_PLATFORM, $consent->source);
        $this->assertSame(ConsentService::PLAN_CHANGE_VERSION, $consent->consent_version);
        $this->assertSame(ConsentService::PLAN_CHANGE_TEXT, $consent->consent_text);
        $this->assertNotNull($consent->accepted_at);
        // Lié à la demande de changement.
        $this->assertSame(SubscriptionChangeRequest::class, $consent->related_entity_type);
        $this->assertSame($crId, $consent->related_entity_id);
    }

    #[Test]
    public function the_consent_text_endpoint_exposes_version_and_text(): void
    {
        $this->seed(PlansSeeder::class);
        $this->actingTenant();

        $this->getJson('/api/me/subscription/consent-text')
            ->assertOk()
            ->assertJsonPath('version', ConsentService::PLAN_CHANGE_VERSION)
            ->assertJsonPath('text', ConsentService::PLAN_CHANGE_TEXT);
    }
}
