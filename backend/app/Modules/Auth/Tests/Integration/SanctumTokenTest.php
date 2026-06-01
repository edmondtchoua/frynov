<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression suite for the Sanctum tokenable_id UUID bug.
 *
 * Root cause: personal_access_tokens used morphs() → BIGINT tokenable_id.
 * Fix: changed to uuidMorphs() → CHAR(36) tokenable_id.
 *
 * Every test here exercises a real User::createToken() call so the bug
 * would re-surface immediately if the migration reverts.
 */
class SanctumTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member',  'guard_name' => 'web']);

        Plan::firstOrCreate(
            ['code' => Plan::CODE_STARTER],
            [
                'name'                => 'Starter',
                'price_monthly_cents' => 0,
                'price_yearly_cents'  => 0,
                'currency'            => 'XOF',
                'trial_days'          => 14,
                'is_active'           => true,
                'is_public'           => true,
                'sort_order'          => 1,
            ]
        );
    }

    // ── Bug regression ────────────────────────────────────────────────────────

    #[Test]
    public function token_is_persisted_without_truncation_when_user_has_uuid(): void
    {
        // The bug: tokenable_id BIGINT ← UUID string → MySQL truncation error
        $tenant = $this->createTenant();
        $user   = $this->createUser($tenant);

        // Must not throw QueryException (Data truncated for column 'tokenable_id')
        $tokenResult = $user->createToken('api', ['*'], now()->addDays(30));

        $this->assertNotEmpty($tokenResult->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => User::class,
            'name'           => 'api',
        ]);
    }

    #[Test]
    public function tokenable_id_column_stores_full_uuid_without_loss(): void
    {
        $tenant = $this->createTenant();
        $user   = $this->createUser($tenant);

        $user->createToken('api');

        $row = \DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->first();

        // If the column was BIGINT the UUID would be truncated to '0' or a number
        $this->assertSame($user->id, $row->tokenable_id);
    }

    // ── Registration flow (end-to-end token creation) ─────────────────────────

    #[Test]
    public function registration_returns_usable_bearer_token(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'company_name'          => 'Boutique Dakar',
            'name'                  => 'Fatou Diallo',
            'email'                 => 'fatou@boutique-dakar.sn',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $registerResponse->assertStatus(201);
        $token = $registerResponse->json('token');
        $this->assertNotEmpty($token);

        // The returned token must work immediately for authenticated calls
        $meResponse = $this->withToken($token)->getJson('/api/auth/me');
        $meResponse->assertOk()
            ->assertJsonPath('user.email', 'fatou@boutique-dakar.sn');
    }

    #[Test]
    public function token_is_stored_in_personal_access_tokens_after_registration(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name'          => 'Shop CI',
            'name'                  => 'Kouamé Brou',
            'email'                 => 'kouame@shop.ci',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertStatus(201);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $row = \DB::table('personal_access_tokens')->first();
        // UUID format: 8-4-4-4-12 hex characters
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $row->tokenable_id,
        );
    }

    // ── Login token creation ──────────────────────────────────────────────────

    #[Test]
    public function login_creates_token_without_truncation(): void
    {
        $tenant = $this->createTenant();
        $this->createUser($tenant, 'mamadou@test.sn', 'Secret123!');

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'mamadou@test.sn',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function refresh_replaces_old_token_without_truncation(): void
    {
        $tenant = $this->createTenant();
        $user   = $this->createUser($tenant);
        $token  = $user->createToken('api', ['*'], now()->addDays(30))->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withToken($token)->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token']);

        // Old token deleted, new token created — count stays at 1
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $newToken = $response->json('token');
        $this->assertNotSame($token, $newToken);

        // New token works
        $this->withToken($newToken)->getJson('/api/auth/me')->assertOk();
    }

    #[Test]
    public function logout_deletes_token_row(): void
    {
        $tenant = $this->createTenant();
        $user   = $this->createUser($tenant);
        $token  = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createTenant(string $name = 'Test Tenant'): Tenant
    {
        return Tenant::create([
            'name'     => $name,
            'slug'     => \Str::slug($name) . '-' . uniqid(),
            'plan'     => 'starter',
            'status'   => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Abidjan', 'locale' => 'fr'],
        ]);
    }

    private function createUser(
        Tenant $tenant,
        string $email    = 'user@test.sn',
        string $password = 'Secret123!',
    ): User {
        $user = User::create([
            'name'      => 'Test User',
            'email'     => $email,
            'password'  => bcrypt($password),
            'tenant_id' => $tenant->id,
        ]);
        $user->assignTenantRole('admin');

        return $user;
    }
}
