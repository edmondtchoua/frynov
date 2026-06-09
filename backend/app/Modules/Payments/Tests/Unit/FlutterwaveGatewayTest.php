<?php

namespace App\Modules\Payments\Tests\Unit;

use App\Modules\Payments\Gateways\FlutterwaveGateway;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P6-4 — adaptateur de référence Flutterwave, testé contre HTTP mocké (aucun appel réseau réel).
 * Inerte tant que le flag + la clé ne sont pas configurés ; ce test les force pour exercer le contrat.
 */
class FlutterwaveGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'billing.gateways_enabled'       => true,
            'billing.flutterwave.base_url'   => 'https://api.flutterwave.test',
            'billing.flutterwave.secret_key' => 'FLWSECK_TEST',
        ]);
    }

    #[Test]
    public function the_manager_resolves_flutterwave_when_the_flag_is_on(): void
    {
        $manager = new PaymentGatewayManager();
        $this->assertContains('flutterwave', $manager->available());
        $this->assertInstanceOf(FlutterwaveGateway::class, $manager->get('flutterwave'));
    }

    #[Test]
    public function initiate_returns_a_pending_intent_with_a_redirect_link(): void
    {
        Http::fake([
            '*/v3/payments' => Http::response(['status' => 'success', 'data' => ['tx_ref' => 'ORD-1', 'link' => 'https://pay.flutterwave.test/abc']], 200),
        ]);

        $intent = (new FlutterwaveGateway())->initiate([
            'tx_ref' => 'ORD-1', 'amount_minor' => 500000, 'currency' => 'NGN',
            'redirect_url' => 'https://app/return', 'customer_email' => 'a@b.c',
        ]);

        $this->assertSame('pending', $intent['status']);
        $this->assertSame('auto', $intent['mode']);
        $this->assertSame('https://pay.flutterwave.test/abc', $intent['redirect_url']);
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer FLWSECK_TEST'));
    }

    #[Test]
    public function initiate_returns_failed_on_a_provider_error(): void
    {
        Http::fake(['*/v3/payments' => Http::response(['status' => 'error'], 400)]);

        $this->assertSame('failed', (new FlutterwaveGateway())->initiate(['tx_ref' => 'X'])['status']);
    }

    #[Test]
    public function verify_maps_the_provider_status_to_a_canonical_status(): void
    {
        Http::fakeSequence('*/verify_by_reference*')
            ->push(['data' => ['status' => 'successful']], 200)
            ->push(['data' => ['status' => 'failed']], 200)
            ->push(['data' => ['status' => 'pending']], 200);

        $gateway = new FlutterwaveGateway();
        $this->assertSame('succeeded', $gateway->verify('ORD-1'));
        $this->assertSame('failed', $gateway->verify('ORD-2'));
        $this->assertSame('pending', $gateway->verify('ORD-3'));
    }

    #[Test]
    public function refund_succeeds_when_the_provider_acknowledges(): void
    {
        Http::fakeSequence('*/refund')
            ->push(['status' => 'success'], 200)
            ->push(['status' => 'error'], 400);

        $gateway = new FlutterwaveGateway();
        $this->assertTrue($gateway->refund('123', 100000));
        $this->assertFalse($gateway->refund('123'));
    }
}
