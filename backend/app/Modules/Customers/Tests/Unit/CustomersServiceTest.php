<?php

namespace App\Modules\Customers\Tests\Unit;

use App\Modules\Customers\Repositories\CustomersRepositoryInterface;
use App\Modules\Customers\Services\CustomersService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CustomersServiceTest extends TestCase
{
    private CustomersService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(CustomersRepositoryInterface::class);
$this->service    = new CustomersService($this->repository);
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