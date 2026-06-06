<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Models\TemporaryAccessGrant;
use App\Modules\Auth\Services\TemporaryAccessService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RBAC Phase C — temporary access: a member gets a role until an expiry, then it is
 * revoked automatically (scheduler) with no manual action.
 */
class TemporaryAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $member;
    private string $adminToken;
    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'manager', 'member', 'viewer', 'cashier', 'agent', 'commercial', 'delivery'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'temp-access', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        $this->admin = User::create(['name' => 'A', 'email' => 'a@temp.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->adminToken = $this->admin->createToken('api')->plainTextToken;

        $this->member = User::create(['name' => 'M', 'email' => 'm@temp.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->member->assignTenantRole('member');
        $this->memberToken = $this->member->createToken('api')->plainTextToken;
    }

    #[Test]
    public function a_manager_grants_temporary_access_and_the_role_takes_effect(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/workspace/users/{$this->member->id}/temporary-access", [
                'role'       => 'manager',
                'expires_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertCreated();

        $this->assertTrue($this->member->fresh()->hasTenantRole('manager'), 'temp role must take effect');
        $this->assertDatabaseHas('temporary_access_grants', ['user_id' => $this->member->id, 'role' => 'manager', 'revoked_at' => null]);
    }

    #[Test]
    public function expired_grants_are_revoked_automatically_by_the_service(): void
    {
        $grant = app(TemporaryAccessService::class)->grant($this->member, 'manager', now()->addDay(), $this->admin);
        $this->assertTrue($this->member->fresh()->hasTenantRole('manager'));

        // Simulate the deadline passing, then run the auto-revocation.
        TemporaryAccessGrant::whereKey($grant->id)->update(['expires_at' => now()->subMinute()]);
        $revoked = app(TemporaryAccessService::class)->revokeExpired();

        $this->assertSame(1, $revoked);
        $this->assertFalse($this->member->fresh()->hasTenantRole('manager'), 'expired role must be removed');
        $this->assertNotNull($grant->fresh()->revoked_at);
    }

    #[Test]
    public function the_scheduled_command_revokes_expired_grants(): void
    {
        $grant = app(TemporaryAccessService::class)->grant($this->member, 'cashier', now()->addDay(), $this->admin);
        TemporaryAccessGrant::whereKey($grant->id)->update(['expires_at' => now()->subMinute()]);

        Artisan::call('access:revoke-expired');

        $this->assertFalse($this->member->fresh()->hasTenantRole('cashier'));
    }

    #[Test]
    public function the_admin_role_cannot_be_temporarily_granted(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/workspace/users/{$this->member->id}/temporary-access", ['role' => 'admin', 'expires_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    #[Test]
    public function a_regular_member_cannot_grant_temporary_access(): void
    {
        $this->withToken($this->memberToken)
            ->postJson("/api/workspace/users/{$this->admin->id}/temporary-access", ['role' => 'manager', 'expires_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(403);
    }

    #[Test]
    public function granting_a_role_the_member_already_has_is_rejected(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/workspace/users/{$this->member->id}/temporary-access", ['role' => 'member', 'expires_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(422);
    }
}
