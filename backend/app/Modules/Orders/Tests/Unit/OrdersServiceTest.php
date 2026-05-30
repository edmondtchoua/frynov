<?php

namespace App\Modules\Orders\Tests\Unit;

use App\Modules\Orders\Repositories\OrdersRepositoryInterface;
use App\Modules\Orders\Services\OrdersService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class OrdersServiceTest extends TestCase
{
    private OrdersService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(OrdersRepositoryInterface::class);
$this->service    = new OrdersService($this->repository);
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