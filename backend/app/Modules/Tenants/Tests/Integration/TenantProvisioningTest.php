<?php

namespace App\Modules\Tenants\Tests\Integration;

use App\Modules\Tenants\Models\Tenant;
use App\Modules\Tenants\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private TenantProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantProvisioningService;
    }

    /** @test */
    public function it_creates_a_tenant_with_correct_slug(): void
    {
        $tenant = $this->service->provision([
            'name' => 'Boutique Dakar',
            'plan' => 'starter',
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('boutique-dakar', $tenant->slug);
        $this->assertEquals('active', $tenant->status);
        $this->assertDatabaseHas('tenants', ['slug' => 'boutique-dakar']);
    }

    /** @test */
    public function it_deduplicates_slugs_for_same_name(): void
    {
        $first  = $this->service->provision(['name' => 'Shop Lagos']);
        $second = $this->service->provision(['name' => 'Shop Lagos']);

        $this->assertEquals('shop-lagos', $first->slug);
        $this->assertEquals('shop-lagos-1', $second->slug);
    }

    /** @test */
    public function it_injects_default_settings(): void
    {
        $tenant = $this->service->provision(['name' => 'Test Shop']);

        $this->assertNotNull($tenant->settings);
        $this->assertEquals('XOF', $tenant->settings['currency']);
        $this->assertEquals('fr', $tenant->settings['locale']);
        $this->assertArrayHasKey('order_prefix', $tenant->settings);
    }

    /** @test */
    public function it_sets_starter_plan_by_default(): void
    {
        $tenant = $this->service->provision(['name' => 'My Shop']);

        $this->assertEquals('starter', $tenant->plan);
    }
}
