<?php

namespace App\Modules\Notifications\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Notifications\Models\CommunicationCredit;
use App\Modules\Notifications\Models\CommunicationCreditMovement;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Services\CommunicationCreditService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-7E — crédits de communication rechargeables par tenant × canal : décompte à l'envoi (avec
 * remboursement sur échec), blocage à zéro, recharge par pack (rail manuel), soldes/mouvements API.
 */
class CommunicationCreditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private CommunicationCreditService $credits;
    private NotificationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Cred', 'slug' => 'cred-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@cred.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->credits = $this->app->make(CommunicationCreditService::class);
        $this->svc     = $this->app->make(NotificationService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function smsHttpChannel(): NotificationChannel
    {
        return NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'sms', 'provider' => 'http_api',
            'name' => 'Agrégateur', 'from_address' => 'FRYNOV',
            'config' => ['url' => 'https://sms.example.test/send', 'payload' => ['to' => '{{to}}', 'text' => '{{message}}']],
            'is_active' => true, 'is_default' => true,
        ]);
    }

    private function smsTemplate(): void
    {
        NotificationTemplate::create([
            'tenant_id' => null, 'code' => 'test.sms', 'channel' => 'sms', 'locale' => 'fr',
            'body' => 'Bonjour {{name}}', 'is_active' => true,
        ]);
    }

    // ── Service ─────────────────────────────────────────────────────────────

    #[Test]
    public function debit_reduces_the_balance_and_records_a_movement(): void
    {
        $this->credits->credit($this->tenant->id, 'sms', 5);

        $this->assertTrue($this->credits->debit($this->tenant->id, 'sms', 1, CommunicationCreditMovement::REASON_SEND, 'out-1'));
        $this->assertSame(4, $this->credits->balance($this->tenant->id, 'sms'));

        $mv = CommunicationCreditMovement::withoutTenantScope()->where('reason', 'send')->first();
        $this->assertSame(-1, $mv->delta);
        $this->assertSame(4, $mv->balance_after);
        $this->assertSame('out-1', $mv->reference);
    }

    #[Test]
    public function debit_fails_without_enough_credit_and_records_nothing(): void
    {
        $this->credits->credit($this->tenant->id, 'sms', 0); // ligne à 0

        $this->assertFalse($this->credits->debit($this->tenant->id, 'sms', 1));
        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms'));
        $this->assertSame(0, CommunicationCreditMovement::withoutTenantScope()->where('reason', 'send')->count());
    }

    #[Test]
    public function recharging_a_pack_credits_the_matching_channel(): void
    {
        // Pack sms_1k = 1000 crédits SMS.
        $res = $this->credits->recharge($this->tenant->id, 'sms_1k', 'PAY-123', $this->user->id);

        $this->assertSame('sms', $res['channel']);
        $this->assertSame(1000, $res['credits_added']);
        $this->assertSame(1000, $res['balance']);
        $this->assertSame(1000, $this->credits->balance($this->tenant->id, 'sms'));

        $mv = CommunicationCreditMovement::withoutTenantScope()->where('reason', 'recharge')->first();
        $this->assertSame('PAY-123', $mv->reference);
        $this->assertSame('sms_1k', $mv->meta['pack']);
    }

    #[Test]
    public function an_unknown_pack_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->credits->recharge($this->tenant->id, 'does_not_exist');
    }

    // ── Décompte à l'envoi (flush) ──────────────────────────────────────────

    #[Test]
    public function flush_debits_one_credit_per_sent_message(): void
    {
        Http::fake(['sms.example.test/*' => Http::response(['ok' => true], 200)]);
        $this->smsHttpChannel();
        $this->smsTemplate();
        $this->credits->credit($this->tenant->id, 'sms', 3);

        $out = $this->svc->notify($this->tenant->id, 'test.sms', '+221770000001', ['name' => 'Awa'], 'sms');
        $result = $this->svc->flush();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(NotificationOutbox::STATUS_SENT, $out->fresh()->status);
        $this->assertSame(2, $this->credits->balance($this->tenant->id, 'sms')); // 3 → 2
    }

    #[Test]
    public function flush_blocks_the_send_when_the_balance_is_zero(): void
    {
        Http::fake(['sms.example.test/*' => Http::response(['ok' => true], 200)]);
        $this->smsHttpChannel();
        $this->smsTemplate();
        // Aucun crédit SMS.

        $out = $this->svc->notify($this->tenant->id, 'test.sms', '+221770000009', ['name' => 'Zero'], 'sms');
        $result = $this->svc->flush();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['blocked']);
        $this->assertSame(NotificationOutbox::STATUS_NO_CREDIT, $out->fresh()->status);
        // Aucun appel réseau n'a été tenté (bloqué avant l'envoi).
        Http::assertNothingSent();
    }

    #[Test]
    public function a_failed_send_refunds_the_reserved_credit(): void
    {
        Http::fake(['sms.example.test/*' => Http::response('boom', 500)]);
        $this->smsHttpChannel();
        $this->smsTemplate();
        $this->credits->credit($this->tenant->id, 'sms', 1);

        $out = $this->svc->notify($this->tenant->id, 'test.sms', '+221770000002', ['name' => 'Bad'], 'sms');
        $this->svc->flush(); // échec → remboursé

        $this->assertSame(NotificationOutbox::STATUS_PENDING, $out->fresh()->status);
        $this->assertSame(1, $this->credits->balance($this->tenant->id, 'sms')); // remboursé
        $this->assertSame(1, CommunicationCreditMovement::withoutTenantScope()->where('reason', 'refund')->count());
    }

    #[Test]
    public function a_recharge_requeues_messages_that_were_blocked_for_no_credit(): void
    {
        // Recette QA — un envoi bloqué faute de crédit (no_credit) repart après recharge.
        Http::fake(['sms.example.test/*' => Http::response(['ok' => true], 200)]);
        $this->smsHttpChannel();
        $this->smsTemplate();

        $out = $this->svc->notify($this->tenant->id, 'test.sms', '+221770000003', ['name' => 'Later'], 'sms');
        $this->svc->flush();
        $this->assertSame(NotificationOutbox::STATUS_NO_CREDIT, $out->fresh()->status);

        // Recharge → l'envoi bloqué est réarmé (pending) puis part au flush suivant.
        $this->credits->recharge($this->tenant->id, 'sms_1k', 'PAY-1');
        $this->assertSame(NotificationOutbox::STATUS_PENDING, $out->fresh()->status);

        $this->svc->flush();
        $this->assertSame(NotificationOutbox::STATUS_SENT, $out->fresh()->status);
        $this->assertSame(999, $this->credits->balance($this->tenant->id, 'sms')); // 1000 rechargés − 1 envoyé
    }

    #[Test]
    public function a_negative_adjustment_never_drives_the_balance_below_zero(): void
    {
        $this->credits->credit($this->tenant->id, 'sms', 3);
        $this->credits->credit($this->tenant->id, 'sms', -10, CommunicationCreditMovement::REASON_ADJUSTMENT);

        $this->assertSame(0, $this->credits->balance($this->tenant->id, 'sms')); // clampé à 0, jamais négatif
    }

    // ── API ─────────────────────────────────────────────────────────────────

    #[Test]
    public function the_credits_endpoint_returns_balances_and_packs(): void
    {
        $this->credits->credit($this->tenant->id, 'sms', 42);

        $res = $this->getJson('/api/notifications/credits', $this->auth())->assertOk();
        $this->assertSame(42, $res->json('data.balances.sms'));
        $this->assertSame(0, $res->json('data.balances.whatsapp'));
        $this->assertNotEmpty($res->json('data.packs'));
    }

    #[Test]
    public function a_manager_can_recharge_a_pack_via_the_api(): void
    {
        $this->postJson('/api/notifications/credits/recharge', ['pack_code' => 'wa_1k', 'payment_reference' => 'CASH-9'], $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.balance', 1000);

        $this->assertSame(1000, $this->credits->balance($this->tenant->id, 'whatsapp'));
    }

    #[Test]
    public function the_recharge_endpoint_rejects_an_unknown_pack(): void
    {
        $this->postJson('/api/notifications/credits/recharge', ['pack_code' => 'nope'], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function credits_are_isolated_per_tenant(): void
    {
        $this->credits->credit($this->tenant->id, 'sms', 10);

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-cred', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->assertSame(0, $this->credits->balance($other->id, 'sms'));
    }
}
