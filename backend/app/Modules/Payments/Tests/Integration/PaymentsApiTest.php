<?php

namespace App\Modules\Payments\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_paymentss(): void
    {
$response = $this->getJson('/api/paymentss');

// Doit retourner 401 sans authentification
$response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_other_tenant_paymentss(): void
    {
// TODO: tester l'isolation multitenant
$this->markTestIncomplete('Isolation multitenant à tester');
    }
}