<?php

namespace App\Modules\Sync\Tests\Unit;

use App\Modules\Sync\Repositories\SyncRepositoryInterface;
use App\Modules\Sync\Services\SyncService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SyncServiceTest extends TestCase
{
    private SyncService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(SyncRepositoryInterface::class);
$this->service    = new SyncService($this->repository);
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