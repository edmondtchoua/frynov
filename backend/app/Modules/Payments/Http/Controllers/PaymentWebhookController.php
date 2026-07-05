<?php

namespace App\Modules\Payments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Réception des webhooks PSP (P6-3 — inerte).
 *
 * La signature HMAC est déjà validée par le middleware `webhook.signature`. À ce stade
 * aucun rail réel n'est branché : on accuse réception (200) et on journalise, SANS muter
 * d'état. P6-4 branchera le traitement réel (PaymentGatewayManager->get($provider)->verify()
 * + mise à jour du Payment). La route n'est même enregistrée que si le flag est actif.
 */
class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        Log::info('PAYMENT_WEBHOOK_RECEIVED', ['provider' => $provider]);

        return response()->json(['received' => true, 'provider' => $provider], 200);
    }
}
