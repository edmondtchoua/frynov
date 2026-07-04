<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Services\RechargeOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * RC-7F — webhook public de confirmation Mobile Money (hors auth tenant : c'est le FOURNISSEUR
 * qui appelle). Sécurité : signature HMAC-SHA256 du corps brut avec le secret partagé
 * (`notifications.mobile_money.webhook_secret`) — comparaison en temps constant. Les champs du
 * payload sont mappés par config (`field_map`) pour s'adapter à tout fournisseur sans code.
 *
 * Toujours idempotent : un replay du même webhook ne crédite jamais deux fois.
 */
class MobileMoneyWebhookController extends Controller
{
    public function __construct(private readonly RechargeOrderService $orders) {}

    /** POST /api/webhooks/mobile-money */
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('notifications.mobile_money.webhook_secret', '');
        if ($secret === '') {
            // Pas de secret configuré = webhook désactivé : on refuse tout (jamais de crédit non signé).
            return response()->json(['message' => 'Webhook non configuré.'], 503);
        }

        $header    = (string) config('notifications.mobile_money.signature_header', 'X-Webhook-Signature');
        $signature = (string) $request->header($header, '');
        $expected  = hash_hmac('sha256', $request->getContent(), $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        // Mapping des champs, configurable par fournisseur (data_get : chemins pointés acceptés).
        $map      = (array) config('notifications.mobile_money.field_map', []);
        $payload  = (array) $request->json()->all();
        $ref      = (string) data_get($payload, $map['reference'] ?? 'reference', '');
        $status   = (string) data_get($payload, $map['status'] ?? 'status', '');
        $amount   = data_get($payload, $map['amount_cents'] ?? 'amount_cents');
        $currency = data_get($payload, $map['currency'] ?? 'currency');
        $txId     = data_get($payload, $map['provider_ref'] ?? 'transaction_id');
        $provider = (string) data_get($payload, $map['provider'] ?? 'provider', 'mobile_money');

        if ($ref === '') {
            return response()->json(['result' => 'ignored', 'message' => 'Référence absente.'], 200);
        }

        // Seuls les statuts de succès déclenchent la recharge ; le reste est journalisé et ignoré.
        $successValues = array_map('strtolower', (array) config('notifications.mobile_money.success_values', ['success', 'successful', 'paid', 'completed']));
        if (! in_array(strtolower($status), $successValues, true)) {
            Log::info('[momo-webhook] statut non finalisé ignoré', ['reference' => $ref, 'status' => $status]);

            return response()->json(['result' => 'ignored'], 200);
        }

        $outcome = $this->orders->confirmByReference(
            $ref,
            $amount === null ? null : (int) $amount,
            $currency === null ? null : (string) $currency,
            $provider,
            $txId === null ? null : (string) $txId,
            ['status' => $status],
        );

        // Toujours 200 sur une requête signée traitable : le fournisseur ne doit pas rejouer en boucle.
        return response()->json(['result' => $outcome['result']], 200);
    }
}
