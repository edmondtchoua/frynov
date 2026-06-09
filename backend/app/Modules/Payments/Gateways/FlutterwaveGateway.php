<?php

namespace App\Modules\Payments\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adaptateur de RÉFÉRENCE Flutterwave (P6-4).
 *
 * Couverture panafricaine (cartes + Mobile Money : Nigeria, Ghana, Kenya, UEMOA…). Démontre
 * comment un PSP réel se branche sur le contrat PaymentGateway. NE FAIT AUCUN APPEL tant que le
 * flag `billing.gateways_enabled` est false ET qu'une `secret_key` n'est pas configurée — il
 * n'est résolvable par le manager que sous ces conditions.
 *
 * ⚠️ Implémentation de référence (testée contre HTTP mocké). Vérifier les endpoints/champs exacts
 * contre la doc Flutterwave v3 avant toute mise en production.
 */
class FlutterwaveGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'flutterwave';
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('billing.flutterwave.base_url', 'https://api.flutterwave.com'), '/'))
            ->withToken((string) config('billing.flutterwave.secret_key', ''))
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * @param  array{tx_ref?:string, amount_minor?:int, currency?:string, redirect_url?:string, customer_email?:string}  $params
     */
    public function initiate(array $params): array
    {
        $res = $this->client()->post('/v3/payments', [
            'tx_ref'       => $params['tx_ref'] ?? null,
            'amount'       => isset($params['amount_minor']) ? $params['amount_minor'] / 100 : null,
            'currency'     => $params['currency'] ?? null,
            'redirect_url' => $params['redirect_url'] ?? null,
            'customer'     => ['email' => $params['customer_email'] ?? null],
        ]);

        if (! $res->successful() || $res->json('status') !== 'success') {
            return ['status' => 'failed', 'reference' => null, 'redirect_url' => null, 'mode' => 'auto'];
        }

        return [
            'status'       => 'pending',
            'reference'    => $res->json('data.tx_ref') ?? ($params['tx_ref'] ?? null),
            'redirect_url' => $res->json('data.link'),
            'mode'         => 'auto',
        ];
    }

    public function verify(string $reference): string
    {
        $res = $this->client()->get('/v3/transactions/verify_by_reference', ['tx_ref' => $reference]);

        return match ($res->json('data.status')) {
            'successful'          => 'succeeded',
            'failed', 'cancelled' => 'failed',
            default               => 'pending',
        };
    }

    public function refund(string $reference, ?int $amountMinor = null): bool
    {
        $res = $this->client()->post(
            "/v3/transactions/{$reference}/refund",
            $amountMinor !== null ? ['amount' => $amountMinor / 100] : [],
        );

        return $res->successful() && $res->json('status') === 'success';
    }
}
