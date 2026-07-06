<?php

namespace App\Modules\Billing\Services\Psp;

/**
 * Driver PSP de DÉVELOPPEMENT/RECETTE : aucune passerelle réelle. `initiate` renvoie une URL de
 * checkout factice ; `verify` authentifie le webhook par HMAC-SHA256 sur `reference|status|amount`
 * avec le secret configuré. Sert de référence d'implémentation pour un vrai driver.
 */
class FakePspGateway implements PspGateway
{
    public function initiate(string $reference, int $amountMinor, string $currency, array $meta = []): array
    {
        return [
            'reference'    => $reference,
            'checkout_url' => rtrim((string) config('app.url'), '/')."/psp/fake-checkout/{$reference}",
        ];
    }

    public function verify(array $payload): ?array
    {
        $reference = (string) ($payload['reference'] ?? '');
        $status    = (string) ($payload['status'] ?? '');
        $amount    = isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : null;
        $signature = (string) ($payload['signature'] ?? '');

        if ($reference === '' || $status === '') {
            return null;
        }

        $expected = hash_hmac('sha256', "{$reference}|{$status}|{$amount}", (string) config('billing.psp.webhook_secret'));
        if (! hash_equals($expected, $signature)) {
            return null; // signature invalide → webhook rejeté
        }

        return ['reference' => $reference, 'status' => $status, 'amount_minor' => $amount];
    }

    /** Utilitaire (recette) — signe un payload comme le ferait le PSP. */
    public static function sign(string $reference, string $status, ?int $amountMinor): string
    {
        return hash_hmac('sha256', "{$reference}|{$status}|{$amountMinor}", (string) config('billing.psp.webhook_secret'));
    }
}
