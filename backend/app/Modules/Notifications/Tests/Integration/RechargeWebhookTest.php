<?php

namespace App\Modules\Notifications\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Notifications\Models\CommunicationCreditMovement;
use App\Modules\Notifications\Models\CreditRechargeOrder;
use App\Modules\Notifications\Services\CommunicationCreditService;
use App\Modules\Notifications\Services\RechargeOrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-7F — webhook Mobile Money : commande de recharge → paiement → confirmation signée HMAC →
 * crédit automatique. Idempotence, signature, montant divergent, référence inconnue, annulation.
 */
class RechargeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private Tenant $tenant;
    private User $user;
    private string $token;
    private CommunicationCreditService $credits;
    private RechargeOrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Momo', 'slug' => 'momo-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@momo.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->credits = $this->app->make(CommunicationCreditService::class);
        $this->orders  = $this->app->make(RechargeOrderService::class);

        config(['notifications.mobile_money.webhook_secret' => self::SECRET]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** POST signé HMAC-SHA256 sur le corps JSON exact, comme le ferait le fournisseur. */
    private function signedWebhook(array $payload, ?string $secret = self::SECRET)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/webhooks/mobile-money', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    private function pendingOrder(string $pack = 'sms_1k'): CreditRechargeOrder
    {
        return $this->orders->createOrder($this->tenant->id, $pack, $this->user->id);
    }

    private function successPayload(CreditRechargeOrder $order, array $overrides = []): array
    {
        return array_merge([
            'reference'      => $order->reference,
            'status'         => 'SUCCESS',
            'amount_cents'   => $order->price_cents,
            'currency'       => $order->currency,
            'transaction_id' => 'TX-001',
            'provider'       => 'orange_money',
        ], $overrides);
    }

    #[Test]
    public function creating_an_order_returns_a_payable_reference(): void
    {
        $res = $this->postJson('/api/notifications/credits/orders', ['pack_code' => 'sms_1k'], $this->auth())
            ->assertCreated();

        $this->assertStringStartsWith('RCH-', $res->json('data.reference'));
        $this->assertSame('pending', $res->json('data.status'));
        $this->assertSame(1000, $res->json('data.credits'));
        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms')); // rien tant que non payé
    }

    #[Test]
    public function a_signed_webhook_confirms_the_order_and_credits_the_channel(): void
    {
        $order = $this->pendingOrder();

        $this->signedWebhook($this->successPayload($order))
            ->assertOk()
            ->assertJsonPath('result', 'confirmed');

        $order->refresh();
        $this->assertSame(CreditRechargeOrder::STATUS_PAID, $order->status);
        $this->assertSame('orange_money', $order->provider);
        $this->assertSame('TX-001', $order->provider_ref);
        $this->assertNotNull($order->movement_id);
        $this->assertSame(1000, $this->credits->balance($this->tenant->id, 'sms'));

        // Le mouvement de recharge porte la référence de la commande.
        $mv = CommunicationCreditMovement::withoutTenantScope()->find($order->movement_id);
        $this->assertSame($order->reference, $mv->reference);
        $this->assertSame('recharge', $mv->reason);
    }

    #[Test]
    public function replaying_the_webhook_never_credits_twice(): void
    {
        $order = $this->pendingOrder();

        $this->signedWebhook($this->successPayload($order))->assertOk()->assertJsonPath('result', 'confirmed');
        $this->signedWebhook($this->successPayload($order))->assertOk()->assertJsonPath('result', 'already_processed');

        $this->assertSame(1000, $this->credits->balance($this->tenant->id, 'sms')); // une seule fois
        $this->assertSame(1, CommunicationCreditMovement::withoutTenantScope()->where('reason', 'recharge')->count());
    }

    #[Test]
    public function an_invalid_signature_is_rejected_and_nothing_is_credited(): void
    {
        $order = $this->pendingOrder();

        $this->signedWebhook($this->successPayload($order), 'wrong-secret')->assertStatus(401);

        $this->assertSame(CreditRechargeOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms'));
    }

    #[Test]
    public function without_a_configured_secret_the_webhook_is_disabled(): void
    {
        config(['notifications.mobile_money.webhook_secret' => '']);
        $order = $this->pendingOrder();

        $this->signedWebhook($this->successPayload($order), '')->assertStatus(503);
        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms'));
    }

    #[Test]
    public function an_unknown_reference_is_ignored_without_crediting(): void
    {
        $this->signedWebhook(['reference' => 'RCH-UNKNOWN1', 'status' => 'SUCCESS', 'amount_cents' => 1, 'currency' => 'XOF'])
            ->assertOk()
            ->assertJsonPath('result', 'unknown_reference');

        $this->assertSame(0, CommunicationCreditMovement::withoutTenantScope()->count());
    }

    #[Test]
    public function an_amount_mismatch_flags_the_order_for_review_without_crediting(): void
    {
        $order = $this->pendingOrder(); // sms_1k → 1 500 000

        $this->signedWebhook($this->successPayload($order, ['amount_cents' => 999]))
            ->assertOk()
            ->assertJsonPath('result', 'mismatch');

        $order->refresh();
        $this->assertSame(CreditRechargeOrder::STATUS_NEEDS_REVIEW, $order->status);
        $this->assertSame(999, $order->meta['mismatch']['amount_cents']);
        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms'));
    }

    #[Test]
    public function a_non_final_status_is_ignored(): void
    {
        $order = $this->pendingOrder();

        $this->signedWebhook($this->successPayload($order, ['status' => 'PENDING']))
            ->assertOk()
            ->assertJsonPath('result', 'ignored');

        $this->assertSame(CreditRechargeOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms'));
    }

    #[Test]
    public function a_cancelled_order_cannot_be_paid(): void
    {
        $order = $this->pendingOrder();
        $this->postJson("/api/notifications/credits/orders/{$order->id}/cancel", [], $this->auth())->assertOk();

        $this->signedWebhook($this->successPayload($order))
            ->assertOk()
            ->assertJsonPath('result', 'not_payable');

        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms'));
    }

    #[Test]
    public function orders_are_listed_for_the_tenant_only(): void
    {
        $mine = $this->pendingOrder();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-momo', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->orders->createOrder($other->id, 'sms_1k');

        $res = $this->getJson('/api/notifications/credits/orders', $this->auth())->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame($mine->reference, $res->json('data.0.reference'));
    }
}
