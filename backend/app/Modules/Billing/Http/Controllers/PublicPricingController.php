<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\MarketPaymentMethod;
use App\Modules\Billing\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PublicPricingController extends Controller
{
    private const MARKETS = [
        'waemu' => [
            'label' => 'UEMOA',
            'currency' => 'XOF',
            'countries' => ['SN', 'CI', 'ML', 'BF', 'BJ', 'TG', 'NE', 'GW'],
        ],
        'cemac' => [
            'label' => 'CEMAC',
            'currency' => 'XAF',
            'countries' => ['CM', 'GA', 'CG', 'TD', 'CF', 'GQ'],
        ],
        'nigeria' => ['label' => 'Nigeria', 'currency' => 'NGN', 'countries' => ['NG']],
        'ghana' => ['label' => 'Ghana', 'currency' => 'GHS', 'countries' => ['GH']],
        'kenya' => ['label' => 'Kenya', 'currency' => 'KES', 'countries' => ['KE']],
        'south_africa' => ['label' => 'Afrique du Sud', 'currency' => 'ZAR', 'countries' => ['ZA']],
        'europe' => ['label' => 'Europe', 'currency' => 'EUR', 'countries' => ['FR', 'BE', 'ES', 'DE', 'IT', 'NL', 'PT']],
        'canada' => ['label' => 'Canada', 'currency' => 'CAD', 'countries' => ['CA']],
        'usa' => ['label' => 'USA', 'currency' => 'USD', 'countries' => ['US']],
        'global' => ['label' => 'International', 'currency' => 'USD', 'countries' => []],
    ];

    /**
     * GET /api/public/geo — visitor country resolved from the CDN/edge layer only.
     *
     * Privacy-first: the visitor's IP never leaves our infrastructure (no third-party
     * geolocation call). Returns the ISO-3166 alpha-2 country from an edge header when
     * present, else null — the frontend then falls back to locale-based detection.
     */
    public function geo(Request $request): JsonResponse
    {
        return response()->json(['country_code' => $this->countryFromEdge($request)]);
    }

    private function countryFromEdge(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'X-AppEngine-Country', 'X-Country-Code'] as $header) {
            $val = strtoupper((string) $request->headers->get($header));
            // Reject placeholders the edges emit for unknown/anonymized origins.
            if (preg_match('/^[A-Z]{2}$/', $val) && ! in_array($val, ['XX', 'T1', 'ZZ'], true)) {
                return $val;
            }
        }

        return null;
    }

    /** Périodicités d'abonnement exposées publiquement (annuel = mensuel ×10, seedé). */
    private const INTERVALS = ['monthly', 'yearly'];

    public function index(Request $request): JsonResponse
    {
        // Whitelist : mensuel ou annuel ; toute autre valeur retombe sur le mensuel.
        $interval = $request->string('interval', 'monthly')->lower()->toString();
        if (! in_array($interval, self::INTERVALS, true)) {
            $interval = 'monthly';
        }

        [$marketCode, $source] = $this->resolveMarket(
            $request->query('market'),
            $request->query('country'),
        );
        $market = self::MARKETS[$marketCode];

        // On charge les DEUX périodicités du marché : le mensuel sert de référence pour
        // calculer l'équivalent mensuel et l'économie affichés sur l'offre annuelle.
        $plans = Plan::query()
            ->with(['limits', 'prices' => fn ($query) => $query
                ->whereIn('interval', self::INTERVALS)
                ->where('is_public', true)
                ->whereIn('market_code', [$marketCode, 'global'])])
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => $this->serializePlan($plan, $marketCode, $interval))
            ->values();

        return response()->json([
            'market' => [
                'code' => $marketCode,
                'label' => $market['label'],
                'currency' => $market['currency'],
                'source' => $source,
                'country' => $this->normalizeCountry($request->query('country')),
            ],
            'interval' => $interval,
            'selectable_markets' => $this->selectableMarkets(),
            'data' => $plans,
        ]);
    }

    /**
     * GET /api/public/payment-methods — moyens de paiement disponibles par marché (P6-1).
     *
     * Résout le marché comme /public/pricing (param `market`|`country`, repli `global`), puis
     * renvoie les moyens du marché triés par `display_order`. Chaque moyen porte un `mode` :
     * `auto` (rail PSP réel), `manual` (preuve + validation admin via ManualPayment) ou
     * `quote` (sur devis). À ce stade tout est manual/quote — aucun rail réel branché.
     * Matérialise le DoD : chaque devise affichée renvoie ≥1 moyen (flux OU mention).
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        [$marketCode, $source] = $this->resolveMarket(
            $request->query('market'),
            $request->query('country'),
        );
        $market = self::MARKETS[$marketCode];

        $methods = MarketPaymentMethod::query()
            ->where('is_active', true)
            ->where('market_code', $marketCode)
            ->whereNull('country_code')
            ->orderBy('display_order')
            ->get()
            ->map(fn (MarketPaymentMethod $m) => [
                'method'   => $m->method,
                'mode'     => $m->mode,
                'currency' => $m->currency,
                'label'    => $m->label,
            ])
            ->values();

        return response()->json([
            'market' => [
                'code'     => $marketCode,
                'label'    => $market['label'],
                'currency' => $market['currency'],
                'source'   => $source,
            ],
            // Vrai dès qu'au moins un moyen est un rail automatique (faux à ce stade — DoD via mention).
            'has_auto' => $methods->contains(fn ($m) => $m['mode'] === MarketPaymentMethod::MODE_AUTO),
            'data'     => $methods,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveMarket(mixed $market, mixed $country): array
    {
        $marketCode = is_string($market) ? strtolower(trim($market)) : null;
        if ($marketCode && array_key_exists($marketCode, self::MARKETS)) {
            return [$marketCode, 'market'];
        }

        $countryCode = $this->normalizeCountry($country);
        if ($countryCode) {
            foreach (self::MARKETS as $code => $definition) {
                if (in_array($countryCode, $definition['countries'], true)) {
                    return [$code, 'country'];
                }
            }
        }

        return ['global', 'fallback'];
    }

    private function normalizeCountry(mixed $country): ?string
    {
        if (! is_string($country)) {
            return null;
        }

        $country = strtoupper(trim($country));

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }

    /**
     * @return array<int, array{code:string,label:string,currency:string,countries:array<int,string>}>
     */
    private function selectableMarkets(): array
    {
        return collect(self::MARKETS)
            ->map(fn (array $definition, string $code) => [
                'code' => $code,
                'label' => $definition['label'],
                'currency' => $definition['currency'],
                'countries' => $definition['countries'],
            ])
            ->values()
            ->all();
    }

    /**
     * Économie de l'offre annuelle vs 12 mensualités. Renvoie un tableau vide pour le mensuel
     * (rien à comparer). `monthly_equivalent_minor` = ce que coûte « par mois » l'offre annuelle ;
     * `savings_*` = ce qu'on évite par rapport à 12× le tarif mensuel.
     *
     * @param  \App\Modules\Billing\Models\PlanPrice  $price    prix de la période demandée
     * @param  ?\App\Modules\Billing\Models\PlanPrice $monthly  prix mensuel de référence (peut être null)
     * @return array<string, int>
     */
    private function intervalEconomics(string $interval, $price, $monthly): array
    {
        if ($interval !== 'yearly') {
            return [];
        }

        $yearly             = (int) $price->base_amount_minor;
        $monthlyEquivalent  = (int) round($yearly / 12);
        $annualizedMonthly  = $monthly ? (int) $monthly->base_amount_minor * 12 : 0;
        $savingsAmount      = max(0, $annualizedMonthly - $yearly);
        $savingsPct         = $annualizedMonthly > 0
            ? (int) round($savingsAmount / $annualizedMonthly * 100)
            : 0;

        return [
            'monthly_equivalent_minor' => $monthlyEquivalent,
            'savings_amount_minor'     => $savingsAmount,
            'savings_pct'              => $savingsPct,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(Plan $plan, string $marketCode, string $interval): array
    {
        // Prix du marché demandé, sinon repli sur le marché 'global'. On garde les deux
        // périodicités pour ce marché afin de calculer l'économie annuelle.
        $marketPrices = $plan->prices->where('market_code', $marketCode);
        if ($marketPrices->isEmpty()) {
            $marketPrices = $plan->prices->where('market_code', 'global');
        }

        $price   = $marketPrices->firstWhere('interval', $interval);
        $monthly = $marketPrices->firstWhere('interval', 'monthly');

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'trial_days' => $plan->trial_days,
            'features' => $plan->features ?? [],
            'sort_order' => $plan->sort_order,
            'price' => $price ? array_merge([
                'market_code' => $price->market_code,
                'currency' => $price->currency,
                'interval' => $interval,
                'base_amount_minor' => $price->base_amount_minor,
                'included_users' => $price->included_users,
                'extra_user_amount_minor' => $price->extra_user_amount_minor,
            ], $this->intervalEconomics($interval, $price, $monthly)) : null,
            'limits' => $plan->limits ? [
                'max_products' => $plan->limits->max_products,
                'max_monthly_orders' => $plan->limits->max_monthly_orders,
                'max_customers' => $plan->limits->max_customers,
                'max_branches' => $plan->limits->max_branches,
                'max_warehouses' => $plan->limits->max_warehouses,
                'max_imports_per_month' => $plan->limits->max_imports_per_month,
                'max_api_calls_per_month' => $plan->limits->max_api_calls_per_month,
                'storage_mb' => $plan->limits->storage_mb,
            ] : null,
        ];
    }
}
