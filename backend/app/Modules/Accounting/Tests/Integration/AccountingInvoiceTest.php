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
use App\Modules\Payments\Models\Payment;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-30 — facturation : TVA par ligne, émission → écriture 411/701/4431, allocation de paiement
 * (partielle/multiple, bornée), écriture d'encaissement, RBAC.
 */
class AccountingInvoiceTest extends TestCase
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

        $this->tenant = Tenant::create(['name' => 'Fact SARL', 'slug' => 'fact-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->chief  = User::create(['name' => 'Chef', 'email' => 'chef@fact.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
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

    #[Test]
    public function a_draft_invoice_computes_line_and_totals_with_discount_and_tax(): void
    {
        // 2 × 100 000 = 200 000 HT, remise 10 % → 180 000 HT ; TVA 18 % → 32 400 ; TTC 212 400.
        $invoice = $this->svc->createDraft([
            'customer_name' => 'Client SA',
            'lines' => [[
                'label' => 'Prestation', 'quantity' => 2, 'unit_price_minor' => 100000,
                'discount_bp' => 1000, 'tax_id' => $this->vat->id,
            ]],
        ], $this->tenant->id, $this->chief->id);

        $this->assertSame(180000, $invoice->subtotal_minor);
        $this->assertSame(32400, $invoice->tax_total_minor);
        $this->assertSame(212400, $invoice->total_minor);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertNull($invoice->number);
    }

    #[Test]
    public function issuing_an_invoice_numbers_it_and_books_the_sale_entry(): void
    {
        $invoice = $this->svc->createDraft([
            'lines' => [['label' => 'Bien', 'quantity' => 1, 'unit_price_minor' => 100000, 'tax_id' => $this->vat->id]],
        ], $this->tenant->id, $this->chief->id);

        $issued = $this->svc->issue($invoice, $this->chief->id);

        $this->assertStringStartsWith('FA-', $issued->number);
        $this->assertSame(Invoice::STATUS_ISSUED, $issued->status);
        $this->assertNotNull($issued->entry_id);

        $entry = Entry::withoutTenantScope()->where('id', $issued->entry_id)->with('lines.account')->first();
        $this->assertSame(Entry::STATUS_POSTED, $entry->status);
        $byCode = $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ['d' => (int) $l->debit_minor, 'c' => (int) $l->credit_minor]]);
        $this->assertSame(118000, $byCode['411']['d']);   // client TTC
        $this->assertSame(100000, $byCode['701']['c']);   // ventes HT
        $this->assertSame(18000, $byCode['4431']['c']);   // TVA collectée
        $this->assertSame($entry->totalDebit(), $entry->totalCredit());
    }

    #[Test]
    public function an_issued_invoice_cannot_be_reissued(): void
    {
        $invoice = $this->svc->issue($this->svc->createDraft([
            'lines' => [['label' => 'X', 'quantity' => 1, 'unit_price_minor' => 5000]],
        ], $this->tenant->id, $this->chief->id), $this->chief->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->issue($invoice, $this->chief->id);
    }

    #[Test]
    public function allocating_payments_updates_status_and_books_collection_entries(): void
    {
        $customer = Customer::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'name' => 'C', 'email' => 'c@fact.sn']);
        $invoice  = $this->svc->issue($this->svc->createDraft([
            'customer_id' => $customer->id,
            'lines' => [['label' => 'Bien', 'quantity' => 1, 'unit_price_minor' => 100000]],  // 100 000 HT, pas de TVA
        ], $this->tenant->id, $this->chief->id), $this->chief->id);
        $this->assertSame(100000, $invoice->total_minor);

        // Paiement partiel 60 000 → partially_paid + écriture 571/411.
        $p1 = Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 60000, 'currency' => 'XOF', 'method' => 'cash', 'paid_at' => now()]);
        $this->svc->allocatePayment($p1, $invoice, 60000, $this->chief->id);
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertSame(60000, $invoice->paid_minor);

        // Solde 40 000 → paid.
        $p2 = Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 40000, 'currency' => 'XOF', 'method' => 'mobile_money', 'paid_at' => now()]);
        $this->svc->allocatePayment($p2, $invoice, 40000, $this->chief->id);
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);

        // Deux écritures d'encaissement (CA + BQ), chacune créditant 411.
        $collections = Entry::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('rule_code', 'payment.allocated')->count();
        $this->assertSame(2, $collections);
    }

    #[Test]
    public function an_allocation_exceeding_the_remaining_balance_is_rejected(): void
    {
        $invoice = $this->svc->issue($this->svc->createDraft([
            'lines' => [['label' => 'X', 'quantity' => 1, 'unit_price_minor' => 50000]],
        ], $this->tenant->id, $this->chief->id), $this->chief->id);

        $p = Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 90000, 'currency' => 'XOF', 'method' => 'cash', 'paid_at' => now()]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->allocatePayment($p, $invoice, 90000, $this->chief->id); // > 50 000 dus
    }

    // ── HTTP / RBAC ──────────────────────────────────────────────────────────

    #[Test]
    public function the_full_lifecycle_works_over_http(): void
    {
        $created = $this->withHeaders($this->auth())->postJson('/api/accounting/invoices', [
            'customer_name' => 'ACME',
            'lines' => [['label' => 'Service', 'quantity' => 3, 'unit_price_minor' => 20000, 'tax_id' => $this->vat->id]],
        ])->assertStatus(201)->json('data');

        $this->assertSame(70800, $created['total_minor']); // 60 000 HT + 10 800 TVA

        $issued = $this->withHeaders($this->auth())->postJson("/api/accounting/invoices/{$created['id']}/issue")
            ->assertOk()->json('data');
        $this->assertStringStartsWith('FA-', $issued['number']);

        $this->withHeaders($this->auth())->getJson('/api/accounting/invoices?status=issued')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_cashier_cannot_create_invoices(): void
    {
        $cashier = User::create(['name' => 'Ca', 'email' => 'ca@fact.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $cashier->assignTenantRole('cashier');

        $this->withHeaders(['Authorization' => 'Bearer ' . $cashier->createToken('api')->plainTextToken])
            ->postJson('/api/accounting/invoices', ['lines' => [['label' => 'X', 'quantity' => 1, 'unit_price_minor' => 1000]]])
            ->assertStatus(403);
    }
}
