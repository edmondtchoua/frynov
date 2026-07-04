<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\TenantCredit;

/**
 * RC-6G (règle 1) — ledger d'avoirs. Un avoir ne franchit JAMAIS une devise. Le solde est la somme
 * signée des mouvements. La consommation est bornée au solde (jamais de solde négatif).
 */
class TenantCreditService
{
    public function balance(string $tenantId, string $currency): int
    {
        return (int) TenantCredit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('currency', strtoupper($currency))
            ->sum('amount_minor');
    }

    /** Crédite un avoir (trop-perçu, reliquat de proration…). */
    public function credit(string $tenantId, string $currency, int $amountMinor, string $source, ?string $reference = null, ?string $userId = null): ?TenantCredit
    {
        if ($amountMinor <= 0) {
            return null;
        }

        return TenantCredit::create([
            'tenant_id'    => $tenantId,
            'currency'     => strtoupper($currency),
            'amount_minor' => $amountMinor,
            'source'       => $source,
            'reference'    => $reference,
            'created_by'   => $userId,
        ]);
    }

    /**
     * Consomme jusqu'à `amountMinor` d'avoirs ; retourne le montant réellement consommé
     * (borné au solde disponible).
     */
    public function consume(string $tenantId, string $currency, int $amountMinor, ?string $reference = null, ?string $userId = null): int
    {
        if ($amountMinor <= 0) {
            return 0;
        }

        $available = $this->balance($tenantId, $currency);
        $consumed  = min($available, $amountMinor);
        if ($consumed <= 0) {
            return 0;
        }

        TenantCredit::create([
            'tenant_id'    => $tenantId,
            'currency'     => strtoupper($currency),
            'amount_minor' => -$consumed,
            'source'       => TenantCredit::SOURCE_CONSUMPTION,
            'reference'    => $reference,
            'created_by'   => $userId,
        ]);

        return $consumed;
    }
}
