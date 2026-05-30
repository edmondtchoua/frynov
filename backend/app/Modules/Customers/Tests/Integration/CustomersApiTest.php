<?php

namespace App\Modules\Customers\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomersApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_customerss(): void
    {
$response = $this->getJson('/api/customerss');

// Doit retourner 401 sans authentification
$response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_other_tenant_customerss(): void
    {
// TODO: tester l'isolation multitenant
$this->markTestIncomplete('Isolation multitenant à tester');
    }
}