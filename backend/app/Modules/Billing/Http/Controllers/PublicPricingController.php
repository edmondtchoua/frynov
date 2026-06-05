<?php

namespace App\Modules\Billing\Http\Controllers;

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

    public function index(Request $request): JsonResponse
    {
        $interval = $request->string('interval', 'monthly')->lower()->toString();
        if ($interval !== 'monthly') {
            $interval = 'monthly';
        }

        [$marketCode, $source] = $this->resolveMarket(
            $request->query('market'),
            $request->query('country'),
        );
        $market = self::MARKETS[$marketCode];

        $plans = Plan::query()
            ->with(['limits', 'prices' => fn ($query) => $query
                ->where('interval', $interval)
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
            'selectable_markets' => $this->selectableMarkets(),
            'data' => $plans,
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
     * @return array<string, mixed>
     */
    private function serializePlan(Plan $plan, string $marketCode, string $interval): array
    {
        $price = $plan->prices->firstWhere('market_code', $marketCode)
            ?? $plan->prices->firstWhere('market_code', 'global');

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'trial_days' => $plan->trial_days,
            'features' => $plan->features ?? [],
            'sort_order' => $plan->sort_order,
            'price' => $price ? [
                'market_code' => $price->market_code,
                'currency' => $price->currency,
                'interval' => $interval,
                'base_amount_minor' => $price->base_amount_minor,
                'included_users' => $price->included_users,
                'extra_user_amount_minor' => $price->extra_user_amount_minor,
            ] : null,
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
