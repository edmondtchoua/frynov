<?php

namespace App\Modules\Inventory\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_inventorys(): void
    {
$response = $this->getJson('/api/inventorys');

// Doit retourner 401 sans authentification
$response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_other_tenant_inventorys(): void
    {
// TODO: tester l'isolation multitenant
$this->markTestIncomplete('Isolation multitenant à tester');
    }
}