<?php

namespace App\Modules\Warranties\Services;

use App\Modules\Platform\Services\AuditService;
use App\Modules\Warranties\Exceptions\ClaimStateException;
use App\Modules\Warranties\Exceptions\OutOfWarrantyException;
use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * RC-5F — réclamations SAV rattachées aux contrats de garantie.
 *
 * Ouverture gardée par la période : un contrat **void** refuse toute réclamation ; un contrat **expiré**
 * n'en accepte qu'avec un `override` explicite (tracé `out_of_warranty=true` + audit). Le cycle de vie
 * suit `open → in_repair → resolved|replaced|rejected` (les trois derniers sont terminaux).
 */
class WarrantyClaimService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param array{reason:string,description?:?string,override?:bool} $data
     * @throws OutOfWarrantyException
     */
    public function open(WarrantyContract $contract, array $data, ?string $userId = null): WarrantyClaim
    {
        if ($contract->status === WarrantyContract::STATUS_VOID) {
            throw new OutOfWarrantyException($contract->id, 'contrat annulé');
        }

        $expired  = $contract->status === WarrantyContract::STATUS_EXPIRED
            || ($contract->ends_at !== null && $contract->ends_at->isPast());
        $override = (bool) ($data['override'] ?? false);

        if ($expired && ! $override) {
            throw new OutOfWarrantyException($contract->id);
        }

        $claim = WarrantyClaim::create([
            'tenant_id'            => $contract->tenant_id,
            'warranty_contract_id' => $contract->id,
            'customer_id'          => $contract->customer_id,
            'inventory_unit_id'    => $contract->inventory_unit_id,
            'reason'               => $data['reason'],
            'description'          => $data['description'] ?? null,
            'status'               => WarrantyClaim::STATUS_OPEN,
            'out_of_warranty'      => $expired,
            'opened_at'            => now(),
            'opened_by'            => $userId,
        ]);

        // Hors période via override → tracé pour audit (décision opposable).
        if ($expired && $override) {
            $this->audit->log(
                action: 'warranty.claim.out_of_period_override',
                tenantId: $contract->tenant_id,
                userId: $userId,
                subject: $claim,
            );
        }

        return $claim;
    }

    /**
     * @param array{diagnostic?:?string,resolution?:?string,resolution_note?:?string} $data
     * @throws ClaimStateException
     */
    public function transition(WarrantyClaim $claim, string $to, array $data = [], ?string $userId = null): WarrantyClaim
    {
        $from = $claim->status;

        if ($claim->isTerminal()) {
            throw new ClaimStateException($from, $to);
        }

        $allowed = [
            WarrantyClaim::STATUS_IN_REPAIR,
            WarrantyClaim::STATUS_RESOLVED,
            WarrantyClaim::STATUS_REPLACED,
            WarrantyClaim::STATUS_REJECTED,
        ];
        if (! in_array($to, $allowed, true)) {
            throw new ClaimStateException($from, $to);
        }

        $attrs = ['status' => $to];

        if (array_key_exists('diagnostic', $data)) {
            $attrs['diagnostic'] = $data['diagnostic'];
        }

        if (in_array($to, WarrantyClaim::TERMINAL, true)) {
            $attrs['resolution']      = $data['resolution'] ?? $this->defaultResolution($to);
            $attrs['resolution_note'] = $data['resolution_note'] ?? null;
            $attrs['resolved_at']     = now();
            $attrs['resolved_by']     = $userId;
        }

        $claim->update($attrs);

        return $claim;
    }

    /** Réclamations d'un contrat. */
    public function forContract(string $tenantId, string $contractId): Collection
    {
        return WarrantyClaim::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('warranty_contract_id', $contractId)
            ->latest()
            ->get();
    }

    /** Réclamations rattachées aux contrats d'une commande (affichage fiche commande). */
    public function forOrder(string $tenantId, string $orderId): Collection
    {
        $contractIds = WarrantyContract::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->pluck('id');

        return WarrantyClaim::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereIn('warranty_contract_id', $contractIds)
            ->latest()
            ->get();
    }

    private function defaultResolution(string $status): string
    {
        return match ($status) {
            WarrantyClaim::STATUS_REPLACED => 'replacement',
            WarrantyClaim::STATUS_REJECTED => 'rejected',
            default                        => 'repair',
        };
    }
}
