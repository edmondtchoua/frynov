<?php

namespace App\Modules\Sync\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Sync\Models\Sync;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $manager;
    private User $viewer;
    private string $adminToken;
    private string $managerToken;
    private string $viewerToken;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'manager', 'member', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Boutique Sync', 'slug' => 'boutique-sync',
            'plan' => 'starter', 'status' => 'active', 'settings' => [],
        ]);

        $this->admin   = $this->makeUser('admin@sync.sn', 'admin');
        $this->manager = $this->makeUser('manager@sync.sn', 'manager');
        $this->viewer  = $this->makeUser('viewer@sync.sn', 'viewer');

        $this->adminToken   = $this->admin->createToken('api')->plainTextToken;
        $this->managerToken = $this->manager->createToken('api')->plainTextToken;
        $this->viewerToken  = $this->viewer->createToken('api')->plainTextToken;

        // Ensure Spatie team context is set for each test (prevents leakage between tests)
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'name'      => ucfirst($role),
            'email'     => $email,
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
        $user->assignTenantRole($role);

        return $user;
    }

    private function makeSync(?string $tenantId = null): Sync
    {
        return Sync::withoutTenantScope()->create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
        ]);
    }

    // ── Authentication (401) ────────────────────────────────────────────────────

    #[Test]
    public function it_requires_authentication_to_list(): void
    {
        $this->getJson('/api/syncs')->assertUnauthorized();
    }

    #[Test]
    public function it_requires_authentication_to_show(): void
    {
        $sync = $this->makeSync();

        $this->getJson("/api/syncs/{$sync->id}")->assertUnauthorized();
    }

    #[Test]
    public function it_requires_authentication_to_create(): void
    {
        $this->postJson('/api/syncs', [])->assertUnauthorized();
    }

    // ── Read access (any authenticated tenant member) ───────────────────────────

    #[Test]
    public function a_viewer_can_list_syncs(): void
    {
        $this->makeSync();
        $this->makeSync();

        $this->withToken($this->viewerToken)
            ->getJson('/api/syncs')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_shows_a_single_sync(): void
    {
        $sync = $this->makeSync();

        $this->withToken($this->adminToken)
            ->getJson("/api/syncs/{$sync->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $sync->id);
    }

    #[Test]
    public function it_returns_404_for_an_unknown_sync(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/syncs/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    // ── Write access (manager / admin only) ─────────────────────────────────────

    #[Test]
    public function an_admin_can_create_a_sync(): void
    {
        $res = $this->withToken($this->adminToken)
            ->postJson('/api/syncs', [])
            ->assertCreated();

        $this->assertDatabaseHas('syncs', [
            'id'        => $res->json('data.id'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function a_manager_can_create_a_sync(): void
    {
        $this->withToken($this->managerToken)
            ->postJson('/api/syncs', [])
            ->assertCreated();
    }

    #[Test]
    public function a_viewer_cannot_create_a_sync(): void
    {
        $this->withToken($this->viewerToken)
            ->postJson('/api/syncs', [])
            ->assertForbidden();
    }

    #[Test]
    public function a_viewer_cannot_update_a_sync(): void
    {
        $sync = $this->makeSync();

        $this->withToken($this->viewerToken)
            ->putJson("/api/syncs/{$sync->id}", [])
            ->assertForbidden();
    }

    #[Test]
    public function a_viewer_cannot_delete_a_sync(): void
    {
        $sync = $this->makeSync();

        $this->withToken($this->viewerToken)
            ->deleteJson("/api/syncs/{$sync->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('syncs', ['id' => $sync->id, 'deleted_at' => null]);
    }

    #[Test]
    public function an_admin_can_update_a_sync(): void
    {
        $sync = $this->makeSync();

        $this->withToken($this->adminToken)
            ->putJson("/api/syncs/{$sync->id}", [])
            ->assertOk()
            ->assertJsonPath('data.id', $sync->id);
    }

    #[Test]
    public function an_admin_can_delete_a_sync(): void
    {
        $sync = $this->makeSync();

        $this->withToken($this->adminToken)
            ->deleteJson("/api/syncs/{$sync->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('syncs', ['id' => $sync->id]);
    }

    // ── Multitenant isolation (OWASP API4 — BOLA/IDOR) ──────────────────────────

    #[Test]
    public function the_list_never_leaks_another_tenants_syncs(): void
    {
        $other = Tenant::create([
            'name' => 'Autre', 'slug' => 'autre-tenant',
            'plan' => 'starter', 'status' => 'active', 'settings' => [],
        ]);

        $this->makeSync();               // own tenant
        $this->makeSync($other->id);     // other tenant — must NEVER appear
        $this->makeSync($other->id);

        $res = $this->withToken($this->adminToken)->getJson('/api/syncs')->assertOk();

        $ids       = collect($res->json('data'))->pluck('id')->all();
        $tenantIds = Sync::withoutTenantScope()->whereIn('id', $ids)->pluck('tenant_id')->unique()->values()->all();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame([$this->tenant->id], $tenantIds);
    }

    #[Test]
    public function a_tenant_gets_404_reading_a_sync_of_another_tenant(): void
    {
        $other = Tenant::create([
            'name' => 'Autre', 'slug' => 'autre-tenant-2',
            'plan' => 'starter', 'status' => 'active', 'settings' => [],
        ]);
        $syncB = $this->makeSync($other->id);

        $this->withToken($this->adminToken)
            ->getJson("/api/syncs/{$syncB->id}")
            ->assertNotFound(); // 404, not 403 — never confirm the resource exists
    }

    #[Test]
    public function a_tenant_cannot_delete_a_sync_of_another_tenant(): void
    {
        $other = Tenant::create([
            'name' => 'Autre', 'slug' => 'autre-tenant-3',
            'plan' => 'starter', 'status' => 'active', 'settings' => [],
        ]);
        $syncB = $this->makeSync($other->id);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/syncs/{$syncB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('syncs', ['id' => $syncB->id, 'deleted_at' => null]);
    }
}
