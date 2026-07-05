<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSettings;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\InvoiceService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Customers\Models\Customer;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-33 — avoirs : création depuis facture (reprise des lignes), émission → écriture INVERSE
 * (701/4431 débit, 411 crédit, journal AV), application à une facture (bornée, statuts), RBAC.
 */
class AccountingCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $chief;
    private string $chiefToken;
    private InvoiceService $svc;
    private Tax $vat;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'chief-accountant', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Avoir SARL', 'slug' => 'avoir-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@avoir.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->chief->assignTenantRole('chief-accountant');
        $this->chiefToken = $this->chief->createToken('api')->plainTextToken;

        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->chief->id);
        AccountingSettings::withoutTenantScope()->where('tenant_id', $this->tenant->id)->update(['auto_post' => true]);

        $this->svc = app(InvoiceService::class);
        $this->vat = Tax::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('code', 'TVA18')->firstOrFail();
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->chiefToken];
    }

    private function issuedInvoice(int $unit = 100000, ?string $customerId = null, bool $tax = false): Invoice
    {
        return $this->svc->issue($this->svc->createDraft([
            'customer_id' => $customerId,
            'lines' => [['label' => 'Bien', 'quantity' => 1, 'unit_price_minor' => $unit, 'tax_id' => $tax ? $this->vat->id : null]],
        ], $this->tenant->id, $this->chief->id), $this->chief->id);
    }

    #[Test]
    public function a_credit_note_from_an_invoice_copies_its_lines_and_links_back(): void
    {
        $invoice    = $this->issuedInvoice(100000, tax: true); // 100 000 HT + 18 000 TVA = 118 000
        $creditNote = $this->svc->createCreditNoteFromInvoice($invoice, null, $this->chief->id);

        $this->assertSame(Invoice::KIND_CREDIT_NOTE, $creditNote->kind);
        $this->assertSame($invoice->id, $creditNote->credit_note_of_id);
        $this->assertSame(Invoice::STATUS_DRAFT, $creditNote->status);
        $this->assertSame(100000, $creditNote->subtotal_minor);
        $this->assertSame(18000, $creditNote->tax_total_minor);
        $this->assertSame(118000, $creditNote->total_minor);
        $this->assertCount(1, $creditNote->lines);
    }

    #[Test]
    public function issuing_a_credit_note_numbers_it_and_books_the_reversing_entry(): void
    {
        $invoice    = $this->issuedInvoice(100000, tax: true);
        $creditNote = $this->svc->issueCreditNote($this->svc->createCreditNoteFromInvoice($invoice, null, $this->chief->id), $this->chief->id);

        $this->assertStringStartsWith('AV-', $creditNote->number);
        $this->assertSame(Invoice::STATUS_ISSUED, $creditNote->status);
        $this->assertNotNull($creditNote->entry_id);

        $entry = Entry::withoutTenantScope()->where('id', $creditNote->entry_id)->with('lines.account', 'journal')->first();
        $this->assertSame(Entry::STATUS_POSTED, $entry->status);
        $this->assertSame('AV', $entry->journal->code);

        // INVERSE de la facture : 701 & 4431 au DÉBIT, 411 au CRÉDIT.
        $byCode = $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ['d' => (int) $l->debit_minor, 'c' => (int) $l->credit_minor]]);
        $this->assertSame(100000, $byCode['701']['d']);   // annulation vente (HT)
        $this->assertSame(18000, $byCode['4431']['d']);   // TVA à régulariser
        $this->assertSame(118000, $byCode['411']['c']);   // client crédité
        $this->assertSame($entry->totalDebit(), $entry->totalCredit());
    }

    #[Test]
    public function applying_a_credit_note_reduces_the_invoice_balance_and_updates_statuses(): void
    {
        $customer   = Customer::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'name' => 'C', 'email' => 'c@avoir.sn']);
        $invoice    = $this->issuedInvoice(100000, $customer->id);          // 100 000, sans TVA
        $creditNote = $this->svc->issueCreditNote($this->svc->createCreditNoteFromInvoice($invoice, null, $this->chief->id), $this->chief->id);

        // Application partielle 40 000.
        $this->svc->applyCreditNote($creditNote, $invoice, 40000, $this->chief->id);
        $invoice->refresh();
        $creditNote->refresh();
        $this->assertSame(40000, $invoice->credited_minor);
        $this->assertSame(60000, $invoice->remainingMinor());
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $creditNote->status);

        // Solde 60 000 → facture soldée par l'avoir, avoir entièrement appliqué.
        $this->svc->applyCreditNote($creditNote, $invoice, 60000, $this->chief->id);
        $invoice->refresh();
        $creditNote->refresh();
        $this->assertSame(0, $invoice->remainingMinor());
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(0, $creditNote->remainingMinor());
        $this->assertSame(Invoice::STATUS_PAID, $creditNote->status);
    }

    #[Test]
    public function an_application_exceeding_the_available_amount_is_rejected(): void
    {
        $invoice    = $this->issuedInvoice(100000);
        $creditNote = $this->svc->issueCreditNote($this->svc->createCreditNoteFromInvoice($invoice, null, $this->chief->id), $this->chief->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->applyCreditNote($creditNote, $invoice, 150000, $this->chief->id); // max = 100 000
    }

    #[Test]
    public function a_draft_credit_note_cannot_be_applied(): void
    {
        $invoice    = $this->issuedInvoice(100000);
        $creditNote = $this->svc->createCreditNoteFromInvoice($invoice, null, $this->chief->id); // reste brouillon

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->applyCreditNote($creditNote, $invoice, 10000, $this->chief->id);
    }

    #[Test]
    public function a_credit_note_cannot_be_created_from_a_draft_invoice(): void
    {
        $draft = $this->svc->createDraft([
            'lines' => [['label' => 'X', 'quantity' => 1, 'unit_price_minor' => 5000]],
        ], $this->tenant->id, $this->chief->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->createCreditNoteFromInvoice($draft, null, $this->chief->id);
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function the_full_credit_note_lifecycle_works_over_http(): void
    {
        $invoice = $this->issuedInvoice(50000, tax: true); // 50 000 HT + 9 000 = 59 000

        $creditNote = $this->withHeaders($this->auth())
            ->postJson("/api/accounting/invoices/{$invoice->id}/credit-notes")
            ->assertStatus(201)->json('data');
        $this->assertSame(59000, $creditNote['total_minor']);

        $issued = $this->withHeaders($this->auth())
            ->postJson("/api/accounting/credit-notes/{$creditNote['id']}/issue")
            ->assertOk()->json('data');
        $this->assertStringStartsWith('AV-', $issued['number']);

        $this->withHeaders($this->auth())
            ->postJson("/api/accounting/credit-notes/{$creditNote['id']}/apply", ['invoice_id' => $invoice->id, 'amount_minor' => 59000])
            ->assertStatus(201);

        $this->withHeaders($this->auth())->getJson('/api/accounting/invoices?kind=credit_note')
            ->assertOk()->assertJsonCount(1, 'data');

        // La facture d'origine est soldée par l'avoir.
        $this->assertSame(Invoice::STATUS_PAID, Invoice::withoutTenantScope()->find($invoice->id)->status);
    }

    #[Test]
    public function a_cashier_cannot_issue_credit_notes(): void
    {
        $invoice = $this->issuedInvoice(20000);
        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@avoir.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');

        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->postJson("/api/accounting/invoices/{$invoice->id}/credit-notes")
            ->assertStatus(403);
    }
}
