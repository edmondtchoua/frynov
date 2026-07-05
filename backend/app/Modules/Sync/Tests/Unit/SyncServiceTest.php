<?php

namespace App\Modules\Sync\Tests\Unit;

use App\Modules\Sync\Models\Sync;
use App\Modules\Sync\Repositories\EloquentSyncRepository;
use App\Modules\Sync\Repositories\SyncRepositoryInterface;
use App\Modules\Sync\Services\SyncService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(SyncService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Boutique Sync',
            'slug'   => 'boutique-sync',
            'plan'   => 'starter',
            'status' => 'active',
        ]);
    }

    private function otherTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name'   => 'Autre',
            'slug'   => $slug,
            'plan'   => 'starter',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function the_repository_interface_resolves_to_the_eloquent_implementation(): void
    {
        $this->assertInstanceOf(
            EloquentSyncRepository::class,
            $this->app->make(SyncRepositoryInterface::class),
        );
    }

    #[Test]
    public function it_creates_a_sync_scoped_to_the_tenant(): void
    {
        $sync = $this->service->create([], $this->tenant->id);

        $this->assertInstanceOf(Sync::class, $sync);
        $this->assertSame($this->tenant->id, $sync->tenant_id);
        $this->assertDatabaseHas('syncs', [
            'id'        => $sync->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_finds_a_sync_by_id_for_its_tenant(): void
    {
        $sync  = $this->service->create([], $this->tenant->id);
        $found = $this->service->findOrFail($sync->id, $this->tenant->id);

        $this->assertSame($sync->id, $found->id);
    }

    #[Test]
    public function it_aborts_404_when_the_sync_does_not_exist(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->service->findOrFail('00000000-0000-0000-0000-000000000000', $this->tenant->id);
    }

    #[Test]
    public function it_aborts_404_when_the_sync_belongs_to_another_tenant(): void
    {
        $sync = $this->service->create([], $this->otherTenant('autre-find')->id);

        $this->expectException(NotFoundHttpException::class);

        $this->service->findOrFail($sync->id, $this->tenant->id);
    }

    #[Test]
    public function it_updates_a_sync_and_returns_a_fresh_instance(): void
    {
        $sync = $this->service->create([], $this->tenant->id);

        $updated = $this->service->update($sync->id, [], $this->tenant->id);

        $this->assertInstanceOf(Sync::class, $updated);
        $this->assertSame($sync->id, $updated->id);
    }

    #[Test]
    public function it_cannot_update_a_sync_of_another_tenant(): void
    {
        $sync = $this->service->create([], $this->otherTenant('autre-update')->id);

        $this->expectException(NotFoundHttpException::class);

        $this->service->update($sync->id, [], $this->tenant->id);
    }

    #[Test]
    public function it_soft_deletes_a_sync(): void
    {
        $sync = $this->service->create([], $this->tenant->id);

        $this->service->delete($sync->id, $this->tenant->id);

        $this->assertSoftDeleted('syncs', ['id' => $sync->id]);
    }

    #[Test]
    public function it_cannot_delete_a_sync_of_another_tenant(): void
    {
        $sync = $this->service->create([], $this->otherTenant('autre-delete')->id);

        $this->expectException(NotFoundHttpException::class);

        $this->service->delete($sync->id, $this->tenant->id);
    }

    #[Test]
    public function it_lists_only_the_syncs_of_the_given_tenant(): void
    {
        $other = $this->otherTenant('autre-list');

        $this->service->create([], $this->tenant->id);
        $this->service->create([], $this->tenant->id);
        $this->service->create([], $other->id); // must NOT appear

        $page = $this->service->list($this->tenant->id);

        $this->assertSame(2, $page->total());

        foreach ($page->items() as $item) {
            $this->assertSame($this->tenant->id, $item->tenant_id);
        }
    }

    #[Test]
    public function it_paginates_the_list_with_twenty_items_per_page(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->service->create([], $this->tenant->id);
        }

        $page = $this->service->list($this->tenant->id);

        $this->assertSame(20, $page->perPage());
        $this->assertSame(25, $page->total());
        $this->assertSame(2, $page->lastPage());
    }
}
