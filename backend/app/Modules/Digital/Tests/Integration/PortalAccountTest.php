<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Digital\Models\PortalAccount;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-7C — comptes clients du portail : inscription vérifiée par code (anti-usurpation d'email),
 * login refusé sans vérification, « mes achats » multi-vendeurs derrière le token portail.
 */
class PortalAccountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Acct', 'slug' => 'acct-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@acct.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Awa', 'email' => 'awa@client.sn']);
        NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'email', 'provider' => 'log',
            'name' => 'Log', 'is_active' => true, 'is_default' => true,
        ]);
    }

    private function sellDigitalToCustomer(): void
    {
        $ebook = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'EBOOK-' . uniqid(), 'name' => 'Ebook PHP', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
        ]);
        $orders = $this->app->make(OrderService::class);
        $order  = $orders->create(['customer_id' => $this->customer->id, 'items' => [['product_id' => $ebook->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order  = $orders->confirm($order, $this->user->id);
        $orders->fulfill($order, $this->user->id);
    }

    private function registerAndVerify(string $email = 'awa@client.sn', string $password = 'Secret123!'): void
    {
        $this->postJson('/api/portal/register', ['email' => $email, 'password' => $password])->assertOk();
        $code = PortalAccount::where('email', $email)->firstOrFail()->verification_code;
        $this->postJson('/api/portal/verify', ['email' => $email, 'code' => $code, 'password' => $password])->assertOk();
    }

    #[Test]
    public function registration_sends_a_code_via_the_sellers_channel_and_login_is_refused_until_verified(): void
    {
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])
            ->assertOk();

        // Code envoyé via le canal du vendeur qui connaît cet email.
        $out = NotificationOutbox::withoutTenantScope()->where('template_code', 'portal.verify_code')->first();
        $this->assertNotNull($out);
        $this->assertSame('awa@client.sn', $out->recipient);

        // Login AVANT vérification → refusé (anti-usurpation).
        $this->postJson('/api/portal/login', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_wrong_or_expired_code_is_rejected(): void
    {
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])->assertOk();

        $this->postJson('/api/portal/verify', ['email' => 'awa@client.sn', 'code' => '000000', 'password' => 'Secret123!'])
            ->assertStatus(422);

        PortalAccount::where('email', 'awa@client.sn')->update(['verification_expires_at' => now()->subMinute()]);
        $code = PortalAccount::where('email', 'awa@client.sn')->firstOrFail()->verification_code;
        $this->postJson('/api/portal/verify', ['email' => 'awa@client.sn', 'code' => $code, 'password' => 'Secret123!'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_verified_account_logs_in_and_lists_its_purchases_with_magic_links(): void
    {
        $this->sellDigitalToCustomer();
        $this->registerAndVerify();

        $token = $this->postJson('/api/portal/login', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])
            ->assertOk()
            ->json('token');

        $res = $this->getJson('/api/portal/my-purchases', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertSame('Ebook PHP', $res->json('data.0.product_name'));
        $this->assertSame('Acct', $res->json('data.0.seller_name'));
        $this->assertStringContainsString('/portal?token=', $res->json('data.0.portal_link'));
    }

    #[Test]
    public function my_purchases_requires_a_token(): void
    {
        $this->getJson('/api/portal/my-purchases')->assertUnauthorized();
    }

    #[Test]
    public function registering_an_unknown_email_answers_generically_without_sending_a_code(): void
    {
        $this->postJson('/api/portal/register', ['email' => 'inconnu@nulpart.sn', 'password' => 'Secret123!'])
            ->assertOk(); // réponse générique

        $this->assertSame(0, NotificationOutbox::withoutTenantScope()->where('template_code', 'portal.verify_code')->count());
    }
}
