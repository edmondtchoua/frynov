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
 * P2b — le fil de notifications IN-APP (cloche) reçoit les événements d'abonnement, marquables lus,
 * isolés par tenant.
 */
class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

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

    private function submit(): void
    {
        $this->postJson('/api/me/manual-payments', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly',
            'payment_method' => 'orange_money', 'market_code' => 'waemu', 'consent' => true,
        ])->assertCreated();
    }

    #[Test]
    public function submitting_creates_an_in_app_notification(): void
    {
        $this->seed(PlansSeeder::class);
        Sanctum::actingAs($this->tenantUser());
        $this->submit();

        $res = $this->getJson('/api/me/subscription/notifications')->assertOk()->assertJsonPath('unread', 1);
        $id = $res->json('data.0.id');
        $this->assertSame('submitted', $res->json('data.0.type'));

        // Marquée lue → plus rien d'unread.
        $this->postJson("/api/me/subscription/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/me/subscription/notifications')->assertOk()->assertJsonPath('unread', 0);
    }

    #[Test]
    public function a_tenant_cannot_read_another_tenants_notification(): void
    {
        $this->seed(PlansSeeder::class);
        Sanctum::actingAs($this->tenantUser());
        $this->submit();
        $id = $this->getJson('/api/me/subscription/notifications')->json('data.0.id');

        // Tenant B ne peut pas marquer lue la notification du tenant A (404).
        Sanctum::actingAs($this->tenantUser());
        $this->postJson("/api/me/subscription/notifications/{$id}/read")->assertNotFound();
    }
}
