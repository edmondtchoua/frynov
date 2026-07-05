<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-23 — référentiel comptable SYSCOHADA : provisionnement idempotent, RBAC comptable
 * (séparation des pouvoirs), isolation tenant, verrouillage/réouverture de période.
 */
class AccountingReferentialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private User $accountant;
    private User $cashier;
    private string $chiefToken;
    private string $accountantToken;
    private string $cashierToken;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'manager', 'cashier', 'accountant', 'chief-accountant', 'accounting-viewer', 'auditor'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        // Les permissions citées dans les routes doivent EXISTER (Spatie lève sinon).
        foreach (['accounting.view', 'accounting.manage', 'accounting.periods.reopen'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Compta SARL', 'slug' => 'compta-sarl', 'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'country' => 'SN'],
        ]);

        [$this->chief, $this->chiefToken]           = $this->makeUser('chef@compta.sn', 'chief-accountant');
        [$this->accountant, $this->accountantToken] = $this->makeUser('cpt@compta.sn', 'accountant');
        [$this->cashier, $this->cashierToken]       = $this->makeUser('caisse@compta.sn', 'cashier');
    }

    /** @return array{0: User, 1: string} */
    private function makeUser(string $email, string $role): array
    {
        $user = User::create(['name' => $role, 'email' => $email, 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole($role);

        return [$user, $user->createToken('api')->plainTextToken];
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Change d'utilisateur au sein d'un même test : purge le cache du guard Sanctum
     * (sinon la 2e requête ré-résout le PREMIER utilisateur — artefact de test connu).
     */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->withHeaders($this->auth($token));
    }

    // ── Provisionnement ────────────────────────────────────────────────────────

    #[Test]
    public function provisioning_seeds_the_syscohada_referential_and_is_idempotent(): void
    {
        $this->withHeaders($this->auth($this->chiefToken))
            ->postJson('/api/accounting/provision')->assertStatus(201);

        $counts = fn () => [
            'accounts' => \App\Modules\Accounting\Models\Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count(),
            'journals' => \App\Modules\Accounting\Models\Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count(),
            'taxes'    => \App\Modules\Accounting\Models\Tax::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count(),
            'periods'  => AccountingPeriod::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count(),
        ];

        $first = $counts();
        $this->assertGreaterThan(30, $first['accounts']);          // sous-ensemble SYSCOHADA
        $this->assertSame(8, $first['journals']);                  // VT AC CA BQ OD ST AV RG
        $this->assertSame(1, $first['taxes']);                     // TVA 18 % (SN)
        $this->assertSame(12, $first['periods']);                  // 12 mois

        // Comptes clés du moteur d'imputation présents et système.
        foreach (['571', '521', '585', '411', '401', '701', '4431', '4452', '471'] as $code) {
            $this->assertDatabaseHas('accounting_accounts', [
                'tenant_id' => $this->tenant->id, 'code' => $code, 'is_system' => true,
            ]);
        }

        // TVA sénégalaise à 18 % (1800 bp) branchée sur 4431/4452.
        $this->assertDatabaseHas('accounting_taxes', ['tenant_id' => $this->tenant->id, 'code' => 'TVA18', 'rate_bp' => 1800]);

        // Rejeu → AUCUN doublon.
        $this->as($this->chiefToken)
            ->postJson('/api/accounting/provision')->assertStatus(201);
        $this->assertSame($first, $counts());
    }

    #[Test]
    public function the_overview_reports_provisioning_state_and_classes(): void
    {
        $this->withHeaders($this->auth($this->accountantToken))
            ->getJson('/api/accounting/overview')
            ->assertOk()
            ->assertJsonPath('data.provisioned', false)
            ->assertJsonCount(9, 'data.classes');
    }

    // ── RBAC — séparation des pouvoirs ─────────────────────────────────────────

    #[Test]
    public function a_cashier_cannot_read_the_referential_and_an_accountant_cannot_manage_it(): void
    {
        $this->withHeaders($this->auth($this->cashierToken))
            ->getJson('/api/accounting/accounts')->assertStatus(403);

        // Comptable : lecture OK…
        $this->as($this->accountantToken)
            ->getJson('/api/accounting/journals')->assertOk();

        // …mais pas d'écriture référentielle (réservée chef comptable/admin).
        $this->as($this->accountantToken)
            ->postJson('/api/accounting/taxes', ['code' => 'X', 'name' => 'X', 'rate_bp' => 100])
            ->assertStatus(403);
    }

    // ── Isolation tenant ───────────────────────────────────────────────────────

    #[Test]
    public function the_chart_of_accounts_is_isolated_per_tenant(): void
    {
        app(\App\Modules\Accounting\Services\ChartOfAccountsProvisioner::class)
            ->provision($this->tenant, $this->chief->id);

        $other = Tenant::create(['name' => 'Autre', 'slug' => 'autre-compta', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF']]);
        [, $otherToken] = [null, tap(User::create(['name' => 'C', 'email' => 'c@autre.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]))
            ->assignTenantRole('chief-accountant')->createToken('api')->plainTextToken];

        $this->withHeaders($this->auth($otherToken))
            ->getJson('/api/accounting/accounts')
            ->assertOk()
            ->assertJsonCount(0, 'data');                            // rien du tenant A
    }

    // ── Garde-fous référentiel ─────────────────────────────────────────────────

    #[Test]
    public function a_system_account_cannot_be_deactivated(): void
    {
        app(\App\Modules\Accounting\Services\ChartOfAccountsProvisioner::class)
            ->provision($this->tenant, $this->chief->id);
        $cash = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', '571')->firstOrFail();

        $this->withHeaders($this->auth($this->chiefToken))
            ->putJson("/api/accounting/accounts/{$cash->id}", ['is_active' => false])
            ->assertStatus(422);

        $this->assertTrue($cash->fresh()->is_active);
    }

    #[Test]
    public function a_custom_account_is_created_with_its_class_derived_from_the_code(): void
    {
        app(\App\Modules\Accounting\Services\ChartOfAccountsProvisioner::class)
            ->provision($this->tenant, $this->chief->id);

        $this->withHeaders($this->auth($this->chiefToken))
            ->postJson('/api/accounting/accounts', [
                'code' => '7011', 'name' => 'Ventes boutique Dakar', 'kind' => 'revenue',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.class_code', 7);
    }

    // ── Périodes : verrouillage + réouverture contrôlée ────────────────────────

    #[Test]
    public function locking_a_period_requires_chief_and_reopening_requires_the_dedicated_permission(): void
    {
        app(\App\Modules\Accounting\Services\ChartOfAccountsProvisioner::class)
            ->provision($this->tenant, $this->chief->id);
        $period = AccountingPeriod::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)->orderBy('starts_on')->firstOrFail();

        // Chef comptable : verrouillage OK.
        $this->withHeaders($this->auth($this->chiefToken))
            ->postJson("/api/accounting/periods/{$period->id}/lock", ['reason' => 'clôture janvier'])
            ->assertOk()->assertJsonPath('data.status', 'locked');

        // Journal d'audit écrit.
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => 'accounting.period.locked']);

        // Le chef n'a PAS la réouverture (permission dédiée) → 403.
        $this->as($this->chiefToken)
            ->postJson("/api/accounting/periods/{$period->id}/unlock", ['reason' => 'correction'])
            ->assertStatus(403);

        // Un admin, si — motif obligatoire, audit tracé.
        [, $adminToken] = [null, tap(User::create(['name' => 'A', 'email' => 'a@compta.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]))
            ->assignTenantRole('admin')->createToken('api')->plainTextToken];
        $this->as($adminToken)
            ->postJson("/api/accounting/periods/{$period->id}/unlock", ['reason' => 'correction validée DG'])
            ->assertOk()->assertJsonPath('data.status', 'open');
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => 'accounting.period.reopened']);
    }

    // ── Gating module ──────────────────────────────────────────────────────────

    #[Test]
    public function the_module_gate_blocks_a_tenant_without_the_accounting_module(): void
    {
        // Désactive le module accounting pour CE tenant (auto-provisionné par TestCase).
        $module = \App\Modules\Platform\Models\ErpModule::where('code', 'accounting')->firstOrFail();
        \App\Modules\Platform\Models\TenantModule::where('tenant_id', $this->tenant->id)
            ->where('module_id', $module->id)
            ->update(['status' => \App\Modules\Platform\Models\TenantModule::STATUS_INACTIVE]);

        $this->withHeaders($this->auth($this->chiefToken))
            ->getJson('/api/accounting/overview')
            ->assertStatus(403);
    }
}
