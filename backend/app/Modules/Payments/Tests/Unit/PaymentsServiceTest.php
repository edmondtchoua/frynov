<?php

namespace App\Modules\Payments\Tests\Unit;

use App\Modules\Payments\Repositories\PaymentsRepositoryInterface;
use App\Modules\Payments\Services\PaymentsService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PaymentsServiceTest extends TestCase
{
    private PaymentsService $service;
    private MockInterface $repository;

    protected function setUp(): void
    {
parent::setUp();
$this->repository = Mockery::mock(PaymentsRepositoryInterface::class);
$this->service    = new PaymentsService($this->repository);
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