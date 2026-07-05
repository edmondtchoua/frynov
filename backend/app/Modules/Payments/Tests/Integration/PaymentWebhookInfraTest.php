<?php

namespace App\Modules\Payments\Tests\Integration;

use App\Modules\Billing\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * P6-3 — infra webhook signée (inerte). Prouve que VerifyWebhookSignature fonctionne dès
 * qu'un secret est configuré, et que la route webhook n'existe PAS tant que le flag est off.
 */
class PaymentWebhookInfraTest extends TestCase
{
    use RefreshDatabase;

    private function sign(string $payload, int $ts, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', "{$ts}.{$payload}", $secret);
    }

    private function passThrough(Request $request, string $provider = 'test'): Response
    {
        return (new VerifyWebhookSignature())->handle($request, fn () => response('ok', 200), $provider);
    }

    #[Test]
    public function a_correctly_signed_webhook_passes(): void
    {
        config(['billing.webhooks.test.secret' => 'shhh']);
        $payload = '{"event":"charge.success"}';
        $ts      = time();

        $req = Request::create('/api/webhooks/payments/test', 'POST', [], [], [], [], $payload);
        $req->headers->set('X-test-Timestamp', (string) $ts);
        $req->headers->set('X-test-Signature', $this->sign($payload, $ts, 'shhh'));

        $this->assertSame(200, $this->passThrough($req)->getStatusCode());
    }

    #[Test]
    public function an_invalid_signature_is_rejected(): void
    {
        config(['billing.webhooks.test.secret' => 'shhh']);
        $req = Request::create('/x', 'POST', [], [], [], [], '{"x":1}');
        $req->headers->set('X-test-Timestamp', (string) time());
        $req->headers->set('X-test-Signature', 'sha256=deadbeef');

        $this->assertSame(401, $this->passThrough($req)->getStatusCode());
    }

    #[Test]
    public function a_replayed_webhook_is_rejected(): void
    {
        config(['billing.webhooks.test.secret' => 'shhh']);
        $payload = '{"x":1}';
        $ts      = time() - 600; // hors fenêtre de 5 minutes

        $req = Request::create('/x', 'POST', [], [], [], [], $payload);
        $req->headers->set('X-test-Timestamp', (string) $ts);
        $req->headers->set('X-test-Signature', $this->sign($payload, $ts, 'shhh'));

        $this->assertSame(401, $this->passThrough($req)->getStatusCode());
    }

    #[Test]
    public function an_unconfigured_provider_is_refused(): void
    {
        config(['billing.webhooks' => []]);
        $req = Request::create('/x', 'POST', [], [], [], [], '{}');

        $this->assertSame(500, $this->passThrough($req, 'nope')->getStatusCode());
    }

    #[Test]
    public function the_webhook_route_is_absent_when_gateways_are_disabled(): void
    {
        // Flag false par défaut (NO-GO) → aucune route webhook enregistrée → 404 (défaut sûr).
        $this->postJson('/api/webhooks/payments/stripe', [])->assertNotFound();
    }
}
