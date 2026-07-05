<?php

namespace App\Modules\Accounting\Tests\Integration;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSettings;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\OutboxEvent;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Accounting\Services\ImputationEngine;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Pos\Services\PosService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\AccountingClassesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-26 — moteur d'imputation branché sur le POS via événements + outbox idempotente.
 * Vérifie l'écriture générée par vente (split), remboursement, écart de clôture, mouvement,
 * et l'ABSENCE de doublon au rejeu (source unique).
 */
class AccountingImputationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $cashier;
    private Product $product;
    private PosService $pos;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'cashier'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->seed(AccountingClassesSeeder::class);

        $this->tenant  = Tenant::create(['name' => 'Imp SARL', 'slug' => 'imp-sarl', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'country' => 'SN']]);
        $this->cashier = User::create(['name' => 'C', 'email' => 'c@imp.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->cashier->assignTenantRole('cashier');

        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Boutique', 'code' => 'WH-1', 'is_default' => true]);
        $this->product = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'IMP-1', 'name' => 'Café', 'price_amount' => 25000, 'price_currency' => 'XOF', 'status' => 'active']);
        $stock = app(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null);
        app(StockService::class)->moveIn($stock, 100);

        // Comptabilité provisionnée + comptabilisation AUTOMATIQUE pour vérifier l'écriture postée.
        app(ChartOfAccountsProvisioner::class)->provision($this->tenant, $this->cashier->id);
        AccountingSettings::withoutTenantScope()->where('tenant_id', $this->tenant->id)->update(['auto_post' => true]);

        $this->pos = app(PosService::class);
    }

    private function openSession(int $float = 0): \App\Modules\Pos\Models\CashRegisterSession
    {
        return $this->pos->openSession(['label' => 'Caisse 1', 'opening_float_cents' => $float], $this->tenant->id, $this->cashier->id);
    }

    private function entryFor(string $sourceType, string $sourceId): ?Entry
    {
        return Entry::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->with('lines.account')->first();
    }

    #[Test]
    public function a_split_pos_sale_generates_a_balanced_sales_entry_debiting_each_tender(): void
    {
        $session = $this->openSession();
        $res = $this->pos->checkout($session, [
            'items'    => [['product_id' => $this->product->id, 'quantity' => 2]], // 50 000
            'payments' => [
                ['method' => 'cash',         'amount_cents' => 30000],
                ['method' => 'mobile_money', 'amount_cents' => 20000],
            ],
        ], $this->tenant->id, $this->cashier->id);

        $entry = $this->entryFor('Order', $res['order']->id);
        $this->assertNotNull($entry, 'une écriture de vente doit exister');
        $this->assertSame(Entry::STATUS_POSTED, $entry->status);
        $this->assertSame('pos.sale', $entry->rule_code);
        $this->assertSame(50000, $entry->totalDebit());
        $this->assertSame(50000, $entry->totalCredit());

        // Débits par trésorerie : caisse (571) 30 000, mobile money (585) 20 000 ; crédit ventes (701) 50 000.
        $byCode = $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ['d' => (int) $l->debit_minor, 'c' => (int) $l->credit_minor]]);
        $this->assertSame(30000, $byCode['571']['d']);
        $this->assertSame(20000, $byCode['585']['d']);
        $this->assertSame(50000, $byCode['701']['c']);
    }

    #[Test]
    public function replaying_the_same_sale_does_not_create_a_second_entry(): void
    {
        $session = $this->openSession();
        $payload = ['items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'method' => 'cash'];
        $key     = 'idem-sale-1';

        $this->pos->checkout($session, $payload, $this->tenant->id, $this->cashier->id, $key);
        // Rejeu (retry offline même clé) → renvoie la vente existante, PAS de 2e vente ni écriture.
        $this->pos->checkout($session, $payload, $this->tenant->id, $this->cashier->id, $key);

        $this->assertSame(1, OutboxEvent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('event_type', 'pos.sale')->count());
        $this->assertSame(1, Entry::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('rule_code', 'pos.sale')->count());
    }

    #[Test]
    public function a_cash_refund_generates_a_credit_note_entry(): void
    {
        $session = $this->openSession();
        $sale = $this->pos->checkout($session, ['items' => [['product_id' => $this->product->id, 'quantity' => 2]], 'method' => 'cash'], $this->tenant->id, $this->cashier->id);
        $lineId = $sale['order']->lines->first()->id;

        $refund = $this->pos->refundSale($session, $sale['order'], [['order_line_id' => $lineId, 'quantity' => 2, 'condition' => 'resalable']], 'Client insatisfait', $this->tenant->id, $this->cashier->id, 'cash');

        $entry = $this->entryFor('OrderReturn', $refund['return']->id);
        $this->assertNotNull($entry);
        $this->assertSame('pos.refund', $entry->rule_code);
        $byCode = $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ['d' => (int) $l->debit_minor, 'c' => (int) $l->credit_minor]]);
        $this->assertSame(50000, $byCode['701']['d']);   // annulation de la vente
        $this->assertSame(50000, $byCode['571']['c']);   // sortie de caisse

        // Le mouvement caisse (reason=refund) NE génère PAS d'écriture séparée (pas de doublon).
        $this->assertSame(0, OutboxEvent::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('event_type', 'cash.movement')->count());
    }

    #[Test]
    public function a_cash_shortage_at_closing_books_an_adjustment_entry(): void
    {
        $session = $this->openSession(10000);
        $this->pos->checkout($session, ['items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'method' => 'cash'], $this->tenant->id, $this->cashier->id);
        // Attendu = 10 000 + 25 000 = 35 000 ; compté 34 000 → manquant 1 000.
        $closed = $this->pos->closeSession($session->fresh(), ['counted_cash_cents' => 34000], $this->tenant->id, $this->cashier->id);

        $entry = $this->entryFor('CashRegisterSession', $closed->id);
        $this->assertNotNull($entry);
        $this->assertSame('pos.session_gap', $entry->rule_code);
        $byCode = $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ['d' => (int) $l->debit_minor, 'c' => (int) $l->credit_minor]]);
        $this->assertSame(1000, $byCode['658']['d']);   // charge (manquant)
        $this->assertSame(1000, $byCode['571']['c']);   // ajustement caisse
    }

    #[Test]
    public function a_float_top_up_movement_books_a_cash_entry(): void
    {
        $session = $this->openSession(0);
        $movement = $this->pos->recordCashMovement($session, ['direction' => 'in', 'amount_cents' => 5000, 'reason' => 'float_add'], $this->tenant->id, $this->cashier->id);

        $entry = $this->entryFor('CashMovement', $movement->id);
        $this->assertNotNull($entry);
        $this->assertSame('cash.movement', $entry->rule_code);
        $this->assertSame(5000, $entry->totalDebit());
    }

    #[Test]
    public function without_the_accounting_module_a_sale_produces_no_entry(): void
    {
        // Un tenant NON provisionné : la vente marche, aucune écriture (garde de l'engine).
        $other = Tenant::create(['name' => 'Sans compta', 'slug' => 'sans-compta', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF']]);
        $cashier = User::create(['name' => 'C2', 'email' => 'c2@sc.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $cashier->assignTenantRole('cashier');
        Warehouse::create(['tenant_id' => $other->id, 'name' => 'B', 'code' => 'WH', 'is_default' => true]);
        $prod = Product::create(['tenant_id' => $other->id, 'sku' => 'X', 'name' => 'X', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active']);
        app(StockService::class)->moveIn(app(StockService::class)->findOrCreate($other->id, $prod->id, null), 10);

        $session = $this->pos->openSession(['label' => 'C'], $other->id, $cashier->id);
        $this->pos->checkout($session, ['items' => [['product_id' => $prod->id, 'quantity' => 1]], 'method' => 'cash'], $other->id, $cashier->id);

        $this->assertSame(0, OutboxEvent::withoutTenantScope()->where('tenant_id', $other->id)->count());
        $this->assertSame(0, Entry::withoutTenantScope()->where('tenant_id', $other->id)->count());
    }
}
