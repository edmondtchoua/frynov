<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\AccountingSettings;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * RC-23 — provisionnement du référentiel comptable SYSCOHADA d'un tenant.
 *
 * IDEMPOTENT (updateOrCreate partout) : rejouable sans doublon. Seed un sous-ensemble
 * représentatif du plan SYSCOHADA révisé — les comptes requis par le moteur d'imputation sont
 * marqués `is_system` ; le tenant enrichit ensuite librement son plan depuis l'écran dédié.
 */
class ChartOfAccountsProvisioner
{
    /** Plan par défaut : [classe, code, nom, kind, system]. */
    private const DEFAULT_ACCOUNTS = [
        // Classe 1 — capitaux
        [1, '101', 'Capital social',                    Account::KIND_EQUITY,    false],
        [1, '106', 'Réserves',                          Account::KIND_EQUITY,    false],
        [1, '11',  'Report à nouveau',                  Account::KIND_EQUITY,    true],
        [1, '13',  'Résultat net de l\'exercice',       Account::KIND_EQUITY,    true],
        [1, '16',  'Emprunts et dettes assimilées',     Account::KIND_LIABILITY, false],
        // Classe 2 — immobilisations
        [2, '21',  'Immobilisations incorporelles',     Account::KIND_ASSET,     false],
        [2, '24',  'Matériel',                          Account::KIND_ASSET,     false],
        [2, '28',  'Amortissements',                    Account::KIND_ASSET,     false],
        // Classe 3 — stocks
        [3, '31',  'Marchandises',                      Account::KIND_ASSET,     true],
        [3, '36',  'Produits finis',                    Account::KIND_ASSET,     false],
        [3, '39',  'Dépréciations des stocks',          Account::KIND_ASSET,     false],
        // Classe 4 — tiers
        [4, '401', 'Fournisseurs',                      Account::KIND_LIABILITY, true],
        [4, '409', 'Fournisseurs débiteurs',            Account::KIND_ASSET,     false],
        [4, '411', 'Clients',                           Account::KIND_ASSET,     true],
        [4, '419', 'Clients créditeurs (avoirs)',       Account::KIND_LIABILITY, true],
        [4, '4431', 'TVA facturée (collectée)',         Account::KIND_LIABILITY, true],
        [4, '4452', 'TVA récupérable (déductible)',     Account::KIND_ASSET,     true],
        [4, '447', 'Autres impôts et taxes',            Account::KIND_LIABILITY, false],
        [4, '471', 'Comptes d\'attente',                Account::KIND_ASSET,     true],
        [4, '476', 'Charges constatées d\'avance',      Account::KIND_ASSET,     false],
        [4, '477', 'Produits constatés d\'avance',      Account::KIND_LIABILITY, false],
        // Classe 5 — trésorerie
        [5, '521', 'Banques',                           Account::KIND_ASSET,     true],
        [5, '571', 'Caisse',                            Account::KIND_ASSET,     true],
        [5, '585', 'Monnaie électronique (mobile money)', Account::KIND_ASSET,   true],
        // Classe 6 — charges
        [6, '601', 'Achats de marchandises',            Account::KIND_EXPENSE,   true],
        [6, '603', 'Variations des stocks',             Account::KIND_EXPENSE,   true],
        [6, '61',  'Transports',                        Account::KIND_EXPENSE,   false],
        [6, '62',  'Services extérieurs A',             Account::KIND_EXPENSE,   false],
        [6, '63',  'Services extérieurs B',             Account::KIND_EXPENSE,   false],
        [6, '64',  'Impôts et taxes',                   Account::KIND_EXPENSE,   false],
        [6, '658', 'Charges diverses (écarts de caisse)', Account::KIND_EXPENSE, true],
        [6, '66',  'Charges de personnel',              Account::KIND_EXPENSE,   false],
        // Classe 7 — produits
        [7, '701', 'Ventes de marchandises',            Account::KIND_REVENUE,   true],
        [7, '706', 'Services vendus',                   Account::KIND_REVENUE,   true],
        [7, '707', 'Produits accessoires',              Account::KIND_REVENUE,   false],
        [7, '758', 'Produits divers (écarts de caisse)', Account::KIND_REVENUE,  true],
        // Classe 8 — autres charges/produits (HAO)
        [8, '81',  'Valeurs comptables des cessions',   Account::KIND_EXPENSE,   false],
        [8, '82',  'Produits des cessions',             Account::KIND_REVENUE,   false],
    ];

    /** Journaux par défaut : [code, nom, type]. */
    private const DEFAULT_JOURNALS = [
        ['VT', 'Journal des ventes',              Journal::TYPE_SALES],
        ['AC', 'Journal des achats',              Journal::TYPE_PURCHASES],
        ['CA', 'Journal de caisse',               Journal::TYPE_CASH],
        ['BQ', 'Journal de banque',               Journal::TYPE_BANK],
        ['OD', 'Opérations diverses',             Journal::TYPE_MISC],
        ['ST', 'Journal de stock',                Journal::TYPE_STOCK],
        ['AV', 'Journal des avoirs',              Journal::TYPE_CREDIT_NOTES],
        ['RG', 'Journal des régularisations',     Journal::TYPE_ADJUSTMENTS],
    ];

    public function __construct(private readonly AuditService $audit) {}

    /** Le tenant est-il déjà provisionné ? */
    public function isProvisioned(string $tenantId): bool
    {
        return AccountingSettings::withoutTenantScope()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Provisionne (ou complète — idempotent) le référentiel comptable du tenant :
     * plan de comptes, journaux, TVA locale, paramètres, exercice courant + périodes mensuelles.
     */
    public function provision(Tenant $tenant, ?string $userId = null): AccountingSettings
    {
        return DB::transaction(function () use ($tenant, $userId) {
            $tid      = $tenant->id;
            $settings = $tenant->settings ?? [];
            $currency = $settings['currency'] ?? 'XOF';
            $country  = strtoupper((string) ($settings['country'] ?? '')) ?: null;

            // 1. Plan de comptes
            foreach (self::DEFAULT_ACCOUNTS as [$class, $code, $name, $kind, $system]) {
                Account::withoutTenantScope()->updateOrCreate(
                    ['tenant_id' => $tid, 'code' => $code],
                    [
                        'class_code' => $class, 'name' => $name, 'kind' => $kind,
                        'is_system' => $system, 'is_active' => true, 'created_by' => $userId,
                    ],
                );
            }

            // 2. Journaux (préfixe de séquence = CODE + année, ex. VT26)
            $yearSuffix = now()->format('y');
            foreach (self::DEFAULT_JOURNALS as [$code, $name, $type]) {
                Journal::withoutTenantScope()->updateOrCreate(
                    ['tenant_id' => $tid, 'code' => $code],
                    [
                        'name' => $name, 'type' => $type,
                        'sequence_prefix' => $code . $yearSuffix,
                        'is_system' => true, 'is_active' => true, 'created_by' => $userId,
                    ],
                );
            }

            // 3. TVA locale (taux standard UEMOA/CEMAC ; désactivable par le tenant)
            $rate = self::defaultVatRateBp($country);
            if ($rate !== null) {
                $collected  = Account::withoutTenantScope()->where('tenant_id', $tid)->where('code', '4431')->first();
                $deductible = Account::withoutTenantScope()->where('tenant_id', $tid)->where('code', '4452')->first();
                Tax::withoutTenantScope()->updateOrCreate(
                    ['tenant_id' => $tid, 'code' => 'TVA' . intdiv($rate, 100)],
                    [
                        'name' => 'TVA ' . ($rate / 100) . ' %', 'rate_bp' => $rate,
                        'collected_account_id' => $collected?->id,
                        'deductible_account_id' => $deductible?->id,
                        'is_inclusive' => false, 'country' => $country, 'is_active' => true,
                        'created_by' => $userId,
                    ],
                );
            }

            // 4. Exercice courant + périodes mensuelles
            $fyStartMonth = 1;
            $fyStart = now()->startOfYear();
            $fyEnd   = now()->endOfYear()->startOfDay();
            $fy = FiscalYear::withoutTenantScope()->updateOrCreate(
                ['tenant_id' => $tid, 'label' => $fyStart->format('Y')],
                ['starts_on' => $fyStart->toDateString(), 'ends_on' => $fyEnd->toDateString(), 'status' => FiscalYear::STATUS_OPEN],
            );
            for ($m = 0; $m < 12; $m++) {
                $pStart = $fyStart->copy()->addMonths($m);
                AccountingPeriod::withoutTenantScope()->updateOrCreate(
                    ['tenant_id' => $tid, 'fiscal_year_id' => $fy->id, 'label' => $pStart->format('Y-m')],
                    [
                        'starts_on' => $pStart->toDateString(),
                        'ends_on'   => $pStart->copy()->endOfMonth()->startOfDay()->toDateString(),
                        'status'    => AccountingPeriod::STATUS_OPEN,
                    ],
                );
            }

            // 5. Paramètres (comptes par défaut = références symboliques du moteur d'imputation)
            $accountingSettings = AccountingSettings::withoutTenantScope()->updateOrCreate(
                ['tenant_id' => $tid],
                [
                    'country' => $country, 'currency' => $currency,
                    'fiscal_year_start_month' => $fyStartMonth,
                    'numbering_rules' => ['invoice' => 'FA', 'proforma' => 'PF', 'credit_note' => 'AV'],
                    'default_accounts' => [
                        '@cash' => '571', '@bank' => '521', '@mobile_money' => '585',
                        '@customers' => '411', '@customer_credits' => '419', '@suppliers' => '401',
                        '@sales' => '701', '@services' => '706', '@purchases' => '601',
                        '@tax_collected' => '4431', '@tax_deductible' => '4452',
                        '@suspense' => '471', '@cash_short' => '658', '@cash_over' => '758',
                        '@stock' => '31', '@stock_variation' => '603',
                    ],
                    'auto_post' => false,
                    'created_by' => $userId,
                ],
            );

            $this->audit->log(
                action: 'accounting.provisioned',
                tenantId: $tid,
                userId: $userId,
                subject: $accountingSettings,
                newValues: ['accounts' => count(self::DEFAULT_ACCOUNTS), 'journals' => count(self::DEFAULT_JOURNALS)],
            );

            return $accountingSettings;
        });
    }

    /** Taux de TVA standard (points de base) par pays — null si inconnu (tenant paramètre lui-même). */
    public static function defaultVatRateBp(?string $country): ?int
    {
        return match ($country) {
            'SN', 'BJ', 'BF', 'ML', 'GW', 'TG' => 1800,   // UEMOA 18 %
            'CI'                                => 1800,
            'NE'                                => 1900,
            'CM', 'TD', 'CF', 'CG', 'GQ', 'GA' => 1925,   // CEMAC 19,25 % (Cameroun incl. CAC)
            'GN'                                => 1800,
            'CD'                                => 1600,
            default                             => 1800,   // défaut OHADA raisonnable, modifiable
        };
    }
}
