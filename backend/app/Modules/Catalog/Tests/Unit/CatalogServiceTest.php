<?php

namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Catalog\Repositories\CatalogRepositoryInterface;
use App\Modules\Catalog\Services\CatalogService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CatalogServiceTest extends TestCase
{
    private CatalogService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(CatalogRepositoryInterface::class);
$this->service    = new CatalogService($this->repository);
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