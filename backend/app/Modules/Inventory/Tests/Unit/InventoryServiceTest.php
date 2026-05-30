<?php

namespace App\Modules\Inventory\Tests\Unit;

use App\Modules\Inventory\Repositories\InventoryRepositoryInterface;
use App\Modules\Inventory\Services\InventoryService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class InventoryServiceTest extends TestCase
{
    private InventoryService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(InventoryRepositoryInterface::class);
$this->service    = new InventoryService($this->repository);
    }

    protected function tearDown(): void
    {
Mockery::close();
parent::tearDown();
    }

    /** @test */
    public function it_delegates_list_to_repository(): void
    {
$this->repository
    ->shouldReceive('all')
    ->once()
    ->with('tenant-abc', [])
    ->andReturn(collect());

$this->service->list('tenant-abc');

$this->addToAssertionCount(1);
    }

    // TODO: ajouter les tests métier spécifiques au module
}