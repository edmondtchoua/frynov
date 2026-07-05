<?php

namespace App\Modules\Security\Tests;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Security tests: Audit Trail completeness, immutability, and integrity.
 *
 * Verifies:
 *   - Critical actions generate mandatory audit log entries
 *   - Audit entries cannot be updated or deleted (non-repudiation)
 *   - Integrity hash is computed and stored on creation
 *   - IP address and user-agent are captured
 */
class AuditTrailTest extends TestCase
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

        Plan::firstOrCreate(['code' => Plan::CODE_STARTER], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Audit Test', 'slug' => 'audit-test', 'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr'],
        ]);

        $this->admin = User::create([
            'name' => 'Admin Audit', 'email' => 'admin@audit-test.sn',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignTenantRole('admin');
        $this->token = $this->admin->createToken('api')->plainTextToken;
    }

    // ── Immutability (non-répudiation) ────────────────────────────────────────

    #[Test]
    public function audit_log_cannot_be_updated_after_creation(): void
    {
        $log = AuditLog::create([
            'action'    => 'test.event',
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->admin->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/immutable/i');

        $log->update(['action' => 'tampered.event']);
    }

    #[Test]
    public function audit_log_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'action'    => 'test.delete_attempt',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot be deleted/i');

        $log->delete();
    }

    #[Test]
    public function audit_log_has_no_updated_at_field(): void
    {
        // UPDATED_AT = null means Laravel does not manage this field
        $this->assertNull(AuditLog::UPDATED_AT);

        $log = AuditLog::create(['action' => 'test', 'tenant_id' => $this->tenant->id]);
        $this->assertArrayNotHasKey('updated_at', $log->toArray());
    }

    // ── Integrity hash ────────────────────────────────────────────────────────

    #[Test]
    public function audit_log_has_integrity_hash_computed_on_creation(): void
    {
        $log = AuditLog::create([
            'action'    => 'test.hash',
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->admin->id,
            'new_values'=> ['field' => 'value'],
        ]);

        $this->assertNotNull($log->integrity_hash, 'integrity_hash must be set on creation');
        $this->assertSame(64, strlen($log->integrity_hash), 'SHA-256 hex = 64 chars');
    }

    #[Test]
    public function each_audit_log_has_unique_hash(): void
    {
        $log1 = AuditLog::create(['action' => 'event.1', 'tenant_id' => $this->tenant->id]);
        $log2 = AuditLog::create(['action' => 'event.2', 'tenant_id' => $this->tenant->id]);

        $this->assertNotSame($log1->integrity_hash, $log2->integrity_hash,
            'Each entry must have a unique hash (chaining ensures this)');
    }

    // ── Authentication events ─────────────────────────────────────────────────

    #[Test]
    public function successful_login_creates_audit_entry(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'admin@audit-test.sn',
            'password' => 'Secret123!',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'auth.login',
            'user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function audit_login_entry_captures_ip_address(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'admin@audit-test.sn',
            'password' => 'Secret123!',
        ], ['X-Forwarded-For' => '203.0.113.42']);

        $log = AuditLog::where('action', 'auth.login')
            ->where('user_id', $this->admin->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address, 'IP address must be captured in audit log');
    }

    #[Test]
    public function logout_creates_audit_entry(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'auth.logout',
            'user_id' => $this->admin->id,
        ]);
    }

    // ── Registration audit ────────────────────────────────────────────────────

    #[Test]
    public function registration_creates_audit_entry(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name'          => 'New Company',
            'name'                  => 'New User',
            'email'                 => 'new@company.sn',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertStatus(201);

        // Registration creates a tenant + user — should be auditable
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login', // registration follows with login audit
        ]);
    }

    // ── Five W's validation ───────────────────────────────────────────────────

    #[Test]
    public function audit_entry_contains_all_five_ws(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'admin@audit-test.sn',
            'password' => 'Secret123!',
        ]);

        $log = AuditLog::where('action', 'auth.login')
            ->where('user_id', $this->admin->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->user_id,    'WHO: user_id is required');
        $this->assertNotNull($log->action,     'WHAT: action is required');
        $this->assertNotNull($log->created_at, 'WHEN: created_at is required');
        $this->assertNotNull($log->ip_address, 'WHERE: ip_address is required');
        $this->assertNotNull($log->tenant_id,  'CONTEXT: tenant_id is required');
    }
}
