<?php

namespace App\Modules\Sync\Tests\Modular;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncModuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * Flux complet CRUD du module Sync.
     * Ce test vérifie le comportement de bout en bout.
     */
    public function complete_sync_crud_flow(): void
    {
$this->markTestIncomplete('Implémenter le flux complet du module Sync');
    }
}