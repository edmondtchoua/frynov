<?php

namespace App\Modules\Delivery\Tests\Unit;

use App\Modules\Delivery\Repositories\DeliveryRepositoryInterface;
use App\Modules\Delivery\Services\DeliveryService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DeliveryServiceTest extends TestCase
{
    private DeliveryService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(DeliveryRepositoryInterface::class);
$this->service    = new DeliveryService($this->repository);
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