<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-4A — matrice d'entrée de stock variantes × entrepôts + réception groupée par entrepôt.
 */
class VariantStockMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private string $token;
    private Product $product;
    private ProductVariant $varRouge;
    private ProductVariant $varBleu;
    private Warehouse $whA;
    private Warehouse $whB;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Mat', 'slug' => 'mat-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->manager = User::create(['name' => 'Mgr', 'email' => 'mgr@mat.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->manager->assignTenantRole('manager');
        $this->token = $this->manager->createToken('api')->plainTextToken;

        $this->whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Abidjan', 'code' => 'WH-ABJ', 'is_default' => true]);
        $this->whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Lomé', 'code' => 'WH-LFW']);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'TSHIRT', 'name' => 'T-shirt', 'price_amount' => 5000,
            'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => true, 'product_type' => Product::TYPE_VARIABLE,
        ]);
        $this->varRouge = ProductVariant::create(['product_id' => $this->product->id, 'tenant_id' => $this->tenant->id, 'sku' => 'TSHIRT-R', 'label' => 'Rouge', 'sort_order' => 1]);
        $this->varBleu  = ProductVariant::create(['product_id' => $this->product->id, 'tenant_id' => $this->tenant->id, 'sku' => 'TSHIRT-B', 'label' => 'Bleu', 'sort_order' => 2]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** Octroie une permission directe à un utilisateur dans le contexte d'équipe de son tenant. */
    private function grantPermission(User $user, string $permission): void
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $prev = $registrar->getPermissionsTeamId();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $registrar->setPermissionsTeamId($user->tenant_id);
        $user->givePermissionTo($permission);
        $registrar->setPermissionsTeamId($prev);
    }

    #[Test]
    public function the_matrix_lists_variants_and_accessible_warehouses(): void
    {
        $res = $this->getJson("/api/catalog/products/{$this->product->id}/variant-stock-matrix", $this->auth());

        $res->assertOk()
            ->assertJsonPath('has_variants', true)
            ->assertJsonCount(2, 'warehouses')
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.label', 'Rouge')
            ->assertJsonPath('rows.1.label', 'Bleu');

        // Entrepôt par défaut d'abord (Abidjan), cellules initialisées à 0.
        $this->assertSame($this->whA->id, $res->json('warehouses.0.id'));
        $this->assertSame(0, $res->json("rows.0.cells.{$this->whA->id}.quantity"));
    }

    #[Test]
    public function a_batch_delivery_routes_each_variant_to_its_warehouse(): void
    {
        $payload = ['items' => [
            ['product_id' => $this->product->id, 'variant_id' => $this->varRouge->id, 'warehouse_id' => $this->whA->id, 'quantity' => 12, 'unit_cost_cents' => 1000],
            ['product_id' => $this->product->id, 'variant_id' => $this->varRouge->id, 'warehouse_id' => $this->whB->id, 'quantity' => 3,  'unit_cost_cents' => 1000],
            ['product_id' => $this->product->id, 'variant_id' => $this->varBleu->id,  'warehouse_id' => $this->whA->id, 'quantity' => 7,  'unit_cost_cents' => 1200],
        ]];

        $this->postJson('/api/inventory/deliveries', $payload, $this->auth())
            ->assertCreated()
            ->assertJsonPath('count', 3);

        // Chaque cellule reflète la bonne quantité dans le bon entrepôt.
        $matrix = $this->getJson("/api/catalog/products/{$this->product->id}/variant-stock-matrix", $this->auth())->json();
        $rows = collect($matrix['rows'])->keyBy('label');

        $this->assertSame(12, $rows['Rouge']['cells'][$this->whA->id]['quantity']);
        $this->assertSame(3,  $rows['Rouge']['cells'][$this->whB->id]['quantity']);
        $this->assertSame(7,  $rows['Bleu']['cells'][$this->whA->id]['quantity']);
        $this->assertSame(0,  $rows['Bleu']['cells'][$this->whB->id]['quantity']);
        // Le coût unitaire alimente le CMUP de la cellule.
        $this->assertSame(1000, $rows['Rouge']['cells'][$this->whA->id]['unit_cost_cents']);
    }

    #[Test]
    public function a_restricted_operator_cannot_deliver_to_a_forbidden_warehouse(): void
    {
        // Opérateur NON-manager (donc restreignable) qui peut quand même réceptionner grâce à la
        // permission inventory.receive ; assigné uniquement à whA.
        $op = User::create(['name' => 'Op', 'email' => 'op@mat.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $op->assignTenantRole('member');
        $this->grantPermission($op, 'inventory.receive');
        \Illuminate\Support\Facades\DB::table('user_warehouses')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'user_id' => $op->id,
            'warehouse_id' => $this->whA->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $opToken = $op->createToken('api')->plainTextToken;

        $payload = ['items' => [
            ['product_id' => $this->product->id, 'variant_id' => $this->varRouge->id, 'warehouse_id' => $this->whB->id, 'quantity' => 5],
        ]];

        $this->postJson('/api/inventory/deliveries', $payload, ['Authorization' => "Bearer {$opToken}"])
            ->assertStatus(403);
    }

    #[Test]
    public function a_service_product_has_no_stock_matrix(): void
    {
        $service = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'SVC', 'name' => 'Installation', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SERVICE,
        ]);

        $this->getJson("/api/catalog/products/{$service->id}/variant-stock-matrix", $this->auth())
            ->assertStatus(422);
    }
}
