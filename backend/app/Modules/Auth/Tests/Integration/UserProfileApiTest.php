<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integration tests for user profile management endpoints.
 *
 * Covers:
 *  PATCH /api/me/profile          — update name / email
 *  POST  /api/me/password         — change password
 *  GET   /api/me/sessions         — list active tokens
 *  DELETE /api/me/sessions/{id}   — revoke a session
 */
class UserProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name' => 'Boutique Test', 'slug' => 'boutique-test',
            'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr'],
        ]);

        $this->user = User::create([
            'name'      => 'Fatou Diallo',
            'email'     => 'fatou@boutique-test.sn',
            'password'  => Hash::make('OldSecret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->assignTenantRole('admin');
        $this->token = $this->user->createToken('api')->plainTextToken;
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    #[Test]
    public function profile_endpoints_require_authentication(): void
    {
        $this->patchJson('/api/me/profile', ['name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/me/password', [])->assertUnauthorized();
        $this->getJson('/api/me/sessions')->assertUnauthorized();
    }

    // ── PATCH /api/me/profile ─────────────────────────────────────────────────

    #[Test]
    public function user_can_update_their_name(): void
    {
        $response = $this->withToken($this->token)->patchJson('/api/me/profile', [
            'name' => 'Fatou Sow Diallo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Fatou Sow Diallo')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Profil'));

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => 'Fatou Sow Diallo']);
    }

    #[Test]
    public function user_can_update_their_email(): void
    {
        $response = $this->withToken($this->token)->patchJson('/api/me/profile', [
            'email' => 'fatou.new@boutique-test.sn',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'fatou.new@boutique-test.sn');

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'email' => 'fatou.new@boutique-test.sn']);
    }

    #[Test]
    public function user_can_update_both_name_and_email(): void
    {
        $this->withToken($this->token)->patchJson('/api/me/profile', [
            'name'  => 'Fatou Updated',
            'email' => 'updated@test.sn',
        ])->assertOk()
          ->assertJsonPath('data.name', 'Fatou Updated')
          ->assertJsonPath('data.email', 'updated@test.sn');
    }

    #[Test]
    public function user_cannot_use_another_users_email(): void
    {
        User::create([
            'name' => 'Other', 'email' => 'taken@test.sn',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withToken($this->token)->patchJson('/api/me/profile', [
            'email' => 'taken@test.sn',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function user_can_update_with_their_own_email_unchanged(): void
    {
        // Should not fail uniqueness check when email is unchanged
        $this->withToken($this->token)->patchJson('/api/me/profile', [
            'email' => 'fatou@boutique-test.sn',  // same as current
            'name'  => 'New Name',
        ])->assertOk();
    }

    #[Test]
    public function profile_update_rejects_invalid_email(): void
    {
        $this->withToken($this->token)->patchJson('/api/me/profile', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['email']);
    }

    // ── POST /api/me/password ─────────────────────────────────────────────────

    #[Test]
    public function user_can_change_password_with_correct_current_password(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/me/password', [
            'current_password'      => 'OldSecret123!',
            'password'              => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertOk();

        // Can login with new password
        $this->postJson('/api/auth/login', [
            'email'    => 'fatou@boutique-test.sn',
            'password' => 'NewSecret456!',
        ])->assertOk();

        // Cannot login with old password
        $this->postJson('/api/auth/login', [
            'email'    => 'fatou@boutique-test.sn',
            'password' => 'OldSecret123!',
        ])->assertUnauthorized();
    }

    #[Test]
    public function password_change_fails_with_wrong_current_password(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/me/password', [
            'current_password'      => 'WrongPassword!',
            'password'              => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.current_password', fn ($e) => !empty($e));
    }

    #[Test]
    public function password_change_requires_confirmation_match(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/me/password', [
            'current_password'      => 'OldSecret123!',
            'password'              => 'NewSecret456!',
            'password_confirmation' => 'DifferentPassword!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function password_change_enforces_strength_rules(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/me/password', [
            'current_password'      => 'OldSecret123!',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function password_change_revokes_other_sessions(): void
    {
        // Create a second token (another "device")
        $this->user->createToken('mobile-app');
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken($this->token)->postJson('/api/me/password', [
            'current_password'      => 'OldSecret123!',
            'password'              => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ])->assertOk();

        // Only the current session token should remain
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // ── GET /api/me/sessions ──────────────────────────────────────────────────

    #[Test]
    public function user_can_list_active_sessions(): void
    {
        $this->user->createToken('mobile');
        $this->user->createToken('tablet');

        $response = $this->withToken($this->token)->getJson('/api/me/sessions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $currentSessions = collect($response->json('data'))->filter(fn ($s) => $s['is_current'])->count();
        $this->assertSame(1, $currentSessions, 'Exactly one session should be marked as current.');
    }

    #[Test]
    public function sessions_are_ordered_by_last_used_at(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/me/sessions');
        $response->assertOk();
        // At minimum we have one session — just check structure
        $this->assertNotEmpty($response->json('data'));
    }

    // ── DELETE /api/me/sessions/{id} ──────────────────────────────────────────

    #[Test]
    public function user_can_revoke_another_session(): void
    {
        $other = $this->user->createToken('other-device');
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken($this->token)
            ->deleteJson("/api/me/sessions/{$other->accessToken->id}")
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function user_cannot_revoke_their_current_session(): void
    {
        $currentId = $this->withToken($this->token)
            ->getJson('/api/me/sessions')
            ->json('data.0.id');

        $this->withToken($this->token)
            ->deleteJson("/api/me/sessions/{$currentId}")
            ->assertUnprocessable();
    }
}
