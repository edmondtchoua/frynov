<?php

namespace App\Modules\Customers\Tests\Modular;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomersModuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * Flux complet CRUD du module Customers.
     * Ce test vérifie le comportement de bout en bout.
     */
    public function complete_customers_crud_flow(): void
    {
$this->markTestIncomplete('Implémenter le flux complet du module Customers');
    }
}