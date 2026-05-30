<?php

namespace App\Modules\Delivery\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_deliverys(): void
    {
$response = $this->getJson('/api/deliverys');

// Doit retourner 401 sans authentification
$response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_other_tenant_deliverys(): void
    {
// TODO: tester l'isolation multitenant
$this->markTestIncomplete('Isolation multitenant à tester');
    }
}