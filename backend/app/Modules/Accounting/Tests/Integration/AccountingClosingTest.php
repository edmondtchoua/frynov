<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\ClosingService;
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
 * RC-41 — clôture d'exercice : détermination du résultat (→ 13), report-à-nouveau équilibré dans
 * l'exercice suivant (ouvert au besoin), exercice & périodes fermés, RBAC.
 */
class AccountingClosingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private string $chiefToken;
    private EntryService $entries;
    private ClosingService $svc;
    private FiscalYear $fy;
    private array $acc = [];
    private string $journalId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'chief-accountant', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Clos SARL', 'slug' => 'clos-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@clos.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->chief->assignTenantRole('chief-accountant');
        $this->chiefToken = $this->chief->createToken('api')->plainTextToken;

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->chief->id);

        $this->entries = app(EntryService::class);
        $this->svc     = app(ClosingService::class);
        foreach (['571', '701', '601', '13'] as $c) {
            $this->acc[$c] = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', $c)->firstOrFail()->id;
        }
        $this->journalId = Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'OD')->firstOrFail()->id;
        $this->fy = FiscalYear::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail();
    }

    private function postEntry(array $lines): void
    {
        $entry = $this->entries->createDraft(['journal_id' => $this->journalId, 'entry_date' => $this->fy->starts_on->toDateString(), 'label' => 'X', 'lines' => $lines], $this->tenant->id, $this->chief->id);
        $this->entries->post($entry, $this->chief->id);
    }

    private function byCode(Entry $entry): array
    {
        return $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ['d' => (int) $l->debit_minor, 'c' => (int) $l->credit_minor]])->all();
    }

    #[Test]
    public function closing_determines_the_profit_and_posts_a_balanced_carry_forward(): void
    {
        // Vente 400 000 encaissée, charge 100 000 payée → résultat = +300 000.
        $this->postEntry([['account_id' => $this->acc['571'], 'debit_minor' => 400000], ['account_id' => $this->acc['701'], 'credit_minor' => 400000]]);
        $this->postEntry([['account_id' => $this->acc['601'], 'debit_minor' => 100000], ['account_id' => $this->acc['571'], 'credit_minor' => 100000]]);

        $res = $this->svc->close($this->fy, $this->chief->id);

        $this->assertSame(300000, $res['result_minor']); // bénéfice

        $ran = $res['carry_forward_entry']->load('lines.account', 'journal');
        $this->assertSame(Entry::STATUS_POSTED, $ran->status);
        $this->assertSame('OD', $ran->journal->code);
        $this->assertSame($res['next_year']->starts_on->toDateString(), $ran->entry_date->toDateString());

        $codes = $this->byCode($ran);
        $this->assertSame(300000, $codes['571']['d']); // solde caisse reporté (débiteur)
        $this->assertSame(300000, $codes['13']['c']);  // résultat porté au crédit de 13 (bénéfice)
        $this->assertSame($ran->totalDebit(), $ran->totalCredit());
        $this->assertArrayNotHasKey('701', $codes);     // comptes de gestion NON reportés
        $this->assertArrayNotHasKey('601', $codes);
    }

    #[Test]
    public function closing_marks_the_year_closed_and_opens_the_next_one(): void
    {
        $this->postEntry([['account_id' => $this->acc['571'], 'debit_minor' => 50000], ['account_id' => $this->acc['701'], 'credit_minor' => 50000]]);

        $res = $this->svc->close($this->fy, $this->chief->id);

        $this->fy->refresh();
        $this->assertSame(FiscalYear::STATUS_CLOSED, $this->fy->status);
        $this->assertNotNull($this->fy->carry_forward_entry_id);
        $this->assertSame(0, AccountingPeriod::withoutTenantScope()->where('fiscal_year_id', $this->fy->id)->where('status', '!=', 'closed')->count());

        $next = $res['next_year'];
        $this->assertSame(FiscalYear::STATUS_OPEN, $next->status);
        $this->assertSame(12, AccountingPeriod::withoutTenantScope()->where('fiscal_year_id', $next->id)->count());
    }

    #[Test]
    public function a_loss_is_carried_to_the_debit_of_account_13(): void
    {
        // Charge 500 000, vente 200 000 → perte = -300 000.
        $this->postEntry([['account_id' => $this->acc['601'], 'debit_minor' => 500000], ['account_id' => $this->acc['571'], 'credit_minor' => 500000]]);
        $this->postEntry([['account_id' => $this->acc['571'], 'debit_minor' => 200000], ['account_id' => $this->acc['701'], 'credit_minor' => 200000]]);

        $res = $this->svc->close($this->fy, $this->chief->id);
        $this->assertSame(-300000, $res['result_minor']); // perte

        $codes = $this->byCode($res['carry_forward_entry']->load('lines.account'));
        $this->assertSame(300000, $codes['13']['d']); // perte au débit de 13
    }

    #[Test]
    public function an_already_closed_year_cannot_be_closed_again(): void
    {
        $this->postEntry([['account_id' => $this->acc['571'], 'debit_minor' => 50000], ['account_id' => $this->acc['701'], 'credit_minor' => 50000]]);
        $this->svc->close($this->fy, $this->chief->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->close($this->fy->fresh(), $this->chief->id);
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function closing_is_reserved_to_the_chief_accountant_over_http(): void
    {
        $this->postEntry([['account_id' => $this->acc['571'], 'debit_minor' => 50000], ['account_id' => $this->acc['701'], 'credit_minor' => 50000]]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->chiefToken])
            ->postJson("/api/accounting/fiscal-years/{$this->fy->id}/close")
            ->assertStatus(201)->assertJsonPath('data.result_minor', 50000);

        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@clos.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->postJson("/api/accounting/fiscal-years/{$this->fy->id}/close")
            ->assertStatus(403);
    }
}
