<?php
namespace App\Modules\Marketplace\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Events\StockUpdated;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Marketplace\Jobs\SyncMarketplaceListingJob;
use App\Modules\Marketplace\Jobs\NotifyManualCloseJob;
use App\Modules\Marketplace\Listeners\DispatchMarketplaceSync;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceSyncAlert;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceListingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $admin;
    private string $token;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => Plan::CODE_STARTER], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $this->tenant = Tenant::create(['name' => 'Test', 'slug' => 'test-mp', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr']]);
        $this->admin  = User::create(['name' => 'Admin', 'email' => 'admin@test.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->token = $this->admin->createToken('api')->plainTextToken;
        $this->warehouse = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'WH-MAIN', 'is_default' => true]);
        $this->product = Product::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'sku' => 'PRD-001', 'name' => 'Test Product',
            'price_amount' => 10000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false,
        ]);
    }

    #[Test]
    public function can_create_marketplace_listing(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/marketplace/listings', [
            'product_id'            => $this->product->id,
            'platform'              => 'facebook',
            'external_product_id'   => 'fb-prod-123',
            'is_auto_close_enabled' => true,
            'close_threshold'       => 0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform', 'facebook')
            ->assertJsonPath('data.sync_status', 'active');
    }

    #[Test]
    public function listing_requires_valid_platform(): void
    {
        $this->withToken($this->token)->postJson('/api/marketplace/listings', [
            'product_id'          => $this->product->id,
            'platform'            => 'invalid_platform',
            'external_product_id' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['platform']);
    }

    #[Test]
    public function can_list_platforms(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/marketplace/platforms');
        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('facebook', $codes);
        $this->assertContains('whatsapp_catalog', $codes);
    }

    #[Test]
    public function stock_update_event_dispatches_marketplace_sync_job_when_auto_close_enabled(): void
    {
        Queue::fake();

        $listing = MarketplaceListing::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'platform' => 'facebook', 'external_product_id' => 'fb-123',
            'is_auto_close_enabled' => true, 'close_threshold' => 0, 'sync_status' => 'active',
        ]);

        $stock = Stock::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'quantity' => 0,
            'reserved_quantity' => 0, 'low_stock_threshold' => 5,
            'unit_cost_cents' => 0, 'total_value_cents' => 0,
        ]);

        // Manually invoke the listener (no need to fire real HTTP)
        $listener = app(DispatchMarketplaceSync::class);
        $listener->handle(new StockUpdated($stock, -1, 'pos'));

        Queue::assertPushed(SyncMarketplaceListingJob::class, function ($job) use ($listing) {
            return true; // job was dispatched
        });
    }

    #[Test]
    public function manual_close_alert_is_created_when_auto_close_disabled(): void
    {
        Queue::fake();

        MarketplaceListing::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'platform' => 'whatsapp_catalog', 'external_product_id' => 'wa-456',
            'is_auto_close_enabled' => false, 'close_threshold' => 0, 'sync_status' => 'active',
        ]);

        $stock = Stock::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'quantity' => 0,
            'reserved_quantity' => 0, 'low_stock_threshold' => 5,
            'unit_cost_cents' => 0, 'total_value_cents' => 0,
        ]);

        $listener = app(DispatchMarketplaceSync::class);
        $listener->handle(new StockUpdated($stock, -1, 'pos'));

        Queue::assertPushed(NotifyManualCloseJob::class);
        Queue::assertNotPushed(SyncMarketplaceListingJob::class);
    }

    #[Test]
    public function can_mark_alert_as_read(): void
    {
        $listing = MarketplaceListing::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'platform' => 'facebook', 'external_product_id' => 'fb-789', 'sync_status' => 'active',
        ]);
        $alert = MarketplaceSyncAlert::create([
            'tenant_id' => $this->tenant->id, 'listing_id' => $listing->id,
            'severity' => 'warning', 'type' => 'close_failed',
            'message' => 'Test alert', 'requires_action' => true, 'is_read' => false,
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/marketplace/alerts/{$alert->id}/read")
            ->assertOk();

        $this->assertTrue($alert->fresh()->is_read);
    }
}
