<?php

namespace App\Modules\Payments\Tests\Modular;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsModuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * Flux complet CRUD du module Payments.
     * Ce test vérifie le comportement de bout en bout.
     */
    public function complete_payments_crud_flow(): void
    {
$this->markTestIncomplete('Implémenter le flux complet du module Payments');
    }
}