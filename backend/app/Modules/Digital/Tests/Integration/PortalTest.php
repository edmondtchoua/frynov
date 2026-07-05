<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6C — portail client digital (public) : accès par jeton, refus des révoqués, « mes achats »
 * par email (réponse générique, envoi par le canal du tenant), liens signés inclus.
 */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Portal', 'slug' => 'portal-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@portal.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Awa', 'email' => 'awa@client.sn']);

        $this->orders = $this->app->make(OrderService::class);
    }

    /** Vend un produit licence au client et renvoie l'entitlement. */
    private function sellDigital(): DigitalEntitlement
    {
        $ebook = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'EBOOK-' . uniqid(), 'name' => 'Ebook PHP', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
            'fulfillment_type' => Product::FULFILLMENT_LICENSE,
        ]);
        $order = $this->orders->create(['customer_id' => $this->customer->id, 'items' => [['product_id' => $ebook->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->fulfill($order, $this->user->id);

        return DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->firstOrFail();
    }

    #[Test]
    public function the_portal_opens_an_access_by_token_without_auth(): void
    {
        $ent = $this->sellDigital();

        $this->postJson('/api/portal/digital/access', ['token' => $ent->access_token])
            ->assertOk()
            ->assertJsonPath('data.product_name', 'Ebook PHP')
            ->assertJsonPath('data.seller_name', 'Portal')
            ->assertJsonPath('data.license_key', $ent->license_key);
    }

    #[Test]
    public function a_revoked_access_is_refused_and_an_unknown_token_is_404(): void
    {
        $ent = $this->sellDigital();
        $ent->update(['status' => DigitalEntitlement::STATUS_REVOKED]);

        $this->postJson('/api/portal/digital/access', ['token' => $ent->access_token])->assertStatus(403);
        $this->postJson('/api/portal/digital/access', ['token' => (string) \Illuminate\Support\Str::uuid()])->assertStatus(404);
    }

    #[Test]
    public function request_links_emails_the_magic_links_via_the_tenant_channel(): void
    {
        NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'email', 'provider' => 'log',
            'name' => 'Log', 'is_active' => true, 'is_default' => true,
        ]);
        $ent = $this->sellDigital();

        $this->postJson('/api/portal/digital/request-links', ['email' => 'awa@client.sn'])
            ->assertOk();

        $out = NotificationOutbox::withoutTenantScope()
            ->where('template_code', 'digital.portal_links')
            ->first();
        $this->assertNotNull($out);
        $this->assertSame('awa@client.sn', $out->recipient);
        $this->assertStringContainsString($ent->access_token, $out->body);      // lien magique
        $this->assertStringContainsString('/portal?token=', $out->body);
    }

    #[Test]
    public function request_links_answers_generically_for_an_unknown_email(): void
    {
        $this->postJson('/api/portal/digital/request-links', ['email' => 'inconnu@nulpart.sn'])
            ->assertOk();

        $this->assertSame(0, NotificationOutbox::withoutTenantScope()->count());
    }

    #[Test]
    public function the_delivery_email_now_carries_the_magic_portal_link(): void
    {
        NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'email', 'provider' => 'log',
            'name' => 'Log', 'is_active' => true, 'is_default' => true,
        ]);

        $ent = $this->sellDigital();

        $out = NotificationOutbox::withoutTenantScope()
            ->where('template_code', 'digital.delivery')
            ->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('/portal?token=' . $ent->access_token, $out->body);
    }
}
