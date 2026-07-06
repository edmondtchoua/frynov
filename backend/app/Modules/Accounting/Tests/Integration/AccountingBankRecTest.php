<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\EntryLine;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\BankReconciliationService;
use App\Modules\Accounting\Services\EntryService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-43 — rapprochement bancaire : solde comptable vs relevé, en-cours (dépôts en transit / chèques
 * en circulation), pointage, identité de rapprochement, RBAC.
 */
class AccountingBankRecTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private string $chiefToken;
    private EntryService $entries;
    private BankReconciliationService $svc;
    private array $acc = [];
    private string $journalId;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'chief-accountant', 'accounting-viewer', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Bank SARL', 'slug' => 'bank-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@bank.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->chief->assignTenantRole('chief-accountant');
        $this->chiefToken = $this->chief->createToken('api')->plainTextToken;

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->chief->id);
        $this->entries = app(EntryService::class);
        $this->svc     = app(BankReconciliationService::class);
        foreach (['521', '701', '601', '411'] as $c) {
            $this->acc[$c] = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', $c)->firstOrFail()->id;
        }
        $this->journalId = Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'BQ')->firstOrFail()->id;
        $this->date = FiscalYear::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail()->starts_on->toDateString();
    }

    private function bankLine(array $lines): string
    {
        $entry = $this->entries->post(
            $this->entries->createDraft(['journal_id' => $this->journalId, 'entry_date' => $this->date, 'label' => 'X', 'lines' => $lines], $this->tenant->id, $this->chief->id),
            $this->chief->id,
        );

        return EntryLine::withoutTenantScope()->where('entry_id', $entry->id)->where('account_id', $this->acc['521'])->firstOrFail()->id;
    }

    /** Encaissement banque 500 000 ; chèque émis 100 000 ; virement client 200 000 → solde 521 = 600 000. */
    private function scenario(): string
    {
        $receipt = $this->bankLine([['account_id' => $this->acc['521'], 'debit_minor' => 500000], ['account_id' => $this->acc['701'], 'credit_minor' => 500000]]);
        $this->bankLine([['account_id' => $this->acc['601'], 'debit_minor' => 100000], ['account_id' => $this->acc['521'], 'credit_minor' => 100000]]);
        $this->bankLine([['account_id' => $this->acc['521'], 'debit_minor' => 200000], ['account_id' => $this->acc['411'], 'credit_minor' => 200000]]);

        return $receipt;
    }

    #[Test]
    public function the_state_reports_the_book_balance_and_outstanding_items(): void
    {
        $this->scenario();

        $s = $this->svc->state($this->tenant->id, $this->acc['521']);
        $this->assertSame(600000, $s['summary']['book_balance_minor']);
        $this->assertSame(700000, $s['summary']['outstanding_debit_minor']);  // rien de pointé
        $this->assertSame(100000, $s['summary']['outstanding_credit_minor']);
        $this->assertCount(3, $s['lines']);
    }

    #[Test]
    public function pointing_the_statement_lines_reconciles_the_account(): void
    {
        $receipt = $this->scenario();

        // Le relevé ne montre que l'encaissement de 500 000 → on le pointe.
        $this->svc->setPointed($this->tenant->id, $this->acc['521'], [$receipt], true, $this->chief->id);

        // Solde relevé = 500 000 ; en-cours = +200 000 (dépôt en transit) −100 000 (chèque).
        $s = $this->svc->state($this->tenant->id, $this->acc['521'], 500000);
        $this->assertSame(200000, $s['summary']['outstanding_debit_minor']);
        $this->assertSame(100000, $s['summary']['outstanding_credit_minor']);
        $this->assertSame(0, $s['summary']['difference_minor']);
        $this->assertTrue($s['summary']['reconciled']);
    }

    #[Test]
    public function an_unreconciled_statement_shows_a_non_zero_difference(): void
    {
        $this->scenario(); // rien pointé

        $s = $this->svc->state($this->tenant->id, $this->acc['521'], 500000);
        $this->assertNotSame(0, $s['summary']['difference_minor']);
        $this->assertFalse($s['summary']['reconciled']);
    }

    #[Test]
    public function only_lines_of_the_account_can_be_pointed(): void
    {
        $this->scenario();
        // Une ligne d'un AUTRE compte (411) ne doit pas être pointable via le compte 521.
        $foreign = EntryLine::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('account_id', $this->acc['411'])->firstOrFail()->id;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->setPointed($this->tenant->id, $this->acc['521'], [$foreign], true, $this->chief->id);
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function the_http_flow_points_and_a_cashier_is_forbidden(): void
    {
        $receipt = $this->scenario();
        $auth = ['Authorization' => 'Bearer ' . $this->chiefToken];

        $this->withHeaders($auth)->postJson('/api/accounting/reports/bank-reconciliation/point', ['account_id' => $this->acc['521'], 'line_ids' => [$receipt]])
            ->assertStatus(201);

        $this->withHeaders($auth)->getJson("/api/accounting/reports/bank-reconciliation?account_id={$this->acc['521']}&statement_balance=500000")
            ->assertOk()->assertJsonPath('data.summary.reconciled', true);

        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@bank.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->postJson('/api/accounting/reports/bank-reconciliation/point', ['account_id' => $this->acc['521'], 'line_ids' => [$receipt]])
            ->assertStatus(403);
    }
}
