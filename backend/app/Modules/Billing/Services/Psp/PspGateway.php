<?php

namespace App\Modules\Billing\Services\Psp;

/**
 * Option — abstraction d'une passerelle de paiement automatisée (PSP). Permet de brancher plus tard
 * un vrai rail (Flutterwave, Paystack, Stripe…) sans toucher au reste du flux : la demande de
 * changement + le paiement existent déjà (P1), le PSP ne fait qu'INITIER puis CONFIRMER l'encaissement.
 */
interface PspGateway
{
    /**
     * Initie un paiement : renvoie une référence PSP et une URL de checkout.
     *
     * @return array{reference:string,checkout_url:string}
     */
    public function initiate(string $reference, int $amountMinor, string $currency, array $meta = []): array;

    /**
     * Vérifie/authentifie un webhook entrant. Renvoie les données normalisées si le webhook est
     * authentique, sinon null (webhook rejeté).
     *
     * @return array{reference:string,status:string,amount_minor:?int}|null
     */
    public function verify(array $payload): ?array;
}
