<?php

namespace App\Modules\Payments\Gateways;

use InvalidArgumentException;
use RuntimeException;

/**
 * Registre des prestataires de paiement (P6-3).
 *
 * Le rail `manual` (approche A) est TOUJOURS disponible. Tout rail réel (Flutterwave,
 * Stripe…) n'est résolvable que si `config('billing.gateways_enabled')` est true — sinon
 * une exception est levée (NO-GO commercial : aucun encaissement automatisé par défaut).
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function __construct()
    {
        $this->register(new ManualGateway());
        // Les rails réels s'enregistreront ici en P6-4 (derrière le flag).
    }

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->code()] = $gateway;
    }

    public function enabled(): bool
    {
        return (bool) config('billing.gateways_enabled', false);
    }

    public function get(string $code): PaymentGateway
    {
        if ($code !== 'manual' && ! $this->enabled()) {
            throw new RuntimeException(
                "Aucun rail PSP réel actif (config billing.gateways_enabled=false). Seul 'manual' est disponible.",
            );
        }

        return $this->gateways[$code]
            ?? throw new InvalidArgumentException("Prestataire de paiement inconnu : {$code}");
    }

    /** @return array<int,string> codes enregistrés */
    public function available(): array
    {
        return array_keys($this->gateways);
    }
}
