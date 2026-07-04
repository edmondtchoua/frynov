<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Models\SpecialAttributeDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-6D — définitions d'identifiants métier : lecture fusionnée (globales + tenant), création et
 * modification des définitions PROPRES au tenant (les globales sont en lecture seule).
 */
class SpecialAttributeController extends Controller
{
    /** GET /api/inventory/special-attributes — fusion globales + tenant (tenant prime par code). */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $defs = SpecialAttributeDefinition::where('scope', 'inventory_unit')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderBy('code')->orderByRaw('tenant_id IS NULL')
            ->get()
            ->unique('code')
            ->sortBy('sort_order')
            ->values()
            ->map(fn (SpecialAttributeDefinition $d) => $d->toApiArray());

        return response()->json(['data' => $defs]);
    }

    /** POST /api/inventory/special-attributes — crée une définition tenant (manager/admin). */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'code'                   => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('special_attribute_definitions')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'label'                  => ['required', 'string', 'max:120'],
            'normalization_strategy' => ['nullable', Rule::in(SpecialAttributeDefinition::NORMALIZATIONS)],
            'validation_regex'       => ['nullable', 'string', 'max:120', $this->safeRegexRule()],
            'is_unique'              => ['nullable', 'boolean'],
            'help_text'              => ['nullable', 'string', 'max:190'],
        ]);

        $def = SpecialAttributeDefinition::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'scope'     => 'inventory_unit',
            'normalization_strategy' => $data['normalization_strategy'] ?? SpecialAttributeDefinition::NORM_UPPER_TRIM,
            'is_unique' => $data['is_unique'] ?? true,
            'is_active' => true,
        ]));

        return response()->json(['data' => $def->toApiArray()], 201);
    }

    /** PATCH /api/inventory/special-attributes/{id} — modifie une définition DU tenant. */
    public function update(Request $request, string $id): JsonResponse
    {
        $def = SpecialAttributeDefinition::where('tenant_id', $request->user()->tenant_id)
            ->where('id', $id)->first();
        if (! $def) {
            return response()->json(['message' => 'Définition introuvable (les définitions globales sont en lecture seule).'], 404);
        }

        $data = $request->validate([
            'label'                  => ['sometimes', 'string', 'max:120'],
            'normalization_strategy' => ['sometimes', Rule::in(SpecialAttributeDefinition::NORMALIZATIONS)],
            'validation_regex'       => ['nullable', 'string', 'max:120', $this->safeRegexRule()],
            'is_unique'              => ['sometimes', 'boolean'],
            'is_active'              => ['sometimes', 'boolean'],
            'help_text'              => ['nullable', 'string', 'max:190'],
        ]);

        $def->update($data);

        return response()->json(['data' => $def->fresh()->toApiArray()]);
    }

    /**
     * Recette QA (audit sécurité) — garde anti-ReDoS sur les regex fournies par le tenant :
     *  1. la regex doit COMPILER ;
     *  2. elle doit s'évaluer sur une sonde adverse SANS épuiser le budget de backtracking PCRE
     *     (une regex catastrophique type `(a+)+$` échoue ici et est refusée à la création).
     * Défense en profondeur : à l'exécution, `preg_match` reste borné par pcre.backtrack_limit et
     * une erreur PCRE est traitée comme valeur invalide (fail-closed).
     */
    private function safeRegexRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            $pattern = '/' . str_replace('/', '\/', (string) $value) . '/';
            $probe   = str_repeat('a', 100) . '!'; // sonde adverse (suffixe non-matchant)

            set_error_handler(static fn () => true); // silence les warnings de compilation
            $result = preg_match($pattern, $probe);
            restore_error_handler();

            if ($result === false) {
                $fail('Expression régulière invalide ou trop coûteuse (refusée).');
            }
        };
    }
}
