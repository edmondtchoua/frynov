<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Customers\Models\Customer;
use App\Modules\Digital\Models\PortalAccount;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Recette QA Phase 3 — durcissement des comptes portail :
 *  - un token portail ne peut servir QUE `api/portal/*` (SEC-1) ;
 *  - la vérification exige le mot de passe (anti pré-hijack, AR-1) ;
 *  - code borné en tentatives (SEC-4) et non re-spammé (SEC-3).
 */
class PortalSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Sec', 'slug' => 'sec-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Awa', 'email' => 'awa@client.sn']);
        NotificationChannel::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'email', 'provider' => 'log',
            'name' => 'Log', 'is_active' => true, 'is_default' => true,
        ]);
    }

    private function verifiedAccount(string $email = 'awa@client.sn', string $password = 'Secret123!'): PortalAccount
    {
        $this->postJson('/api/portal/register', ['email' => $email, 'password' => $password])->assertOk();
        $code = PortalAccount::where('email', $email)->firstOrFail()->verification_code;
        $this->postJson('/api/portal/verify', ['email' => $email, 'code' => $code, 'password' => $password])->assertOk();

        return PortalAccount::where('email', $email)->firstOrFail();
    }

    private function portalToken(string $email = 'awa@client.sn', string $password = 'Secret123!'): string
    {
        return $this->postJson('/api/portal/login', ['email' => $email, 'password' => $password])->assertOk()->json('token');
    }

    #[Test]
    public function a_portal_token_cannot_change_its_email_via_the_app_profile_route(): void
    {
        // SEC-1 (CRITIQUE) : le token portail ne doit atteindre aucune route hors `api/portal/*`.
        $this->verifiedAccount();
        $token = $this->portalToken();

        $this->patchJson('/api/me/profile', ['email' => 'victime@autre.sn'], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403);

        // L'email du compte est inchangé.
        $this->assertSame('awa@client.sn', PortalAccount::first()->email);
    }

    #[Test]
    public function a_portal_token_is_rejected_on_other_authenticated_app_routes(): void
    {
        $this->verifiedAccount();
        $token = $this->portalToken();

        // Une route tenant classique (auth:sanctum + tenant) et une route auth-sans-tenant.
        $this->getJson('/api/notifications/credits', ['Authorization' => "Bearer {$token}"])->assertStatus(403);
        $this->getJson('/api/me/sessions', ['Authorization' => "Bearer {$token}"])->assertStatus(403);

        // Mais la route portail légitime fonctionne.
        $this->getJson('/api/portal/my-purchases', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    #[Test]
    public function verification_requires_the_password_that_was_set_blocking_pre_hijack(): void
    {
        // AR-1 : la victime s'inscrit (mdp V) ; l'attaquant re-`register` (mdp A) — mais le code va
        // à l'email de la victime. Sans le bon mot de passe, la vérification échoue.
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'VictimPass1!'])->assertOk();

        // Deuxième register : compte non vérifié avec code encore valide → mot de passe mis à jour,
        // MAIS aucun nouveau code (anti-bombardement) → le code initial reste le seul valable.
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Attacker99!'])->assertOk();
        $this->assertSame(1, NotificationOutbox::withoutTenantScope()->where('template_code', 'portal.verify_code')->count());

        $code = PortalAccount::where('email', 'awa@client.sn')->firstOrFail()->verification_code;

        // Vérifier avec un mauvais mot de passe échoue même avec le bon code.
        $this->postJson('/api/portal/verify', ['email' => 'awa@client.sn', 'code' => $code, 'password' => 'WrongGuess1!'])
            ->assertStatus(422);
        $this->assertFalse(PortalAccount::where('email', 'awa@client.sn')->firstOrFail()->isVerified());
    }

    #[Test]
    public function the_verification_code_is_burned_after_too_many_attempts(): void
    {
        // SEC-4 : au-delà du seuil, le code est invalidé (force une nouvelle inscription). On isole
        // la logique de blocage au niveau COMPTE du throttle HTTP de la route (testé par ailleurs).
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])->assertOk();
        $code = PortalAccount::where('email', 'awa@client.sn')->firstOrFail()->verification_code;

        // MAX_VERIFY_ATTEMPTS mauvaises tentatives (dans la limite du throttle 5/10min) brûlent le code.
        for ($i = 0; $i < PortalAccount::MAX_VERIFY_ATTEMPTS; $i++) {
            $this->postJson('/api/portal/verify', ['email' => 'awa@client.sn', 'code' => '000000', 'password' => 'Secret123!'])
                ->assertStatus(422);
        }

        // Le code a été invalidé au niveau du compte (le bon code ne servirait plus).
        $account = PortalAccount::where('email', 'awa@client.sn')->firstOrFail();
        $this->assertNull($account->verification_code);
        $this->assertSame(PortalAccount::MAX_VERIFY_ATTEMPTS, $account->verification_attempts);
        $this->assertNotSame('', (string) $code); // le code initial existait bien
    }

    #[Test]
    public function repeated_registration_does_not_re_send_the_code_while_it_is_still_valid(): void
    {
        // SEC-3 : anti-bombardement d'email / drain de crédits.
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])->assertOk();
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])->assertOk();
        $this->postJson('/api/portal/register', ['email' => 'awa@client.sn', 'password' => 'Secret123!'])->assertOk();

        $this->assertSame(1, NotificationOutbox::withoutTenantScope()->where('template_code', 'portal.verify_code')->count());
    }
}
