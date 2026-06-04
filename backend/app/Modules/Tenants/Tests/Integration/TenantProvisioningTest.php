<?php

namespace App\Modules\Tenants\Tests\Integration;

use App\Modules\Tenants\Models\Tenant;
use App\Modules\Tenants\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
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

    #[Test]
    public function it_deduplicates_slugs_for_same_name(): void
    {
        $first  = $this->service->provision(['name' => 'Shop Lagos']);
        $second = $this->service->provision(['name' => 'Shop Lagos']);

        $this->assertEquals('shop-lagos', $first->slug);
        $this->assertEquals('shop-lagos-1', $second->slug);
    }

    #[Test]
    public function it_injects_default_settings(): void
    {
        $tenant = $this->service->provision(['name' => 'Test Shop']);

        $this->assertNotNull($tenant->settings);
        $this->assertEquals('XOF', $tenant->settings['currency']);
        $this->assertEquals('fr', $tenant->settings['locale']);
        $this->assertArrayHasKey('order_prefix', $tenant->settings);
    }

    #[Test]
    public function it_sets_starter_plan_by_default(): void
    {
        $tenant = $this->service->provision(['name' => 'My Shop']);

        $this->assertEquals('starter', $tenant->plan);
    }

    #[Test]
    public function it_slugifies_accents_and_keeps_an_ascii_kebab_slug(): void
    {
        $tenant = $this->service->provision(['name' => 'Café Délice']);

        // Accents transliterated, lowercased, kebab-cased — and strictly ASCII.
        $this->assertEquals('cafe-delice', $tenant->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $tenant->slug);
    }

    #[Test]
    public function it_falls_back_to_tenant_when_the_name_has_no_sluggable_characters(): void
    {
        // Str::slug('!!!') === '' → must not produce an empty slug.
        $first  = $this->service->provision(['name' => '!!!']);
        $second = $this->service->provision(['name' => '###']);

        $this->assertEquals('tenant', $first->slug);
        $this->assertEquals('tenant-1', $second->slug);
    }

    #[Test]
    public function it_deduplicates_across_multiple_collisions(): void
    {
        // Regression: the exact-match loop must increment past a single suffix.
        // The former LIKE-based count could collide after a mid-list delete.
        $a = $this->service->provision(['name' => 'Boutique']);
        $b = $this->service->provision(['name' => 'Boutique']);
        $c = $this->service->provision(['name' => 'Boutique']);

        $this->assertEquals('boutique',   $a->slug);
        $this->assertEquals('boutique-1', $b->slug);
        $this->assertEquals('boutique-2', $c->slug);
        $this->assertDatabaseCount('tenants', 3);
    }
}
