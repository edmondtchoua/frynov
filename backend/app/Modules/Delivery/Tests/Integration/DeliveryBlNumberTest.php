<?php

namespace App\Modules\Delivery\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for immutable sequential BL numbering.
 */
class DeliveryBlNumberTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $svc;
    private Tenant $tenant;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name' => 'BL Test', 'slug' => 'bl-test', 'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Abidjan', 'locale' => 'fr'],
        ]);

        $user = User::create([
            'name' => 'User', 'email' => 'u@test.sn',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id,
        ]);
        $user->assignTenantRole('admin');
        $this->userId = $user->id;
        $this->svc = app(DeliveryService::class);
    }

    #[Test]
    public function delivery_gets_sequential_bl_number(): void
    {
        $d1 = $this->svc->create([], $this->tenant->id, $this->userId);
        $d2 = $this->svc->create([], $this->tenant->id, $this->userId);
        $d3 = $this->svc->create([], $this->tenant->id, $this->userId);

        $this->assertSame('BL-00001', $d1->number);
        $this->assertSame('BL-00002', $d2->number);
        $this->assertSame('BL-00003', $d3->number);
    }

    #[Test]
    public function bl_number_is_immutable_after_creation(): void
    {
        $d = $this->svc->create([], $this->tenant->id, $this->userId);
        $this->assertNotNull($d->number);

        $this->expectException(\DomainException::class);
        $d->update(['number' => 'BL-99999']);
    }

    #[Test]
    public function bl_numbers_are_unique_per_tenant(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'BL Test 2', 'slug' => 'bl-test-2', 'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Abidjan', 'locale' => 'fr'],
        ]);

        $d1 = $this->svc->create([], $this->tenant->id,  $this->userId);
        $d2 = $this->svc->create([], $tenant2->id,        $this->userId);

        // Both get BL-00001 — independent per-tenant sequences
        $this->assertSame('BL-00001', $d1->number);
        $this->assertSame('BL-00001', $d2->number);
    }

    #[Test]
    public function bl_number_is_stored_in_database(): void
    {
        $d = $this->svc->create([], $this->tenant->id, $this->userId);
        $this->assertDatabaseHas('deliveries', ['id' => $d->id, 'number' => 'BL-00001']);
    }
}
