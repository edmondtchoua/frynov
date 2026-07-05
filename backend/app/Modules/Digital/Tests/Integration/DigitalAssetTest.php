<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalAsset;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Services\DigitalAssetService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5I — fichiers privés des produits digitaux + téléchargement par lien signé et expirable.
 */
class DigitalAssetTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '77777777-7777-4777-8777-777777777777';

    private Tenant $tenant;
    private User $user;
    private string $token;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Ast', 'slug' => 'ast-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->seedCustomer(self::CUSTOMER_ID, $this->tenant->id); // RC-20 (P-5) — le customer_id doit exister
        $this->user = User::create(['name' => 'M', 'email' => 'm@ast.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function digitalProduct(string $sku = 'EBOOK'): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => $sku, 'name' => 'Ebook PHP', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
        ]);
    }

    private function uploadAsset(Product $p, string $filename = 'ebook.pdf', string $content = 'PDF-BYTES'): string
    {
        $file = UploadedFile::fake()->createWithContent($filename, $content);

        return $this->postJson("/api/digital/products/{$p->id}/assets", ['file' => $file], $this->auth())
            ->assertCreated()
            ->json('data.id');
    }

    private function sell(Product $p): \App\Modules\Orders\Models\Order
    {
        $order = $this->orders->create(['customer_id' => self::CUSTOMER_ID, 'items' => [['product_id' => $p->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    #[Test]
    public function it_uploads_a_private_asset_and_stores_it_on_the_private_disk(): void
    {
        $ebook = $this->digitalProduct();
        $assetId = $this->uploadAsset($ebook);

        $asset = DigitalAsset::withoutTenantScope()->find($assetId);
        $this->assertNotNull($asset->path);
        $this->assertNotNull($asset->checksum);
        Storage::disk('local')->assertExists($asset->path);
        // Le chemin n'est jamais exposé dans le payload API.
        $this->getJson("/api/digital/products/{$ebook->id}/assets", $this->auth())
            ->assertOk()->assertJsonMissingPath('data.0.path');
    }

    #[Test]
    public function it_rejects_a_non_whitelisted_extension(): void
    {
        // RC-9 F-7 — un type dangereux en rendu inline (html/svg/js…) est refusé.
        $ebook = $this->digitalProduct();
        foreach (['evil.html', 'x.svg', 'a.js'] as $bad) {
            $file = UploadedFile::fake()->createWithContent($bad, '<script>alert(1)</script>');
            $this->postJson("/api/digital/products/{$ebook->id}/assets", ['file' => $file], $this->auth())
                ->assertStatus(422);
        }
        $this->assertSame(0, DigitalAsset::withoutTenantScope()->count());
    }

    #[Test]
    public function it_sanitizes_the_stored_filename(): void
    {
        // RC-9 F-7 — le nom client (chemin + caractères douteux) est assaini avant stockage.
        $ebook = $this->digitalProduct();
        $file  = UploadedFile::fake()->createWithContent('manuel.pdf', 'PDF');
        // Force un nom d'origine hostile en conservant l'extension autorisée.
        $hostile = UploadedFile::fake()->createWithContent('../../etc/pa ss<x>.pdf', 'PDF');

        $id = $this->postJson("/api/digital/products/{$ebook->id}/assets", ['file' => $hostile], $this->auth())
            ->assertCreated()->json('data.id');

        $name = DigitalAsset::withoutTenantScope()->find($id)->name;
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('<', $name);
        $this->assertStringEndsWith('.pdf', $name);
    }

    #[Test]
    public function a_non_digital_product_rejects_an_asset(): void
    {
        $physical = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'MUG', 'name' => 'Mug', 'price_amount' => 3000, 'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE]);
        $file = UploadedFile::fake()->createWithContent('x.pdf', 'x');

        $this->postJson("/api/digital/products/{$physical->id}/assets", ['file' => $file], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function the_access_endpoint_returns_a_signed_link_that_downloads_the_file(): void
    {
        $ebook = $this->digitalProduct();
        $this->uploadAsset($ebook, 'ebook.pdf', 'HELLO-PDF');
        $order = $this->sell($ebook);
        $ent   = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();

        $links = $this->getJson("/api/digital/access/{$ent->access_token}", $this->auth())
            ->assertOk()
            ->json('data.download_urls');

        $this->assertCount(1, $links);
        $this->assertSame('ebook.pdf', $links[0]['name']);

        $res = $this->get($links[0]['url'])->assertOk();
        $this->assertSame('HELLO-PDF', $res->streamedContent());
    }

    #[Test]
    public function a_revoked_entitlement_blocks_the_signed_link(): void
    {
        $ebook = $this->digitalProduct();
        $this->uploadAsset($ebook);
        $order = $this->sell($ebook);
        $ent   = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();

        $url = $this->getJson("/api/digital/access/{$ent->access_token}", $this->auth())->json('data.download_urls.0.url');

        // Révocation → le lien (signature pourtant valide) doit échouer.
        $ent->update(['status' => DigitalEntitlement::STATUS_REVOKED, 'revoked_at' => now()]);
        $this->get($url)->assertStatus(403);
    }

    #[Test]
    public function an_unsigned_or_tampered_download_url_is_rejected(): void
    {
        $ebook   = $this->digitalProduct();
        $assetId = $this->uploadAsset($ebook);
        $order   = $this->sell($ebook);
        $ent     = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();

        // Sans signature → 403 (middleware signed).
        $this->get("/api/digital/download/{$ent->access_token}/{$assetId}")->assertStatus(403);
    }

    #[Test]
    public function a_token_cannot_download_an_asset_of_another_product(): void
    {
        $ebookA = $this->digitalProduct('EBOOK-A');
        $ebookB = $this->digitalProduct('EBOOK-B');
        $assetB = $this->uploadAsset($ebookB);          // asset du produit B
        $orderA = $this->sell($ebookA);                  // entitlement du produit A
        $entA   = DigitalEntitlement::withoutTenantScope()->where('order_id', $orderA->id)->first();

        // Lien signé valide token(A) + asset(B) → l'asset n'appartient pas au produit de l'entitlement → 404.
        $url = URL::temporarySignedRoute('digital.download', now()->addMinutes(15), ['token' => $entA->access_token, 'asset' => $assetB]);
        $this->get($url)->assertStatus(404);
    }
}
