<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountClass;
use App\Modules\Accounting\Models\AccountingSettings;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Accounting\Services\ChartOfAccountsProvisioner;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-23 — référentiel comptable : plan de comptes, journaux, taxes, paramètres, provisionnement.
 * RBAC porté par les routes (lecture : accounting.view ; écriture : accounting.manage).
 */
class ReferentialController extends Controller
{
    public function __construct(private readonly ChartOfAccountsProvisioner $provisioner) {}

    // ── GET /api/accounting/overview ─────────────────────────────────────────

    /** État du référentiel (provisionné ?) + compteurs — écran d'accueil du module. */
    public function overview(Request $request): JsonResponse
    {
        $tid = $request->user()->tenant_id;

        return response()->json(['data' => [
            'provisioned' => $this->provisioner->isProvisioned($tid),
            'accounts'    => Account::count(),
            'journals'    => Journal::count(),
            'taxes'       => Tax::count(),
            'classes'     => AccountClass::orderBy('code')->get(['code', 'name', 'type']),
        ]]);
    }

    // ── POST /api/accounting/provision ───────────────────────────────────────

    /** Initialise (idempotent) le référentiel SYSCOHADA du tenant. */
    public function provision(Request $request): JsonResponse
    {
        $tenant   = Tenant::withoutGlobalScopes()->findOrFail($request->user()->tenant_id);
        $settings = $this->provisioner->provision($tenant, $request->user()->id);

        return response()->json([
            'message' => 'Référentiel comptable initialisé.',
            'data'    => $settings,
        ], 201);
    }

    // ── Plan de comptes ──────────────────────────────────────────────────────

    /** GET /api/accounting/accounts?class=&search=&active= */
    public function accounts(Request $request): JsonResponse
    {
        $accounts = Account::query()
            ->when($request->query('class'), fn ($q, $c) => $q->where('class_code', (int) $c))
            ->when($request->query('search'), function ($q, $s) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($s)) . '%';
                $q->where(fn ($qq) => $qq->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json($accounts);
    }

    /** POST /api/accounting/accounts */
    public function storeAccount(Request $request): JsonResponse
    {
        $tid  = $request->user()->tenant_id;
        $data = $request->validate([
            'code'       => ['required', 'string', 'max:20', 'regex:/^[1-9][0-9]*$/',
                             Rule::unique('accounting_accounts', 'code')->where('tenant_id', $tid)],
            'name'       => ['required', 'string', 'max:255'],
            'kind'       => ['required', Rule::in(Account::KINDS)],
            'parent_id'  => ['nullable', 'uuid', Rule::exists('accounting_accounts', 'id')->where('tenant_id', $tid)],
        ]);

        $account = Account::create($data + [
            'tenant_id'  => $tid,
            'class_code' => (int) substr($data['code'], 0, 1),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $account], 201);
    }

    /** PUT /api/accounting/accounts/{id} — nom/actif uniquement (le code est immuable). */
    public function updateAccount(Request $request, string $id): JsonResponse
    {
        $account = Account::findOrFail($id);
        $data    = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Un compte système reste actif : le moteur d'imputation en dépend.
        if ($account->is_system && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json(['message' => 'Un compte système ne peut pas être désactivé.'], 422);
        }

        $account->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => $account->fresh()]);
    }

    // ── Journaux ─────────────────────────────────────────────────────────────

    /** GET /api/accounting/journals */
    public function journals(): JsonResponse
    {
        return response()->json(['data' => Journal::orderBy('code')->get()]);
    }

    /** PUT /api/accounting/journals/{id} — libellé/actif (code & type immuables). */
    public function updateJournal(Request $request, string $id): JsonResponse
    {
        $journal = Journal::findOrFail($id);
        $data    = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($journal->is_system && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json(['message' => 'Un journal système ne peut pas être désactivé.'], 422);
        }

        $journal->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => $journal->fresh()]);
    }

    // ── Taxes ────────────────────────────────────────────────────────────────

    /** GET /api/accounting/taxes */
    public function taxes(): JsonResponse
    {
        return response()->json(['data' => Tax::orderBy('code')->get()]);
    }

    /** POST /api/accounting/taxes */
    public function storeTax(Request $request): JsonResponse
    {
        $tid  = $request->user()->tenant_id;
        $data = $request->validate([
            'code'         => ['required', 'string', 'max:16',
                               Rule::unique('accounting_taxes', 'code')->where('tenant_id', $tid)],
            'name'         => ['required', 'string', 'max:255'],
            'rate_bp'      => ['required', 'integer', 'min:0', 'max:10000'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'collected_account_id'  => ['nullable', 'uuid', Rule::exists('accounting_accounts', 'id')->where('tenant_id', $tid)],
            'deductible_account_id' => ['nullable', 'uuid', Rule::exists('accounting_accounts', 'id')->where('tenant_id', $tid)],
        ]);

        $tax = Tax::create($data + ['tenant_id' => $tid, 'created_by' => $request->user()->id]);

        return response()->json(['data' => $tax], 201);
    }

    /** PUT /api/accounting/taxes/{id} */
    public function updateTax(Request $request, string $id): JsonResponse
    {
        $tax  = Tax::findOrFail($id);
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'rate_bp'      => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $tax->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => $tax->fresh()]);
    }

    // ── Paramètres ───────────────────────────────────────────────────────────

    /** GET /api/accounting/settings */
    public function settings(Request $request): JsonResponse
    {
        $settings = AccountingSettings::first();

        return response()->json(['data' => $settings]); // null si non provisionné (l'UI propose l'init)
    }

    /** PUT /api/accounting/settings */
    public function updateSettings(Request $request): JsonResponse
    {
        $settings = AccountingSettings::firstOrFail();
        $data     = $request->validate([
            'fiscal_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'numbering_rules'         => ['sometimes', 'array'],
            'default_accounts'        => ['sometimes', 'array'],
            'auto_post'               => ['sometimes', 'boolean'],
        ]);

        $old = $settings->only(array_keys($data));
        $settings->update($data + ['updated_by' => $request->user()->id]);

        app(\App\Modules\Platform\Services\AuditService::class)->log(
            action: 'accounting.settings.updated',
            tenantId: $request->user()->tenant_id,
            userId: $request->user()->id,
            subject: $settings,
            oldValues: $old,
            newValues: $data,
        );

        return response()->json(['data' => $settings->fresh()]);
    }
}
