<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integration tests for /api/workspace/* endpoints.
 *
 * Covers:
 *  - GET  /api/workspace/users   (list)
 *  - POST /api/workspace/users   (invite)
 *  - PATCH /api/workspace/users/{id} (update role/name)
 *  - DELETE /api/workspace/users/{id} (toggle active)
 *  - GET  /api/workspace/settings
 *  - PATCH /api/workspace/settings
 */
class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer',  'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name'     => 'Boutique Test',
            'slug'     => 'boutique-test',
            'plan'     => 'starter',
            'status'   => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr'],
        ]);

        $this->admin = User::create([
            'name'      => 'Admin User',
            'email'     => 'admin@test.sn',
            'password'  => bcrypt('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignTenantRole('admin');
        $this->token = $this->admin->createToken('api')->plainTextToken;
    }

    // ── POST /api/workspace/provision (onboarding) ────────────────────────────

    #[Test]
    public function onboarding_persists_the_selected_currency(): void
    {
        // The onboarding wizard collects & validates a currency. It must be saved to
        // tenant settings — orders/invoices read settings['currency']. It was dropped.
        $response = $this->withToken($this->token)->postJson('/api/workspace/provision', [
            'company_name' => 'Ma Boutique Douala',
            'country'      => 'CM',
            'currency'     => 'XAF',
            'needs_stock'     => true,
            'needs_pos'       => false,
            'needs_delivery'  => false,
            'needs_ecommerce' => false,
            'needs_offline'   => false,
        ]);

        $response->assertOk();
        $this->tenant->refresh();
        $this->assertSame('XAF', $this->tenant->settings['currency']);
        $this->assertSame('CM',  $this->tenant->settings['country']);
        $this->assertTrue((bool) $this->tenant->onboarded);
    }

    // ── GET /api/workspace/users ──────────────────────────────────────────────

    #[Test]
    public function it_lists_users_of_own_tenant(): void
    {
        // Create a second user in the same tenant
        $member = User::create([
            'name'      => 'Modou Fall',
            'email'     => 'modou@test.sn',
            'password'  => bcrypt('pass'),
            'tenant_id' => $this->tenant->id,
        ]);
        $member->assignTenantRole('member');

        // Create user in a DIFFERENT tenant — must not appear
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        User::create(['name' => 'Outsider', 'email' => 'out@other.sn', 'password' => bcrypt('x'), 'tenant_id' => $other->id]);

        $response = $this->withToken($this->token)->getJson('/api/workspace/users');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.email', 'admin@test.sn')
            ->assertJsonPath('data.1.email', 'modou@test.sn');
    }

    #[Test]
    public function it_includes_inactive_users_in_list(): void
    {
        $user = User::create(['name' => 'To Deactivate', 'email' => 'deac@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->delete(); // soft delete

        $response = $this->withToken($this->token)->getJson('/api/workspace/users');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $inactive = collect($response->json('data'))->firstWhere('email', 'deac@test.sn');
        $this->assertFalse($inactive['is_active']);
    }

    #[Test]
    public function it_requires_authentication_to_list_users(): void
    {
        $this->getJson('/api/workspace/users')->assertUnauthorized();
    }

    // ── POST /api/workspace/users ─────────────────────────────────────────────

    #[Test]
    public function admin_can_invite_a_new_member(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/workspace/users', [
            'name'  => 'Aïssatou Mbaye',
            'email' => 'aissatou@test.sn',
            'role'  => 'member',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'is_active'], 'temp_password', 'message'])
            ->assertJsonPath('data.email', 'aissatou@test.sn')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', ['email' => 'aissatou@test.sn', 'tenant_id' => $this->tenant->id]);

        $newUser = User::where('email', 'aissatou@test.sn')->firstOrFail();
        $this->assertTrue($newUser->hasRole('member'));
    }

    #[Test]
    public function invite_returns_a_non_empty_temp_password(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/workspace/users', [
            'name'  => 'Temp Pass User',
            'email' => 'tmp@test.sn',
            'role'  => 'viewer',
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('temp_password'));
        $this->assertGreaterThanOrEqual(10, strlen($response->json('temp_password')));
    }

    #[Test]
    public function invite_rejects_duplicate_email(): void
    {
        User::create(['name' => 'Existing', 'email' => 'existing@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)->postJson('/api/workspace/users', [
            'name'  => 'New Person',
            'email' => 'existing@test.sn',
            'role'  => 'member',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function invite_rejects_invalid_role(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/workspace/users', [
            'name'  => 'Someone',
            'email' => 'someone@test.sn',
            'role'  => 'super-villain',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function member_cannot_invite_other_users(): void
    {
        $member = User::create(['name' => 'Plain Member', 'email' => 'member@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $member->assignTenantRole('member');
        $memberToken = $member->createToken('api')->plainTextToken;

        $response = $this->withToken($memberToken)->postJson('/api/workspace/users', [
            'name'  => 'Intruder',
            'email' => 'intruder@test.sn',
            'role'  => 'member',
        ]);

        $response->assertForbidden();
    }

    // ── PATCH /api/workspace/users/{id} ──────────────────────────────────────

    #[Test]
    public function admin_can_change_role_of_member(): void
    {
        $user = User::create(['name' => 'Demba Diop', 'email' => 'demba@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole('member');

        $response = $this->withToken($this->token)->patchJson("/api/workspace/users/{$user->id}", [
            'role' => 'manager',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.roles.0', 'manager');

        $this->assertTrue($user->fresh()->hasRole('manager'));
    }

    #[Test]
    public function cannot_demote_the_only_admin(): void
    {
        // admin is the only admin in the tenant
        $response = $this->withToken($this->token)->patchJson("/api/workspace/users/{$this->admin->id}", [
            'role' => 'member',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'dernier administrateur'));
    }

    #[Test]
    public function admin_can_update_user_name(): void
    {
        $user = User::create(['name' => 'Old Name', 'email' => 'name@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole('member');

        $response = $this->withToken($this->token)->patchJson("/api/workspace/users/{$user->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    #[Test]
    public function cannot_update_user_from_another_tenant(): void
    {
        $other   = Tenant::create(['name' => 'Other', 'slug' => 'other2', 'plan' => 'starter', 'status' => 'active']);
        $foreign = User::create(['name' => 'Foreign', 'email' => 'for@other.sn', 'password' => bcrypt('x'), 'tenant_id' => $other->id]);

        $response = $this->withToken($this->token)->patchJson("/api/workspace/users/{$foreign->id}", [
            'role' => 'member',
        ]);

        $response->assertNotFound();
    }

    // ── DELETE /api/workspace/users/{id} (toggle) ─────────────────────────────

    #[Test]
    public function admin_can_deactivate_a_member(): void
    {
        $user = User::create(['name' => 'Active User', 'email' => 'active@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole('member');

        $response = $this->withToken($this->token)->deleteJson("/api/workspace/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function admin_can_reactivate_a_deactivated_user(): void
    {
        $user = User::create(['name' => 'Inactive', 'email' => 'inactive@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole('member');
        $user->delete();

        $response = $this->withToken($this->token)->deleteJson("/api/workspace/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function admin_cannot_deactivate_themselves(): void
    {
        $response = $this->withToken($this->token)->deleteJson("/api/workspace/users/{$this->admin->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'votre propre statut'));
    }

    #[Test]
    public function cannot_deactivate_the_last_admin(): void
    {
        $other = User::create(['name' => 'Second Admin', 'email' => 'admin2@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $other->assignTenantRole('admin');
        $otherToken = $other->createToken('api')->plainTextToken;

        // admin2 tries to deactivate admin (the last other admin - there are now 2 admins)
        // Let's deactivate admin2 via admin's token — succeeds (2 admins)
        $response = $this->withToken($this->token)->deleteJson("/api/workspace/users/{$other->id}");
        $response->assertOk();

        // Now admin is the only one — cannot deactivate themselves
        $response = $this->withToken($this->token)->deleteJson("/api/workspace/users/{$this->admin->id}");
        $response->assertUnprocessable();
    }

    // ── GET /api/workspace/settings ──────────────────────────────────────────

    #[Test]
    public function it_returns_tenant_settings(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/workspace/settings');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'domain', 'settings']])
            ->assertJsonPath('data.name', 'Boutique Test')
            ->assertJsonPath('data.settings.currency', 'XOF');
    }

    #[Test]
    public function it_requires_auth_to_get_settings(): void
    {
        $this->getJson('/api/workspace/settings')->assertUnauthorized();
    }

    // ── PATCH /api/workspace/settings ────────────────────────────────────────

    #[Test]
    public function admin_can_update_company_name(): void
    {
        $response = $this->withToken($this->token)->patchJson('/api/workspace/settings', [
            'name' => 'Boutique Dakar Premium',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Boutique Dakar Premium');

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'name' => 'Boutique Dakar Premium']);
    }

    #[Test]
    public function admin_can_update_settings_json(): void
    {
        $response = $this->withToken($this->token)->patchJson('/api/workspace/settings', [
            'settings' => [
                'country'  => 'SN',
                'phone'    => '+221 77 000 00 00',
                'address'  => '123 Avenue Bourguiba, Dakar',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.settings.country', 'SN')
            ->assertJsonPath('data.settings.phone', '+221 77 000 00 00');

        // Existing settings are preserved (merged, not replaced)
        $tenant = $this->tenant->fresh();
        $this->assertSame('XOF', $tenant->settings['currency']);
        $this->assertSame('SN',  $tenant->settings['country']);
    }

    #[Test]
    public function settings_merge_preserves_existing_keys(): void
    {
        $this->withToken($this->token)->patchJson('/api/workspace/settings', [
            'settings' => ['country' => 'CI'],
        ]);

        // 'currency' was set in setUp → should still be 'XOF' after the update
        $this->assertSame('XOF', $this->tenant->fresh()->settings['currency']);
        $this->assertSame('CI',  $this->tenant->fresh()->settings['country']);
    }

    #[Test]
    public function member_cannot_update_company_settings(): void
    {
        $member = User::create(['name' => 'Member', 'email' => 'mem@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $member->assignTenantRole('member');
        $memberToken = $member->createToken('api')->plainTextToken;

        $response = $this->withToken($memberToken)->patchJson('/api/workspace/settings', [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function duplicate_domain_is_rejected(): void
    {
        // Another tenant with the same domain
        Tenant::create(['name' => 'Other', 'slug' => 'other3', 'domain' => 'taken.com', 'plan' => 'starter', 'status' => 'active']);

        $response = $this->withToken($this->token)->patchJson('/api/workspace/settings', [
            'domain' => 'taken.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    }

    #[Test]
    public function empty_domain_is_stored_as_null(): void
    {
        $response = $this->withToken($this->token)->patchJson('/api/workspace/settings', [
            'domain' => '',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.domain', null);

        $this->assertNull($this->tenant->fresh()->domain);
    }
}
