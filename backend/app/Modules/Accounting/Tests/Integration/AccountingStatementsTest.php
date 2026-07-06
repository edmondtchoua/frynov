<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\EntryService;
use App\Modules\Accounting\Services\FinancialStatementsService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-42 — états financiers : compte de résultat (charges/produits → résultat) et bilan (actif =
 * passif + résultat, équilibré par construction), RBAC lecture.
 */
class AccountingStatementsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private string $chiefToken;
    private EntryService $entries;
    private FinancialStatementsService $svc;
    private array $acc = [];
    private string $journalId;
    private string $date;
    private string $end;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'chief-accountant', 'accounting-viewer', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Etats SARL', 'slug' => 'etats-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@etats.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->chief->assignTenantRole('chief-accountant');
        $this->chiefToken = $this->chief->createToken('api')->plainTextToken;

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->chief->id);

        $this->entries = app(EntryService::class);
        $this->svc     = app(FinancialStatementsService::class);
        foreach (['571', '411', '401', '601', '701'] as $c) {
            $this->acc[$c] = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', $c)->firstOrFail()->id;
        }
        $this->journalId = Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'OD')->firstOrFail()->id;
        $fy = FiscalYear::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->date = $fy->starts_on->toDateString();
        $this->end  = $fy->ends_on->toDateString();
    }

    private function postEntry(array $lines): void
    {
        $entry = $this->entries->createDraft(['journal_id' => $this->journalId, 'entry_date' => $this->date, 'label' => 'X', 'lines' => $lines], $this->tenant->id, $this->chief->id);
        $this->entries->post($entry, $this->chief->id);
    }

    private function scenario(): void
    {
        // Vente comptant 400 000, vente à crédit 200 000, achat à crédit 150 000.
        $this->postEntry([['account_id' => $this->acc['571'], 'debit_minor' => 400000], ['account_id' => $this->acc['701'], 'credit_minor' => 400000]]);
        $this->postEntry([['account_id' => $this->acc['411'], 'debit_minor' => 200000], ['account_id' => $this->acc['701'], 'credit_minor' => 200000]]);
        $this->postEntry([['account_id' => $this->acc['601'], 'debit_minor' => 150000], ['account_id' => $this->acc['401'], 'credit_minor' => 150000]]);
    }

    private function amount(array $rows, string $code): ?int
    {
        foreach ($rows as $r) {
            if ($r['code'] === $code) {
                return $r['amount_minor'];
            }
        }

        return null;
    }

    #[Test]
    public function the_income_statement_sums_charges_and_produits_and_computes_the_result(): void
    {
        $this->scenario();

        $is = $this->svc->incomeStatement($this->tenant->id, $this->date, $this->end);

        $this->assertSame(150000, $is['total_charges_minor']);
        $this->assertSame(600000, $is['total_produits_minor']);
        $this->assertSame(450000, $is['result_minor']); // bénéfice
        $this->assertSame(150000, $this->amount($is['charges'], '601'));
        $this->assertSame(600000, $this->amount($is['produits'], '701'));
    }

    #[Test]
    public function the_balance_sheet_is_balanced_and_carries_the_result_in_the_liabilities(): void
    {
        $this->scenario();

        $bs = $this->svc->balanceSheet($this->tenant->id, $this->date, $this->end);

        $this->assertSame(600000, $bs['total_actif_minor']);   // 571 + 411
        $this->assertSame(600000, $bs['total_passif_minor']);  // 401 + résultat
        $this->assertTrue($bs['balanced']);
        $this->assertSame(450000, $bs['result_minor']);
        $this->assertSame(400000, $this->amount($bs['actif'], '571'));
        $this->assertSame(200000, $this->amount($bs['actif'], '411'));
        $this->assertSame(150000, $this->amount($bs['passif'], '401'));
        $this->assertSame(450000, $this->amount($bs['passif'], '13')); // résultat au passif
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function statements_are_readable_by_a_viewer_but_not_a_cashier(): void
    {
        $this->scenario();

        $viewer = User::create(['name' => 'V', 'email' => 'v@etats.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $viewer->assignTenantRole('accounting-viewer');
        $this->withHeaders(['Authorization' => 'Bearer ' . $viewer->createToken('api')->plainTextToken])
            ->getJson('/api/accounting/reports/balance-sheet')
            ->assertOk()->assertJsonPath('data.balanced', true);

        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@etats.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->getJson('/api/accounting/reports/income-statement')
            ->assertStatus(403);
    }
}
