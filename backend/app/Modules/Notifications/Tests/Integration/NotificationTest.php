<?php

namespace App\Modules\Notifications\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\RenewalService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6A — notifications : canaux par tenant, templates dynamiques (global → surcharge), outbox,
 * transports (log / http_api), branchements billing & digital.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private NotificationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Notif', 'slug' => 'notif-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@notif.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->svc = $this->app->make(NotificationService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function logChannel(bool $default = true): NotificationChannel
    {
        return NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'email', 'provider' => 'log',
            'name' => 'Log dev', 'from_name' => 'Frynov', 'from_address' => 'no-reply@frynov.app',
            'is_active' => true, 'is_default' => $default,
        ]);
    }

    #[Test]
    public function notify_renders_the_global_template_into_the_outbox(): void
    {
        $this->logChannel();

        $out = $this->svc->notify($this->tenant->id, 'billing.renewal_reminder', 'boss@notif.sn', [
            'tenant_name' => 'Notif', 'plan' => 'Pro', 'period_end' => '30/06/2026', 'days_left' => 3,
        ]);

        $this->assertNotNull($out);
        $this->assertSame(NotificationOutbox::STATUS_PENDING, $out->status);
        $this->assertStringContainsString('Pro', $out->subject);
        $this->assertStringContainsString('30/06/2026', $out->body);
        $this->assertStringContainsString('3 jour(s)', $out->body);
    }

    #[Test]
    public function a_tenant_template_override_wins_over_the_global_one(): void
    {
        $this->logChannel();
        NotificationTemplate::create([
            'tenant_id' => $this->tenant->id, 'code' => 'billing.renewal_reminder', 'channel' => 'email',
            'locale' => 'fr', 'subject' => 'Custom {{plan}}', 'body' => 'Corps custom {{plan}}', 'is_active' => true,
        ]);

        $out = $this->svc->notify($this->tenant->id, 'billing.renewal_reminder', 'boss@notif.sn', ['plan' => 'Pro']);

        $this->assertSame('Custom Pro', $out->subject);
        $this->assertSame('Corps custom Pro', $out->body);
    }

    #[Test]
    public function without_an_active_channel_notify_is_a_silent_noop(): void
    {
        $out = $this->svc->notify($this->tenant->id, 'billing.renewal_reminder', 'boss@notif.sn');

        $this->assertNull($out);
        $this->assertSame(0, NotificationOutbox::withoutTenantScope()->count());
    }

    #[Test]
    public function flush_sends_via_http_api_proxy_and_marks_failures_with_retry(): void
    {
        Http::fake([
            'sms.example.test/*' => Http::sequence()
                ->push(['ok' => true], 200)   // 1er envoi OK
                ->push('boom', 500)           // 2e : échec 1
                ->push('boom', 500)           // échec 2
                ->push('boom', 500),          // échec 3 → failed
        ]);

        $channel = NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'sms', 'provider' => 'http_api',
            'name' => 'Agrégateur SMS', 'from_address' => 'FRYNOV',
            'config' => ['url' => 'https://sms.example.test/send', 'headers' => ['Authorization' => 'Bearer k'],
                'payload' => ['to' => '{{to}}', 'text' => '{{message}}', 'sender' => '{{from}}']],
            'is_active' => true, 'is_default' => true,
        ]);
        NotificationTemplate::create([
            'tenant_id' => null, 'code' => 'test.sms', 'channel' => 'sms', 'locale' => 'fr',
            'body' => 'Bonjour {{name}}', 'is_active' => true,
        ]);

        $ok  = $this->svc->notify($this->tenant->id, 'test.sms', '+221770000001', ['name' => 'Awa'], 'sms');
        $ko  = $this->svc->notify($this->tenant->id, 'test.sms', '+221770000002', ['name' => 'Bineta'], 'sms');

        $this->svc->flush(); // ok → sent ; ko → échec 1 (pending)
        $this->assertSame(NotificationOutbox::STATUS_SENT, $ok->fresh()->status);
        $this->assertSame(NotificationOutbox::STATUS_PENDING, $ko->fresh()->status);

        $this->svc->flush(); // échec 2 (pending)
        $this->svc->flush(); // échec 3 → failed définitif
        $this->assertSame(NotificationOutbox::STATUS_FAILED, $ko->fresh()->status);
        $this->assertSame(3, $ko->fresh()->attempts);
        $this->assertNotNull($ko->fresh()->last_error);
    }

    #[Test]
    public function the_channels_api_never_leaks_secrets_and_keeps_them_on_partial_update(): void
    {
        $create = $this->postJson('/api/notifications/channels', [
            'channel' => 'email', 'provider' => 'smtp', 'name' => 'SMTP OVH',
            'from_name' => 'Boutique', 'from_address' => 'contact@boutique.sn',
            'config' => ['host' => 'smtp.ovh.net', 'port' => 587, 'username' => 'u', 'password' => 'SECRET'],
            'is_default' => true,
        ], $this->auth())->assertCreated();

        // Les secrets ne sortent jamais : seules les CLÉS de config sont exposées.
        $this->assertSame(['host', 'port', 'username', 'password'], $create->json('data.config_keys'));
        $this->assertNull($create->json('data.config'));

        // PATCH partiel (sans password) → le secret existant est conservé.
        $id = $create->json('data.id');
        $this->patchJson("/api/notifications/channels/{$id}", ['config' => ['host' => 'smtp2.ovh.net']], $this->auth())
            ->assertOk();

        $channel = NotificationChannel::withoutTenantScope()->find($id);
        $this->assertSame('smtp2.ovh.net', $channel->config['host']);
        $this->assertSame('SECRET', $channel->config['password']);
    }

    #[Test]
    public function the_test_endpoint_sends_through_the_channel(): void
    {
        $channel = $this->logChannel();

        $this->postJson("/api/notifications/channels/{$channel->id}/test", ['recipient' => 'test@notif.sn'], $this->auth())
            ->assertOk();
    }

    #[Test]
    public function renewal_reminders_reach_the_outbox_when_a_channel_exists(): void
    {
        $this->logChannel();
        $paid = Plan::firstOrCreate(['code' => 'pro'], ['name' => 'Pro', 'price_monthly_cents' => 1500000, 'price_yearly_cents' => 15000000, 'currency' => 'XOF', 'trial_days' => 0, 'is_active' => true, 'is_public' => true, 'sort_order' => 2]);
        Subscription::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $paid->id, 'status' => Subscription::STATUS_ACTIVE,
            'interval' => 'monthly', 'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDays(2),
        ]);

        $this->app->make(RenewalService::class)->processRenewals();

        $out = NotificationOutbox::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('template_code', 'billing.renewal_reminder')
            ->first();
        $this->assertNotNull($out);
        $this->assertSame('m@notif.sn', $out->recipient); // fallback : email du premier user
        $this->assertStringContainsString('Pro', $out->subject);
    }

    #[Test]
    public function a_digital_sale_emails_the_customer_token_and_license(): void
    {
        $this->logChannel();
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Awa', 'email' => 'awa@client.sn']);
        $ebook = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'EBOOK', 'name' => 'Ebook PHP', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
            'fulfillment_type' => Product::FULFILLMENT_LICENSE,
        ]);

        $orders = $this->app->make(OrderService::class);
        $order  = $orders->create(['customer_id' => $customer->id, 'items' => [['product_id' => $ebook->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order  = $orders->confirm($order, $this->user->id);
        $orders->fulfill($order, $this->user->id);

        $out = NotificationOutbox::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('template_code', 'digital.delivery')
            ->first();
        $this->assertNotNull($out);
        $this->assertSame('awa@client.sn', $out->recipient);
        $this->assertStringContainsString('Ebook PHP', $out->subject);
        $this->assertStringContainsString('Clé de licence', $out->body);
    }

    #[Test]
    public function channels_are_isolated_per_tenant(): void
    {
        $channel = $this->logChannel();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-notif', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherUser = User::create(['name' => 'O', 'email' => 'o@notif.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $otherUser->assignTenantRole('manager');
        $otherToken = $otherUser->createToken('api')->plainTextToken;

        $this->patchJson("/api/notifications/channels/{$channel->id}", ['name' => 'Hack'],
            ['Authorization' => "Bearer {$otherToken}"])
            ->assertStatus(404);
    }
}
