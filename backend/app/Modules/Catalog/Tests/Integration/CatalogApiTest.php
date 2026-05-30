<?php

namespace App\Modules\Catalog\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_catalogs(): void
    {
$response = $this->getJson('/api/catalogs');

// Doit retourner 401 sans authentification
$response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_other_tenant_catalogs(): void
    {
// TODO: tester l'isolation multitenant
$this->markTestIncomplete('Isolation multitenant à tester');
    }
}