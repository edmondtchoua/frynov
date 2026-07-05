<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Auth\Models\CountryRule;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Super-admin CRUD for per-country registration rules (Sprint 21).
 * Sits behind the admin group (auth:sanctum + RequireAdmin), so every action is
 * super-admin only and audited. The runtime resolver (RegistrationRuleService)
 * reads these rows; this controller only manages them.
 */
class AdminCountryRuleController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /** GET /api/admin/country-rules */
    public function index(): JsonResponse
    {
        $paginator = CountryRule::orderBy('country_code')->paginate(100);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** POST /api/admin/country-rules */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request, null);

        $rule = CountryRule::create($data);
        $this->audit->logCreated($request, $rule);

        return response()->json($rule, 201);
    }

    /** GET /api/admin/country-rules/{countryRule} */
    public function show(CountryRule $countryRule): JsonResponse
    {
        return response()->json($countryRule);
    }

    /** PATCH /api/admin/country-rules/{countryRule} */
    public function update(Request $request, CountryRule $countryRule): JsonResponse
    {
        $data = $this->validateData($request, $countryRule);

        $old = $countryRule->only(array_keys($data));
        $countryRule->update($data);
        $this->audit->logUpdated($request, $countryRule, $old);

        return response()->json($countryRule);
    }

    /** DELETE /api/admin/country-rules/{countryRule} */
    public function destroy(Request $request, CountryRule $countryRule): JsonResponse
    {
        $this->audit->logDeleted($request, $countryRule);
        $countryRule->delete();

        return response()->json(['message' => 'Règle pays supprimée.']);
    }

    /**
     * Validate + normalise a country-rule payload. On create, country_code is
     * required and unique; on update it is optional and unique-ignoring-self.
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?CountryRule $existing): array
    {
        // Normalise to uppercase BEFORE validation so the unique check is
        // case-insensitive (input "sn" must collide with a stored "SN").
        foreach (['country_code', 'default_currency'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => strtoupper(trim($request->input($field)))]);
            }
        }

        $uniqueIgnore = $existing ? ',' . $existing->id : '';

        return $request->validate([
            'country_code'      => [$existing ? 'sometimes' : 'required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', "unique:country_rules,country_code{$uniqueIgnore}"],
            'is_active'         => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_blocked'        => ['sometimes', 'boolean'],
            'allowed_plans'     => ['nullable', 'array'],
            'allowed_plans.*'   => ['string', 'max:32'],
            'default_currency'  => ['nullable', 'string', 'size:3'],
            'default_timezone'  => ['nullable', 'string', 'max:64'],
            'metadata'          => ['nullable', 'array'],
        ]);
    }
}
