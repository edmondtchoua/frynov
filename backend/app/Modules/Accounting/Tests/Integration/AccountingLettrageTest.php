<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\EntryLine;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\EntryService;
use App\Modules\Accounting\Services\LettrageService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-39 — lettrage : rapprochement de groupes équilibrés d'un compte de tiers, codes A/B par compte,
 * délettrage, refus d'un groupe déséquilibré, synthèse lettré/ouvert, RBAC.
 */
class AccountingLettrageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private string $chiefToken;
    private EntryService $entries;
    private LettrageService $svc;
    private array $acc = [];
    private string $journalId;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'chief-accountant', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Let SARL', 'slug' => 'let-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@let.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->chief->assignTenantRole('chief-accountant');
        $this->chiefToken = $this->chief->createToken('api')->plainTextToken;

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->chief->id);

        $this->entries = app(EntryService::class);
        $this->svc     = app(LettrageService::class);
        foreach (['411', '571', '701'] as $c) {
            $this->acc[$c] = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', $c)->firstOrFail()->id;
        }
        $this->journalId = Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'OD')->firstOrFail()->id;
        $this->date = FiscalYear::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail()->starts_on->toDateString();
    }

    /** Crée + poste une écriture et renvoie l'id de sa ligne sur le compte $accId. */
    private function lineOn(string $accId, array $lines): string
    {
        $entry = $this->entries->post(
            $this->entries->createDraft(['journal_id' => $this->journalId, 'entry_date' => $this->date, 'label' => 'X', 'lines' => $lines], $this->tenant->id, $this->chief->id),
            $this->chief->id,
        );

        return EntryLine::withoutTenantScope()->where('entry_id', $entry->id)->where('account_id', $accId)->firstOrFail()->id;
    }

    private function invoiceLine(int $amount = 100000): string
    {
        return $this->lineOn($this->acc['411'], [['account_id' => $this->acc['411'], 'debit_minor' => $amount], ['account_id' => $this->acc['701'], 'credit_minor' => $amount]]);
    }

    private function paymentLine(int $amount = 100000): string
    {
        return $this->lineOn($this->acc['411'], [['account_id' => $this->acc['571'], 'debit_minor' => $amount], ['account_id' => $this->acc['411'], 'credit_minor' => $amount]]);
    }

    #[Test]
    public function lettering_a_balanced_group_assigns_a_code_and_clears_the_open_balance(): void
    {
        $l1 = $this->invoiceLine(100000);
        $l2 = $this->paymentLine(100000);

        $code = $this->svc->letter($this->tenant->id, $this->acc['411'], [$l1, $l2], $this->chief->id);
        $this->assertSame('A', $code);

        $data = $this->svc->accountLines($this->tenant->id, $this->acc['411']);
        $this->assertSame(0, $data['summary']['open_balance_minor']);
        $this->assertSame(100000, $data['summary']['lettered_debit_minor']);
        $this->assertSame('A', collect($data['lines'])->firstWhere('id', $l1)['lettrage_code']);
    }

    #[Test]
    public function an_unbalanced_group_is_rejected(): void
    {
        $l1 = $this->invoiceLine(100000); // débit 411
        $l2 = $this->invoiceLine(50000);  // débit 411 → Σ débits ≠ 0 crédit

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->letter($this->tenant->id, $this->acc['411'], [$l1, $l2], $this->chief->id);
    }

    #[Test]
    public function unlettering_reopens_the_lines(): void
    {
        $l1 = $this->invoiceLine(100000);
        $l2 = $this->paymentLine(100000);
        $code = $this->svc->letter($this->tenant->id, $this->acc['411'], [$l1, $l2], $this->chief->id);

        $this->svc->unletter($this->tenant->id, $this->acc['411'], $code, $this->chief->id);

        $data = $this->svc->accountLines($this->tenant->id, $this->acc['411']);
        $this->assertSame(0, $data['summary']['lettered_debit_minor']);
        $this->assertSame(100000, $data['summary']['open_debit_minor']);
        $this->assertNull(collect($data['lines'])->firstWhere('id', $l1)['lettrage_code']);
    }

    #[Test]
    public function codes_increment_per_account(): void
    {
        $a = $this->svc->letter($this->tenant->id, $this->acc['411'], [$this->invoiceLine(10000), $this->paymentLine(10000)], $this->chief->id);
        $b = $this->svc->letter($this->tenant->id, $this->acc['411'], [$this->invoiceLine(20000), $this->paymentLine(20000)], $this->chief->id);
        $this->assertSame('A', $a);
        $this->assertSame('B', $b);
    }

    #[Test]
    public function already_lettered_lines_cannot_be_re_lettered(): void
    {
        $l1 = $this->invoiceLine(100000);
        $l2 = $this->paymentLine(100000);
        $this->svc->letter($this->tenant->id, $this->acc['411'], [$l1, $l2], $this->chief->id);

        $l3 = $this->paymentLine(100000);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->letter($this->tenant->id, $this->acc['411'], [$l1, $l3], $this->chief->id); // l1 déjà lettré
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function the_http_flow_letters_and_a_cashier_is_forbidden(): void
    {
        $l1 = $this->invoiceLine(100000);
        $l2 = $this->paymentLine(100000);
        $auth = ['Authorization' => 'Bearer ' . $this->chiefToken];

        $this->withHeaders($auth)->postJson('/api/accounting/reports/lettrage', ['account_id' => $this->acc['411'], 'line_ids' => [$l1, $l2]])
            ->assertStatus(201)->assertJsonPath('data.code', 'A');

        // only_open arrive en chaîne "false" — ne doit PAS déclencher un 422 (bug corrigé RC-39).
        $this->withHeaders($auth)->getJson("/api/accounting/reports/lettrage?account_id={$this->acc['411']}&only_open=false")
            ->assertOk()->assertJsonPath('data.summary.open_balance_minor', 0);

        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@let.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->postJson('/api/accounting/reports/lettrage', ['account_id' => $this->acc['411'], 'line_ids' => [$l1, $l2]])
            ->assertStatus(403);
    }
}
