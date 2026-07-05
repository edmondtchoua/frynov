<?php

namespace App\Modules\Billing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies HMAC-SHA256 webhook signatures from payment providers.
 *
 * Prevents:
 *   - Forged webhook payloads (fake payment confirmations)
 *   - Replay attacks (stale webhooks replayed to re-trigger payment)
 *
 * Configuration (config/billing.php):
 *   'webhooks' => [
 *       'cinetpay'  => ['secret' => env('CINETPAY_WEBHOOK_SECRET')],
 *       'wave'      => ['secret' => env('WAVE_WEBHOOK_SECRET')],
 *       'stripe'    => ['secret' => env('STRIPE_WEBHOOK_SECRET')],
 *   ]
 *
 * Route usage:
 *   Route::post('webhooks/cinetpay', ...)->middleware('webhook.signature:cinetpay')
 */
class VerifyWebhookSignature
{
    private const REPLAY_WINDOW_SECONDS = 300; // 5 minutes

    public function handle(Request $request, Closure $next, string $provider = 'default'): Response
    {
        $secret = config("billing.webhooks.{$provider}.secret");

        if (! $secret) {
            \Log::error('WEBHOOK_NO_SECRET', ['provider' => $provider]);
            return response()->json(['message' => 'Webhook non configuré.'], 500);
        }

        $signature = $request->header("X-{$provider}-Signature")
            ?? $request->header('X-Signature')
            ?? $request->header('X-Hub-Signature-256');

        if (! $signature) {
            \Log::warning('WEBHOOK_MISSING_SIGNATURE', [
                'provider'   => $provider,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['message' => 'Signature manquante.'], 401);
        }

        // ── Replay attack protection ───────────────────────────────────────
        $timestamp = (int) ($request->header("X-{$provider}-Timestamp") ?? time());
        if (abs(time() - $timestamp) > self::REPLAY_WINDOW_SECONDS) {
            \Log::warning('WEBHOOK_REPLAY_ATTACK', [
                'provider'  => $provider,
                'timestamp' => $timestamp,
                'delta_s'   => abs(time() - $timestamp),
                'ip'        => $request->ip(),
            ]);
            return response()->json(['message' => 'Webhook expiré (replay attack prevented).'], 401);
        }

        // ── HMAC-SHA256 verification ───────────────────────────────────────
        $payload       = $request->getContent();
        $signedPayload = $timestamp ? "{$timestamp}.{$payload}" : $payload;
        $expected      = 'sha256=' . hash_hmac('sha256', $signedPayload, $secret);

        // hash_equals prevents timing attacks
        if (! hash_equals($expected, $signature)) {
            \Log::critical('WEBHOOK_INVALID_SIGNATURE', [
                'provider'        => $provider,
                'ip'              => $request->ip(),
                'expected_prefix' => substr($expected, 0, 16) . '...',
                'received_prefix' => substr($signature, 0, 16) . '...',
            ]);
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        return $next($request);
    }
}
