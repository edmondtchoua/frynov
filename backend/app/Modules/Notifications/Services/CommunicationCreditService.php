<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\CommunicationCredit;
use App\Modules\Notifications\Models\CommunicationCreditMovement;
use App\Modules\Notifications\Models\NotificationOutbox;
use Illuminate\Support\Facades\DB;

/**
 * RC-7E — crédits de communication rechargeables par tenant × canal.
 *
 * Le solde est décompté à l'envoi (`debit`, atomique) et abondé à la recharge (`credit` / `recharge`).
 * Toute variation laisse une trace dans `communication_credit_movements`. Le solde ne descend jamais
 * sous zéro : `debit` échoue proprement (retour `false`) et l'appelant bloque l'envoi.
 */
class CommunicationCreditService
{
    /** Le décompte est-il actif pour ce canal (interrupteur global + liste des canaux facturés) ? */
    public function isMetered(string $channel): bool
    {
        if (! (bool) config('notifications.credits.enabled', true)) {
            return false;
        }

        return in_array($channel, (array) config('notifications.credits.metered_channels', []), true);
    }

    /** Solde courant d'un canal (0 si aucune ligne). */
    public function balance(string $tenantId, string $channel): int
    {
        return (int) (CommunicationCredit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->value('balance') ?? 0);
    }

    /** Soldes de tous les canaux connus (facturés ou non), pour l'affichage SPA. */
    public function balances(string $tenantId): array
    {
        $rows = CommunicationCredit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->pluck('balance', 'channel');

        $out = [];
        foreach (\App\Modules\Notifications\Models\NotificationChannel::CHANNELS as $channel) {
            $out[$channel] = (int) ($rows[$channel] ?? 0);
        }

        return $out;
    }

    public function hasCredit(string $tenantId, string $channel, int $amount = 1): bool
    {
        return $this->balance($tenantId, $channel) >= $amount;
    }

    /**
     * Décompte atomique : ne débite QUE si le solde suffit. Verrou de ligne pour empêcher un solde
     * négatif sous concurrence. Retour `false` si crédit insuffisant (aucun mouvement enregistré).
     */
    public function debit(
        string $tenantId,
        string $channel,
        int $amount = 1,
        string $reason = CommunicationCreditMovement::REASON_SEND,
        ?string $reference = null,
        array $meta = [],
        ?string $userId = null,
    ): bool {
        if ($amount <= 0) {
            return true;
        }

        return DB::transaction(function () use ($tenantId, $channel, $amount, $reason, $reference, $meta, $userId) {
            $credit = CommunicationCredit::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('channel', $channel)
                ->lockForUpdate()
                ->first();

            if (! $credit || $credit->balance < $amount) {
                return false;
            }

            $credit->balance -= $amount;
            $credit->save();

            $this->recordMovement($tenantId, $channel, -$amount, $credit->balance, $reason, $reference, $meta, $userId);

            return true;
        });
    }

    /** Abonde le solde (recharge / remboursement / ajustement) et journalise le mouvement. */
    public function credit(
        string $tenantId,
        string $channel,
        int $amount,
        string $reason = CommunicationCreditMovement::REASON_RECHARGE,
        ?string $reference = null,
        array $meta = [],
        ?string $userId = null,
    ): CommunicationCreditMovement {
        return DB::transaction(function () use ($tenantId, $channel, $amount, $reason, $reference, $meta, $userId) {
            $credit = CommunicationCredit::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('channel', $channel)
                ->lockForUpdate()
                ->first();

            if (! $credit) {
                $credit = CommunicationCredit::create([
                    'tenant_id' => $tenantId,
                    'channel'   => $channel,
                    'balance'   => 0,
                ]);
            }

            // Invariant « jamais sous zéro » : un ajustement négatif ne descend pas sous 0 ; le
            // mouvement journalise la variation RÉELLEMENT appliquée. (recette QA)
            $before = (int) $credit->balance;
            $credit->balance = max(0, $before + $amount);
            $credit->save();
            $applied = $credit->balance - $before;

            $movement = $this->recordMovement($tenantId, $channel, $applied, $credit->balance, $reason, $reference, $meta, $userId);

            // À la recharge, on réarme les envois bloqués faute de crédit (statut no_credit) pour ce
            // canal : ils repartent au prochain flush (leurs `attempts` n'ont pas été consommés). Sans
            // cela, un message bloqué resterait perdu à jamais malgré la recharge. (recette QA)
            if ($reason === CommunicationCreditMovement::REASON_RECHARGE && $applied > 0) {
                NotificationOutbox::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('channel', $channel)
                    ->where('status', NotificationOutbox::STATUS_NO_CREDIT)
                    ->update(['status' => NotificationOutbox::STATUS_PENDING, 'last_error' => null]);
            }

            return $movement;
        });
    }

    // ── Recharge par pack (rail de paiement manuel) ─────────────────────────

    /** @return array<string,array{channel:string,credits:int,price_cents:int,currency:string}> */
    public function packs(): array
    {
        return (array) config('notifications.credits.packs', []);
    }

    /** @return array{channel:string,credits:int,price_cents:int,currency:string}|null */
    public function pack(string $code): ?array
    {
        $pack = $this->packs()[$code] ?? null;

        return $pack ? array_merge($pack, ['code' => $code]) : null;
    }

    /**
     * Applique la recharge d'un pack : l'opérateur a encaissé le montant (rail manuel), on abonde le
     * solde du canal du pack et on journalise (réf. paiement + pack en meta).
     *
     * @return array{channel:string,credits_added:int,balance:int,movement_id:string}
     * @throws \InvalidArgumentException pack inconnu
     */
    public function recharge(string $tenantId, string $packCode, ?string $paymentReference = null, ?string $userId = null): array
    {
        $pack = $this->pack($packCode);
        if (! $pack) {
            throw new \InvalidArgumentException("Pack de recharge inconnu : {$packCode}");
        }

        $movement = $this->credit(
            $tenantId,
            $pack['channel'],
            (int) $pack['credits'],
            CommunicationCreditMovement::REASON_RECHARGE,
            $paymentReference,
            ['pack' => $packCode, 'price_cents' => $pack['price_cents'], 'currency' => $pack['currency']],
            $userId,
        );

        return [
            'channel'       => $pack['channel'],
            'credits_added' => (int) $pack['credits'],
            'balance'       => $movement->balance_after,
            'movement_id'   => $movement->id,
        ];
    }

    private function recordMovement(
        string $tenantId,
        string $channel,
        int $delta,
        int $balanceAfter,
        string $reason,
        ?string $reference,
        array $meta,
        ?string $userId,
    ): CommunicationCreditMovement {
        return CommunicationCreditMovement::create([
            'tenant_id'     => $tenantId,
            'channel'       => $channel,
            'delta'         => $delta,
            'balance_after' => $balanceAfter,
            'reason'        => $reason,
            'reference'     => $reference,
            'meta'          => $meta ?: null,
            'created_by'    => $userId,
        ]);
    }
}
