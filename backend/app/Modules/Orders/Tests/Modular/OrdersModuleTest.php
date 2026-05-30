<?php

namespace App\Modules\Orders\Tests\Modular;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersModuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * Flux complet CRUD du module Orders.
     * Ce test vérifie le comportement de bout en bout.
     */
    public function complete_orders_crud_flow(): void
    {
$this->markTestIncomplete('Implémenter le flux complet du module Orders');
    }
}