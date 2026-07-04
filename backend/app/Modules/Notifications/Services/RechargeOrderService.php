<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\CommunicationCreditMovement;
use App\Modules\Notifications\Models\CreditRechargeOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RC-7F — commandes de recharge payables par Mobile Money.
 *
 * `createOrder()` fige le pack (crédits/prix) sous une référence payable unique. La confirmation
 * arrive par le webhook fournisseur (`confirmByReference`, idempotente) : montant/devise vérifiés,
 * recharge appliquée via CommunicationCreditService, mouvement lié à la commande. Un paiement
 * divergent ne crédite JAMAIS : la commande passe `needs_review` pour arbitrage opérateur.
 */
class RechargeOrderService
{
    public function __construct(private readonly CommunicationCreditService $credits) {}

    /** @throws \InvalidArgumentException pack inconnu */
    public function createOrder(string $tenantId, string $packCode, ?string $userId = null): CreditRechargeOrder
    {
        $pack = $this->credits->pack($packCode);
        if (! $pack) {
            throw new \InvalidArgumentException("Pack de recharge inconnu : {$packCode}");
        }

        return CreditRechargeOrder::create([
            'tenant_id'   => $tenantId,
            'reference'   => $this->uniqueReference(),
            'pack_code'   => $packCode,
            'channel'     => $pack['channel'],
            'credits'     => (int) $pack['credits'],
            'price_cents' => (int) $pack['price_cents'],
            'currency'    => (string) $pack['currency'],
            'status'      => CreditRechargeOrder::STATUS_PENDING,
            'created_by'  => $userId,
        ]);
    }

    /** Annulation d'une commande en attente (une commande payée ne s'annule pas). */
    public function cancel(CreditRechargeOrder $order): CreditRechargeOrder
    {
        if ($order->status !== CreditRechargeOrder::STATUS_PENDING) {
            throw new \DomainException('Seule une commande en attente peut être annulée.');
        }
        $order->update(['status' => CreditRechargeOrder::STATUS_CANCELLED]);

        return $order;
    }

    /**
     * Confirmation webhook — IDEMPOTENTE (verrou de ligne) :
     *  - commande déjà payée → `already_processed` (aucun nouveau crédit, replay sûr) ;
     *  - référence inconnue → `unknown_reference` (aucun crash, aucun crédit) ;
     *  - commande annulée → `not_payable` ;
     *  - montant/devise divergents → `mismatch`, commande `needs_review`, AUCUN crédit ;
     *  - sinon → recharge appliquée, commande `paid`, mouvement lié.
     *
     * @return array{result:string,order:?CreditRechargeOrder}
     */
    public function confirmByReference(
        string $reference,
        ?int $amountCents,
        ?string $currency,
        string $provider,
        ?string $providerRef = null,
        array $payloadMeta = [],
    ): array {
        return DB::transaction(function () use ($reference, $amountCents, $currency, $provider, $providerRef, $payloadMeta) {
            $order = CreditRechargeOrder::withoutTenantScope()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return ['result' => 'unknown_reference', 'order' => null];
            }
            if ($order->status === CreditRechargeOrder::STATUS_PAID) {
                return ['result' => 'already_processed', 'order' => $order];
            }
            if ($order->status === CreditRechargeOrder::STATUS_CANCELLED) {
                return ['result' => 'not_payable', 'order' => $order];
            }

            // Vérification stricte du montant/devise : un paiement divergent ne crédite jamais.
            $amountOk   = $amountCents === null || $amountCents === (int) $order->price_cents;
            $currencyOk = $currency === null || strtoupper($currency) === strtoupper($order->currency);
            if (! $amountOk || ! $currencyOk) {
                $order->update([
                    'status'       => CreditRechargeOrder::STATUS_NEEDS_REVIEW,
                    'provider'     => $provider,
                    'provider_ref' => $providerRef,
                    'meta'         => array_merge((array) $order->meta, [
                        'mismatch' => ['amount_cents' => $amountCents, 'currency' => $currency],
                        'payload'  => $payloadMeta,
                    ]),
                ]);

                return ['result' => 'mismatch', 'order' => $order];
            }

            $movement = $this->credits->credit(
                $order->tenant_id,
                $order->channel,
                (int) $order->credits,
                CommunicationCreditMovement::REASON_RECHARGE,
                $order->reference,
                ['pack' => $order->pack_code, 'price_cents' => $order->price_cents, 'currency' => $order->currency,
                 'provider' => $provider, 'provider_ref' => $providerRef],
            );

            $order->update([
                'status'       => CreditRechargeOrder::STATUS_PAID,
                'provider'     => $provider,
                'provider_ref' => $providerRef,
                'paid_at'      => now(),
                'movement_id'  => $movement->id,
            ]);

            return ['result' => 'confirmed', 'order' => $order];
        });
    }

    /** Référence payable courte et unique (lettres/chiffres non ambigus). */
    private function uniqueReference(): string
    {
        do {
            $ref = 'RCH-' . Str::upper(Str::random(8));
        } while (CreditRechargeOrder::withoutTenantScope()->where('reference', $ref)->exists());

        return $ref;
    }
}
