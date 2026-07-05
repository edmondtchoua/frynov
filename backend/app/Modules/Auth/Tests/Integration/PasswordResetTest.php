<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Mail\PasswordResetCodeMail;
use App\Modules\Auth\Services\PasswordResetService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-10 F-3 — réinitialisation de mot de passe par code email.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = Tenant::create(['name' => 'Rst', 'slug' => 'rst-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'Awa', 'email' => 'awa@rst.sn', 'password' => 'OldPass123', 'tenant_id' => $this->tenant->id]);
    }

    /** Le code en clair n'est pas stocké — on le récupère via l'email capturé. */
    private function requestAndCaptureCode(string $email): string
    {
        $this->postJson('/api/auth/forgot-password', ['email' => $email])->assertOk();

        $captured = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $m) use (&$captured, $email) {
            $captured = $m->code;

            return $m->hasTo($email);
        });

        return (string) $captured;
    }

    #[Test]
    public function forgot_password_emails_a_code_for_a_known_user(): void
    {
        $code = $this->requestAndCaptureCode('awa@rst.sn');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseHas('password_reset_codes', ['email' => 'awa@rst.sn']);
    }

    #[Test]
    public function forgot_password_is_generic_and_sends_nothing_for_an_unknown_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'inconnu@nulpart.sn'])->assertOk();
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_codes', ['email' => 'inconnu@nulpart.sn']);
    }

    #[Test]
    public function a_valid_code_resets_the_password_and_revokes_sessions(): void
    {
        $oldToken = $this->user->createToken('api')->plainTextToken;
        $code = $this->requestAndCaptureCode('awa@rst.sn');

        $this->postJson('/api/auth/reset-password', [
            'email' => 'awa@rst.sn', 'code' => $code,
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertOk();

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewPass123', $this->user->password));
        $this->assertSame(0, $this->user->tokens()->count());                 // sessions révoquées
        $this->assertDatabaseMissing('password_reset_codes', ['email' => 'awa@rst.sn']); // code purgé

        // L'ancien token ne s'authentifie plus.
        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$oldToken}"])->assertUnauthorized();
    }

    #[Test]
    public function a_wrong_code_is_rejected_and_counts_as_an_attempt(): void
    {
        $this->requestAndCaptureCode('awa@rst.sn');

        $this->postJson('/api/auth/reset-password', [
            'email' => 'awa@rst.sn', 'code' => '000000',
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertStatus(422);

        $this->assertSame(1, (int) DB::table('password_reset_codes')->where('email', 'awa@rst.sn')->value('attempts'));
        $this->user->refresh();
        $this->assertTrue(Hash::check('OldPass123', $this->user->password)); // inchangé
    }

    #[Test]
    public function an_expired_code_is_rejected(): void
    {
        $code = $this->requestAndCaptureCode('awa@rst.sn');
        DB::table('password_reset_codes')->where('email', 'awa@rst.sn')->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'awa@rst.sn', 'code' => $code,
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertStatus(422);
    }

    #[Test]
    public function the_code_is_burned_after_too_many_attempts(): void
    {
        $code = $this->requestAndCaptureCode('awa@rst.sn');
        $svc = $this->app->make(PasswordResetService::class);

        for ($i = 0; $i < PasswordResetService::MAX_ATTEMPTS; $i++) {
            $this->assertFalse($svc->reset('awa@rst.sn', '000000', 'NewPass123'));
        }
        // Même le bon code ne passe plus (seuil atteint).
        $this->assertFalse($svc->reset('awa@rst.sn', $code, 'NewPass123'));
    }
}
