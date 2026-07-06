<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\EntryService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-38 — balance générale & grand livre : agrégation des écritures postées/extournées, à-nouveau,
 * solde progressif, équilibre des totaux, exclusion des brouillons, RBAC lecture.
 */
class AccountingLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private EntryService $entries;
    private LedgerService $ledger;
    private array $acc = [];
    private string $journalId;
    private string $d1;
    private string $d2;
    private string $end;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'chief-accountant', 'accounting-viewer', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GL SARL', 'slug' => 'gl-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@gl.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->chief->assignTenantRole('chief-accountant');

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->chief->id);

        $this->entries = app(EntryService::class);
        $this->ledger  = app(LedgerService::class);

        foreach (['571', '701', '411'] as $code) {
            $this->acc[$code] = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail()->id;
        }
        $this->journalId = Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'OD')->firstOrFail()->id;

        // Deux dates dans des périodes ouvertes de l'exercice courant + la fin d'exercice.
        $fy = FiscalYear::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->d1  = $fy->starts_on->toDateString();
        $this->d2  = $fy->starts_on->copy()->addMonths(3)->toDateString();
        $this->end = $fy->ends_on->toDateString();
    }

    private function postEntry(string $date, array $lines, string $label = 'Test'): \App\Modules\Accounting\Models\Entry
    {
        $entry = $this->entries->createDraft(['journal_id' => $this->journalId, 'entry_date' => $date, 'label' => $label, 'lines' => $lines], $this->tenant->id, $this->chief->id);

        return $this->entries->post($entry, $this->chief->id);
    }

    private function row(array $tb, string $code): ?array
    {
        foreach ($tb['rows'] as $r) {
            if ($r['code'] === $code) {
                return $r;
            }
        }

        return null;
    }

    #[Test]
    public function the_trial_balance_aggregates_posted_entries_and_balances(): void
    {
        $this->postEntry($this->d1, [['account_id' => $this->acc['571'], 'debit_minor' => 100000], ['account_id' => $this->acc['701'], 'credit_minor' => 100000]]);
        $this->postEntry($this->d2, [['account_id' => $this->acc['411'], 'debit_minor' => 60000], ['account_id' => $this->acc['701'], 'credit_minor' => 60000]]);

        $tb = $this->ledger->trialBalance($this->tenant->id, null, $this->end);

        $this->assertSame(100000, $this->row($tb, '571')['closing_minor']);
        $this->assertSame(60000, $this->row($tb, '411')['closing_minor']);
        $this->assertSame(-160000, $this->row($tb, '701')['closing_minor']); // solde créditeur
        $this->assertSame(160000, $this->row($tb, '701')['credit_minor']);

        // Invariants d'équilibre.
        $this->assertSame($tb['totals']['debit_minor'], $tb['totals']['credit_minor']);
        $this->assertSame($tb['totals']['closing_debit_minor'], $tb['totals']['closing_credit_minor']);
        $this->assertSame(160000, $tb['totals']['debit_minor']);
    }

    #[Test]
    public function an_opening_balance_folds_movements_before_the_from_date(): void
    {
        $this->postEntry($this->d1, [['account_id' => $this->acc['571'], 'debit_minor' => 100000], ['account_id' => $this->acc['701'], 'credit_minor' => 100000]]);
        $this->postEntry($this->d2, [['account_id' => $this->acc['571'], 'debit_minor' => 40000], ['account_id' => $this->acc['701'], 'credit_minor' => 40000]]);

        // Période commençant à d2 : l'écriture de d1 devient à-nouveau.
        $tb = $this->ledger->trialBalance($this->tenant->id, $this->d2, $this->end);

        $r = $this->row($tb, '571');
        $this->assertSame(100000, $r['opening_minor']); // à-nouveau (d1)
        $this->assertSame(40000, $r['debit_minor']);    // mouvement période (d2)
        $this->assertSame(140000, $r['closing_minor']);
    }

    #[Test]
    public function reversed_entries_remain_in_the_ledger_and_net_to_zero(): void
    {
        $entry = $this->postEntry($this->d1, [['account_id' => $this->acc['571'], 'debit_minor' => 50000], ['account_id' => $this->acc['701'], 'credit_minor' => 50000]]);
        $this->entries->reverse($entry, $this->chief->id, $this->d2);

        $tb = $this->ledger->trialBalance($this->tenant->id, null, $this->end);

        // Mouvements présents (débit = crédit sur chaque compte), solde net nul → toujours équilibré.
        $r571 = $this->row($tb, '571');
        $this->assertSame(50000, $r571['debit_minor']);
        $this->assertSame(50000, $r571['credit_minor']);
        $this->assertSame(0, $r571['closing_minor']);
        $this->assertSame($tb['totals']['debit_minor'], $tb['totals']['credit_minor']);
    }

    #[Test]
    public function draft_entries_are_excluded(): void
    {
        // Brouillon non posté.
        $this->entries->createDraft(['journal_id' => $this->journalId, 'entry_date' => $this->d1, 'label' => 'Brouillon', 'lines' => [
            ['account_id' => $this->acc['571'], 'debit_minor' => 99999], ['account_id' => $this->acc['701'], 'credit_minor' => 99999],
        ]], $this->tenant->id, $this->chief->id);

        $tb = $this->ledger->trialBalance($this->tenant->id, null, $this->end);
        $this->assertNull($this->row($tb, '571'));
        $this->assertSame([], $tb['rows']);
    }

    #[Test]
    public function the_general_ledger_lists_lines_with_a_running_balance(): void
    {
        $this->postEntry($this->d1, [['account_id' => $this->acc['701'], 'credit_minor' => 100000], ['account_id' => $this->acc['571'], 'debit_minor' => 100000]]);
        $this->postEntry($this->d2, [['account_id' => $this->acc['701'], 'credit_minor' => 60000], ['account_id' => $this->acc['411'], 'debit_minor' => 60000]]);

        $gl = $this->ledger->generalLedger($this->tenant->id, $this->acc['701'], null, $this->end);

        $this->assertSame(0, $gl['opening_minor']);
        $this->assertCount(2, $gl['lines']);
        $this->assertSame(-100000, $gl['lines'][0]['running_minor']);
        $this->assertSame(-160000, $gl['lines'][1]['running_minor']);
        $this->assertSame(160000, $gl['credit_total_minor']);
        $this->assertSame(-160000, $gl['closing_minor']);
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function reports_are_readable_by_a_viewer_but_not_a_cashier(): void
    {
        $this->postEntry($this->d1, [['account_id' => $this->acc['571'], 'debit_minor' => 100000], ['account_id' => $this->acc['701'], 'credit_minor' => 100000]]);

        $viewer = User::create(['name' => 'V', 'email' => 'v@gl.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $viewer->assignTenantRole('accounting-viewer');
        $this->withHeaders(['Authorization' => 'Bearer ' . $viewer->createToken('api')->plainTextToken])
            ->getJson('/api/accounting/reports/trial-balance')
            ->assertOk()->assertJsonPath('data.totals.debit_minor', 100000);

        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@gl.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');
        $this->app['auth']->forgetGuards(); // Sanctum met en cache le 1er user résolu (viewer) → forcer la ré-résolution
        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->getJson('/api/accounting/reports/trial-balance')
            ->assertStatus(403);
    }
}
