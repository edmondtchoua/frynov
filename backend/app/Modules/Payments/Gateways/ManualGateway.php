<?php

namespace App\Modules\Payments\Gateways;

/**
 * Rail « manuel » (approche A) modélisé comme un PaymentGateway — P6-3.
 *
 * N'effectue AUCUN appel externe : l'encaissement est déclaratif (le client téléverse une
 * preuve, un admin valide via le module ManualPayment). Sert de référence d'implémentation
 * du contrat et reste le rail par défaut/universel tant qu'aucun PSP réel n'est branché.
 */
class ManualGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'manual';
    }

    public function initiate(array $params): array
    {
        // Pas d'orchestration externe : le paiement naît « en attente » de validation manuelle.
        return [
            'status'       => 'pending',
            'reference'    => null,
            'redirect_url' => null,
            'mode'         => 'manual',
        ];
    }

    public function verify(string $reference): string
    {
        // La vérification réelle est faite par l'admin (approve/reject ManualPayment).
        return 'pending';
    }

    public function refund(string $reference, ?int $amountMinor = null): bool
    {
        // Aucun remboursement automatisé pour un encaissement manuel.
        return false;
    }
}
