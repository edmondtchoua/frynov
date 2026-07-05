<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\AccountingSettings;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\EntryService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-25 — écritures en partie double : équilibre imposé, comptabilisation (numéro + période),
 * immutabilité (extourne), refus en période verrouillée.
 */
class AccountingEntryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private EntryService $svc;
    private Journal $od;
    private Account $cash;
    private Account $sales;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Écr SARL', 'slug' => 'ecr-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->user   = User::create(['name' => 'A', 'email' => 'a@ecr.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->user->id);
        $this->svc   = app(EntryService::class);
        $this->od    = Journal::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'OD')->firstOrFail();
        $this->cash  = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', '571')->firstOrFail();
        $this->sales = Account::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', '701')->firstOrFail();
    }

    private function draft(int $debit, int $credit, ?string $date = null): array
    {
        return [
            'journal_id' => $this->od->id,
            'entry_date' => $date ?? now()->toDateString(),
            'label'      => 'Test',
            'lines'      => [
                ['account_id' => $this->cash->id,  'debit_minor' => $debit,  'credit_minor' => 0],
                ['account_id' => $this->sales->id, 'debit_minor' => 0,       'credit_minor' => $credit],
            ],
        ];
    }

    #[Test]
    public function a_balanced_draft_is_created_then_posted_with_a_sequential_number(): void
    {
        $entry = $this->svc->createDraft($this->draft(50000, 50000), $this->tenant->id, $this->user->id);
        $this->assertSame(Entry::STATUS_DRAFT, $entry->status);
        $this->assertNull($entry->number);

        $posted = $this->svc->post($entry, $this->user->id);
        $this->assertSame(Entry::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertNotNull($posted->period_id);
        $this->assertSame(50000, $posted->totalDebit());
        $this->assertSame(50000, $posted->totalCredit());
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => 'accounting.entry.posted']);
    }

    #[Test]
    public function an_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->createDraft($this->draft(50000, 40000), $this->tenant->id, $this->user->id);
    }

    #[Test]
    public function a_line_with_both_sides_positive_is_rejected(): void
    {
        $data = $this->draft(50000, 50000);
        $data['lines'][0] = ['account_id' => $this->cash->id, 'debit_minor' => 10, 'credit_minor' => 10];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->createDraft($data, $this->tenant->id, $this->user->id);
    }

    #[Test]
    public function an_entry_dated_in_a_locked_period_is_refused(): void
    {
        $period = AccountingPeriod::withoutTenantScope()->where('tenant_id', $this->tenant->id)->orderBy('starts_on')->firstOrFail();
        $period->update(['status' => AccountingPeriod::STATUS_LOCKED]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->createDraft($this->draft(1000, 1000, $period->starts_on->toDateString()), $this->tenant->id, $this->user->id);
    }

    #[Test]
    public function a_posted_entry_cannot_be_reposted(): void
    {
        $entry = $this->svc->post($this->svc->createDraft($this->draft(1000, 1000), $this->tenant->id, $this->user->id), $this->user->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->post($entry, $this->user->id);
    }

    #[Test]
    public function reversing_a_posted_entry_creates_a_mirror_and_marks_the_source_reversed(): void
    {
        $entry = $this->svc->post($this->svc->createDraft($this->draft(80000, 80000), $this->tenant->id, $this->user->id), $this->user->id);

        $reversal = $this->svc->reverse($entry, $this->user->id, null, 'erreur de saisie');

        $this->assertSame(Entry::STATUS_POSTED, $reversal->status);
        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertSame(Entry::STATUS_REVERSED, $entry->fresh()->status);
        $this->assertSame($reversal->id, $entry->fresh()->reversed_by_id);

        // Les côtés sont inversés : la caisse est créditée dans l'extourne (elle était débitée).
        $reversedCashLine = $reversal->lines->firstWhere('account_id', $this->cash->id);
        $this->assertSame(80000, (int) $reversedCashLine->credit_minor);
        $this->assertSame(0, (int) $reversedCashLine->debit_minor);

        // Somme des deux écritures = 0 par compte (neutralisation).
        $this->assertSame(0, $entry->totalDebit() - $entry->totalCredit());

        // Double extourne interdite.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->reverse($entry->fresh(), $this->user->id);
    }

    #[Test]
    public function an_entry_needs_at_least_two_lines(): void
    {
        $data = $this->draft(1000, 1000);
        $data['lines'] = [$data['lines'][0]];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->createDraft($data, $this->tenant->id, $this->user->id);
    }
}
