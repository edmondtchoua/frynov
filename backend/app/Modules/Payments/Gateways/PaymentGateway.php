<?php

namespace App\Modules\Payments\Gateways;

/**
 * Contrat d'un prestataire de paiement (PSP) — P6-3 (infrastructure inerte).
 *
 * AUCUN rail réel n'est actif tant que `config('billing.gateways_enabled')` est false.
 * Seul `ManualGateway` (approche A — encaissement déclaratif + validation admin) est
 * toujours disponible. Les implémentations réelles (Flutterwave/Paystack/Stripe…) seront
 * ajoutées en P6-4, marché par marché, derrière le flag.
 */
interface PaymentGateway
{
    /** Code unique du prestataire (ex. `manual`, `flutterwave`, `stripe`). */
    public function code(): string;

    /**
     * Démarre un encaissement.
     *
     * @param  array<string,mixed>  $params
     * @return array{status:string, reference:?string, redirect_url:?string, mode:string}
     */
    public function initiate(array $params): array;

    /**
     * Vérifie le statut d'une transaction auprès du prestataire.
     *
     * @return string `pending` | `succeeded` | `failed`
     */
    public function verify(string $reference): string;

    /** Rembourse tout ou partie d'une transaction. */
    public function refund(string $reference, ?int $amountMinor = null): bool;
}
