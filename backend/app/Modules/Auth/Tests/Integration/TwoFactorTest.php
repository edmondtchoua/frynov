<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Mail\TwoFactorCodeMail;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-13 F-4 — 2FA par code email : activation, challenge au login, vérification du second facteur.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->tenant = Tenant::create(['name' => 'Tfa', 'slug' => 'tfa-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'Awa', 'email' => 'awa@tfa.sn', 'password' => 'Secret123', 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('admin');
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/login', ['email' => 'awa@tfa.sn', 'password' => 'Secret123']);
    }

    #[Test]
    public function without_2fa_login_returns_a_token_directly(): void
    {
        $this->login()->assertOk()->assertJsonStructure(['token', 'user']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_user_can_enable_two_factor(): void
    {
        $token = $this->user->createToken('api')->plainTextToken;
        $this->postJson('/api/me/2fa', ['enabled' => true], ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('data.two_factor_enabled', true);

        $this->assertTrue((bool) $this->user->fresh()->two_factor_enabled);
    }

    #[Test]
    public function login_with_2fa_enabled_challenges_by_email_without_issuing_a_token(): void
    {
        $this->user->update(['two_factor_enabled' => true]);

        $res = $this->login()->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token');
        $this->assertSame('awa@tfa.sn', $res->json('email'));

        Mail::assertSent(TwoFactorCodeMail::class, fn ($m) => $m->hasTo('awa@tfa.sn'));
        $this->assertDatabaseHas('two_factor_codes', ['email' => 'awa@tfa.sn']);
    }

    #[Test]
    public function a_valid_second_factor_code_delivers_the_token(): void
    {
        $this->user->update(['two_factor_enabled' => true]);
        $this->login();

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        $this->postJson('/api/auth/2fa/verify', ['email' => 'awa@tfa.sn', 'code' => $code])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    #[Test]
    public function a_wrong_second_factor_code_is_rejected(): void
    {
        $this->user->update(['two_factor_enabled' => true]);
        $this->login();

        $this->postJson('/api/auth/2fa/verify', ['email' => 'awa@tfa.sn', 'code' => '000000'])
            ->assertStatus(422);
    }

    #[Test]
    public function disabling_2fa_restores_direct_login(): void
    {
        $this->user->update(['two_factor_enabled' => true]);
        $token = $this->user->createToken('api')->plainTextToken;
        $this->postJson('/api/me/2fa', ['enabled' => false], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->login()->assertOk()->assertJsonStructure(['token', 'user']);
    }
}
