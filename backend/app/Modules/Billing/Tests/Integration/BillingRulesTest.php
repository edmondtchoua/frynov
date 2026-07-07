<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\MarketPaymentMethod;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Billing\Services\TenantCreditService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6G — les 6 règles billing configurables (arbitrage : « tout, mais configurable »).
 * Prix seedés (essential/waemu) : mensuel 990 000 XOF, annuel 9 900 000, siège +250 000/mois.
 */
class BillingRulesTest extends TestCase
{
    use RefreshDatabase;

    private ManualPaymentService $svc;
    private Tenant $tenant;
    private User $admin;
    private Plan $essential;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(PlansSeeder::class);

        $this->svc       = app(ManualPaymentService::class);
        $this->essential = Plan::where('code', Plan::CODE_ESSENTIAL)->firstOrFail();
        $this->tenant    = Tenant::create(['name' => 'Rules', 'slug' => 'rules-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->admin     = User::create(['name' => 'SA', 'email' => 'sa@rules.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_super_admin' => true]);
    }

    private function submit(int $amount, ?string $declared = null, ?string $promo = null, string $method = 'mobile_money', string $currency = 'XOF'): ManualPayment
    {
        return $this->svc->submit($this->tenant, $this->essential, $amount, $currency, $method, null, null, $promo, null, $declared);
    }

    private function sub(): ?Subscription
    {
        return Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)
            ->where('status', '!=', Subscription::STATUS_CANCELLED)
            ->latest()->first();
    }

    #[Test]
    public function a_valid_promo_resolves_to_the_net_target_and_activates(): void
    {
        Promotion::create([
            'code' => 'MOITIE', 'discount_type' => 'percent', 'discount_value' => 50,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(), 'is_active' => true,
        ]);

        // 50 % de 990 000 = 495 000 → matched net + abonnement actif + usage promo enregistré.
        $mp = $this->submit(495000, 'monthly', 'MOITIE');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('matched', $mp->fresh()->resolution_status);
        $this->assertSame('active', $this->sub()->status);
        $this->assertDatabaseHas('promo_uses', ['tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function an_invalid_promo_still_routes_to_needs_review(): void
    {
        Promotion::create([
            'code' => 'FINI', 'discount_type' => 'percent', 'discount_value' => 50,
            'valid_from' => now()->subMonth(), 'valid_until' => now()->subDay(), 'is_active' => true, // expirée
        ]);

        $mp = $this->submit(495000, 'monthly', 'FINI');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('needs_review', $mp->fresh()->resolution_status);
        $this->assertNull($this->sub());
    }

    #[Test]
    public function extra_user_seats_are_detected_in_the_amount(): void
    {
        // base 990 000 + 2 sièges × 250 000 = 1 490 000 → matched avec 2 sièges.
        $mp = $this->submit(1490000, 'monthly');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('matched', $mp->fresh()->resolution_status);
        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame(2, $sub->metadata['extra_users'] ?? null);
    }

    #[Test]
    public function a_currency_mismatching_the_payment_method_needs_review(): void
    {
        MarketPaymentMethod::create([
            'market_code' => 'waemu', 'currency' => 'XOF', 'method' => 'orange_money',
            'mode' => 'mobile_money', 'is_active' => true, 'display_order' => 1, 'label' => 'Orange Money',
        ]);

        // Paiement en EUR via un moyen déclaré XOF → approuvé SANS activation.
        $mp = $this->submit(700, 'monthly', null, 'orange_money', 'EUR');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('needs_review', $mp->fresh()->resolution_status);
        $this->assertSame(ManualPayment::STATUS_APPROVED, $mp->fresh()->status);
        $this->assertNull($this->sub());
    }

    #[Test]
    public function successive_deposits_top_up_the_same_past_due_subscription_in_place(): void
    {
        $d1 = $this->submit(300000, 'monthly');
        $this->svc->approve($d1, $this->admin);
        $d2 = $this->submit(300000, 'monthly');
        $this->svc->approve($d2, $this->admin);

        // RC-6G (règle 5) : UNE seule ligne d'abonnement (pas d'annulé par tranche), cumul abondé.
        $all = Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)->get();
        $this->assertCount(1, $all);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $all->first()->status);
        $this->assertSame(600000, $all->first()->amount_paid_minor);
    }

    #[Test]
    public function rejecting_an_applied_deposit_reverses_its_cash_from_the_cycle(): void
    {
        $d1 = $this->submit(300000, 'monthly');
        $this->svc->approve($d1, $this->admin);
        $d2 = $this->submit(200000, 'monthly');
        $this->svc->approve($d2, $this->admin);
        $this->assertSame(500000, $this->sub()->amount_paid_minor);

        // RC-6G (règle 6) : rétro-action d'un acompte imputé NON soldé → cumul décrémenté.
        $this->svc->reject($d2->fresh(), $this->admin, 'Virement rejeté par la banque');

        $this->assertSame(ManualPayment::STATUS_REJECTED, $d2->fresh()->status);
        $this->assertSame(300000, $this->sub()->fresh()->amount_paid_minor);
    }

    #[Test]
    public function a_deposit_in_another_currency_never_tops_up_the_previous_markets_deposit(): void
    {
        // Recette QA — un acompte EUR ne doit JAMAIS abonder « en place » le dépôt XOF existant
        // (avant correctif, existingDeposit ignorait market_code : le dépôt waemu de 300 000 XOF
        // aurait été écrasé à 100 en devenant europe — perte financière directe).
        $xof = $this->submit(300000, 'monthly');                     // waemu (XOF) → dépôt A
        $this->svc->approve($xof, $this->admin);
        $eur = $this->submit(100, 'monthly', null, 'card', 'EUR');   // europe (EUR)
        $this->svc->approve($eur, $this->admin);

        // Le dépôt courant est bien europe/100 (mono-abonnement : changePlan a remplacé le waemu) —
        // jamais un montant mélangé 300100 ni un waemu réétiqueté europe.
        $current = Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('status', Subscription::STATUS_PAST_DUE)->firstOrFail();
        $this->assertSame('europe', $current->market_code);
        $this->assertSame(100, $current->amount_paid_minor);
        $this->assertSame(0, Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('market_code', 'waemu')->where('status', Subscription::STATUS_PAST_DUE)->count());
    }

    #[Test]
    public function rejecting_a_payment_of_a_settled_cycle_stays_forbidden(): void
    {
        $mp = $this->submit(990000, 'monthly'); // solde → actif
        $this->svc->approve($mp, $this->admin);

        $this->expectException(\RuntimeException::class);
        $this->svc->reject($mp->fresh(), $this->admin, 'tentative');
    }

    #[Test]
    public function credit_consumption_is_capped_at_the_balance(): void
    {
        $credits = app(TenantCreditService::class);
        $credits->credit($this->tenant->id, 'XOF', 80000, 'overpaid', 'test');

        $this->assertSame(50000, $credits->consume($this->tenant->id, 'XOF', 50000, 'conso-1'));
        $this->assertSame(30000, $credits->consume($this->tenant->id, 'XOF', 90000, 'conso-2')); // borné
        $this->assertSame(0, $credits->balance($this->tenant->id, 'XOF'));
    }

    // ── RC-17 (M-1) — le ledger n'est plus en écriture seule : les avoirs sont RÉAPPLIQUÉS ────────

    #[Test]
    public function an_available_ledger_credit_completes_a_partial_payment_and_is_consumed(): void
    {
        // Avoir de 200 000 XOF (ex. trop-perçu d'un cycle antérieur).
        app(TenantCreditService::class)->credit($this->tenant->id, 'XOF', 200000, 'overpaid', 'ancien-cycle');

        // Cash 790 000 < cible 990 000, mais cash + avoir = exactement la cible → activation.
        $mp = $this->submit(790000, 'monthly');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('matched', $mp->fresh()->resolution_status);
        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame(790000, $sub->amount_paid_minor);                       // cash réel, hors avoir
        $this->assertSame(200000, $sub->metadata['ledger_credit_applied_minor'] ?? null);

        // L'avoir est consommé : solde 0 + ligne négative référencée au paiement.
        $this->assertSame(0, app(TenantCreditService::class)->balance($this->tenant->id, 'XOF'));
        $this->assertDatabaseHas('tenant_credits', [
            'tenant_id' => $this->tenant->id, 'amount_minor' => -200000, 'reference' => $mp->id,
        ]);
    }

    #[Test]
    public function a_ledger_credit_that_cannot_settle_the_target_stays_intact(): void
    {
        // Avoir 100 000 ; cash 500 000 → 600 000 < 990 000 : acompte partiel, avoir NON consommé
        // (même règle que la proration : pas de consommation partielle en dépôt).
        app(TenantCreditService::class)->credit($this->tenant->id, 'XOF', 100000, 'overpaid', 'x');

        $mp = $this->submit(500000, 'monthly');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('partial', $mp->fresh()->resolution_status);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $this->sub()->status);
        $this->assertSame(100000, app(TenantCreditService::class)->balance($this->tenant->id, 'XOF'));
    }

    #[Test]
    public function a_ledger_credit_in_another_currency_is_never_applied(): void
    {
        // Un avoir EUR ne solde JAMAIS une cible XOF (l'avoir ne franchit pas les devises).
        app(TenantCreditService::class)->credit($this->tenant->id, 'EUR', 200000, 'overpaid', 'x');

        $mp = $this->submit(790000, 'monthly');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('partial', $mp->fresh()->resolution_status);
        $this->assertSame(200000, app(TenantCreditService::class)->balance($this->tenant->id, 'EUR'));
    }

    #[Test]
    public function applying_a_promo_in_the_ui_does_not_burn_its_usage_before_activation(): void
    {
        // RC-18 (M-5) — `POST /api/me/promo/apply` consommait l'usage immédiatement : à l'approbation,
        // validate() voyait « déjà utilisé » → paiement légitime routé needs_review. L'usage n'est
        // désormais enregistré qu'à l'ACTIVATION.
        Promotion::create([
            'code' => 'DEMI', 'discount_type' => 'percent', 'discount_value' => 50,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(), 'is_active' => true,
        ]);
        $this->admin->assignTenantRole('admin');
        $token = $this->admin->createToken('api')->plainTextToken;

        // L'utilisateur « applique » le code dans l'UI (validation + rappel de remise)…
        // NB : le SPA envoie X-Tenant-Slug sur chaque requête — c'est lui qui scope le rôle admin.
        $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Tenant-Slug' => $this->tenant->slug])
            ->postJson('/api/me/promo/apply', ['code' => 'DEMI', 'plan_code' => Plan::CODE_ESSENTIAL])
            ->assertOk();

        // …aucun usage n'est consommé à ce stade.
        $this->assertDatabaseCount('promo_uses', 0);

        // Le paiement net de promo est ensuite approuvé : matched + usage consommé UNE fois.
        $mp = $this->submit(495000, 'monthly', 'DEMI');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('matched', $mp->fresh()->resolution_status);
        $this->assertSame('active', $this->sub()->status);
        $this->assertDatabaseCount('promo_uses', 1);
    }

    #[Test]
    public function an_overpayment_credits_the_ledger_then_settles_the_next_cycle(): void
    {
        // Cycle 1 : sur-paiement AU-DELÀ de la plus grande cible (annuel 9 900 000) — c'est le seul
        // cas 'overpaid' par conception RC-6G (entre mensuel et annuel = acompte vers l'annuel).
        // 10 100 000 → activation annuelle + avoir 200 000 au ledger.
        $mp1 = $this->submit(10100000, 'monthly');
        $this->svc->approve($mp1, $this->admin);
        $this->assertSame('overpaid', $mp1->fresh()->resolution_status);
        $this->assertSame(200000, app(TenantCreditService::class)->balance($this->tenant->id, 'XOF'));

        // Cycle suivant : le client ne vire que 790 000 — l'avoir comble l'écart vers la cible
        // mensuelle (990 000) → activation, solde consommé à 0. (Avant RC-17 : partial + avoir perdu.)
        $mp2 = $this->submit(790000, 'monthly');
        $this->svc->approve($mp2, $this->admin);

        $this->assertSame('matched', $mp2->fresh()->resolution_status);
        $this->assertSame(0, app(TenantCreditService::class)->balance($this->tenant->id, 'XOF'));
    }
}
