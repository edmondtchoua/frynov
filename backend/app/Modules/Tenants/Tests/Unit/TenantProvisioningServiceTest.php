<?php

namespace App\Modules\Tenants\Tests\Unit;

use App\Modules\Tenants\Models\Tenant;
use App\Modules\Tenants\Services\TenantProvisioningService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TenantProvisioningServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_slug_from_name(): void
    {
        $service = new TenantProvisioningService;

        // On accède à la méthode privée via reflection pour la tester directement
        $reflection = new \ReflectionMethod($service, 'generateSlug');

        // Le slug doit être en minuscules avec tirets
        // Note : sans DB, on ne peut pas tester la déduplication ici (test d'intégration)
        $this->markTestIncomplete(
            'generateSlug fait une query DB — à tester en intégration'
        );
    }
}
