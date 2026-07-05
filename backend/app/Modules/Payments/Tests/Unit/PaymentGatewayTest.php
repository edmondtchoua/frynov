<?php

namespace App\Modules\Payments\Tests\Unit;

use App\Modules\Payments\Gateways\ManualGateway;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * P6-3 — abstraction PaymentGateway. AUCUN rail réel n'est actif tant que le flag
 * `billing.gateways_enabled` est false ; seul `manual` (approche A) est disponible.
 */
class PaymentGatewayTest extends TestCase
{
    #[Test]
    public function the_manual_gateway_is_always_available(): void
    {
        $manager = new PaymentGatewayManager();

        $this->assertContains('manual', $manager->available());
        $this->assertInstanceOf(ManualGateway::class, $manager->get('manual'));
    }

    #[Test]
    public function a_real_gateway_is_refused_while_the_flag_is_off(): void
    {
        config(['billing.gateways_enabled' => false]);

        $this->expectException(RuntimeException::class);
        (new PaymentGatewayManager())->get('stripe');
    }

    #[Test]
    public function an_unknown_gateway_throws_even_when_the_flag_is_on(): void
    {
        config(['billing.gateways_enabled' => true]);

        $this->expectException(InvalidArgumentException::class);
        (new PaymentGatewayManager())->get('does-not-exist');
    }

    #[Test]
    public function the_manual_gateway_makes_no_external_call(): void
    {
        $gateway = new ManualGateway();

        $intent = $gateway->initiate(['amount_minor' => 1000]);
        $this->assertSame('pending', $intent['status']);
        $this->assertSame('manual', $intent['mode']);
        $this->assertNull($intent['redirect_url']);

        $this->assertSame('pending', $gateway->verify('ref-1'));
        $this->assertFalse($gateway->refund('ref-1'));
    }
}
