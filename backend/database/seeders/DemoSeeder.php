<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\Inventory\Models\FiscalPeriod;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustmentRequest;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Payments\Models\Payment;
use App\Modules\Pos\Models\CashRegisterSession;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Tenants\Services\TenantProvisioningService;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Creates 3 demo tenants covering all plans, user roles and features.
 *
 * ┌─────────────────────────┬──────────────┬───────────────────────────────────────┐
 * │ Tenant                  │ Plan         │ Accounts (password: Secret123!)        │
 * ├─────────────────────────┼──────────────┼───────────────────────────────────────┤
 * │ Boutique Afrik Style    │ Starter      │ admin@afrikstyle.sn                   │
 * │ (Dakar, SN) - trialing  │ trialing     │ manager@afrikstyle.sn                 │
 * │                         │              │ membre@afrikstyle.sn                  │
 * │                         │              │ lecteur@afrikstyle.sn                 │
 * ├─────────────────────────┼──────────────┼───────────────────────────────────────┤
 * │ TechZone CI             │ Pro          │ admin@techzone.ci                     │
 * │ (Abidjan, CI) - active  │ active       │ manager@techzone.ci                   │
 * │                         │              │ vente1@techzone.ci                    │
 * │                         │              │ vente2@techzone.ci                    │
 * ├─────────────────────────┼──────────────┼───────────────────────────────────────┤
 * │ Grossiste Douala        │ Enterprise   │ admin@grossiste.cm                    │
 * │ (Douala, CM) - active   │ active       │ manager@grossiste.cm                  │
 * │                         │              │ stock1@grossiste.cm                   │
 * │                         │              │ stock2@grossiste.cm                   │
 * │                         │              │ logistique@grossiste.cm               │
 * │                         │              │ audit@grossiste.cm (viewer)           │
 * └─────────────────────────┴──────────────┴───────────────────────────────────────┘
 */
class DemoSeeder extends Seeder
{
    private SubscriptionService  $subscriptions;
    private ModuleRegistryService $moduleRegistry;

    public function __construct(
        SubscriptionService   $subscriptions,
        ModuleRegistryService $moduleRegistry,
    ) {
        $this->subscriptions  = $subscriptions;
        $this->moduleRegistry = $moduleRegistry;
    }

    public function run(): void
    {
        $this->command->info('Seeding demo data...');

        // ── Tenant 1 : Boutique Afrik Style (Starter / trialing) ─────────────
        $t1 = $this->createTenant(
            name:     'Boutique Afrik Style',
            country:  'SN',
            currency: 'XOF',
            domain:   'afrikstyle.sn',
        );
        $this->createStarter($t1);
        $this->seedTeam($t1, 'afrikstyle.sn', [
            ['name' => 'Fatou Diallo',    'role' => 'admin',   'email' => 'admin@afrikstyle.sn'],
            ['name' => 'Moussa Ndiaye',   'role' => 'manager', 'email' => 'manager@afrikstyle.sn'],
            ['name' => 'Aïcha Sow',       'role' => 'member',  'email' => 'membre@afrikstyle.sn'],
            ['name' => 'Ibrahima Balde',  'role' => 'viewer',  'email' => 'lecteur@afrikstyle.sn'],
        ]);
        $this->seedCatalogAfrikStyle($t1);
        $this->seedCustomersAndOrders($t1, 6, 8);

        // ── Tenant 2 : TechZone CI (Pro / active) ─────────────────────────────
        $t2 = $this->createTenant(
            name:     'TechZone CI',
            country:  'CI',
            currency: 'XOF',
            domain:   'techzone.ci',
        );
        $this->createProSubscription($t2);
        $this->seedTeam($t2, 'techzone.ci', [
            ['name' => 'Kouamé Brou',      'role' => 'admin',   'email' => 'admin@techzone.ci'],
            ['name' => 'Amina Koné',       'role' => 'manager', 'email' => 'manager@techzone.ci'],
            ['name' => 'Serge Yao',        'role' => 'member',  'email' => 'vente1@techzone.ci'],
            ['name' => 'Patricia Gbagbo',  'role' => 'member',  'email' => 'vente2@techzone.ci'],
        ]);
        $this->seedCatalogTechZone($t2);
        $suppliers = $this->seedSuppliers($t2);
        $this->seedCustomersAndOrders($t2, 10, 18, true, $suppliers);

        // ── Tenant 3 : Grossiste Douala (Enterprise / active) ─────────────────
        $t3 = $this->createTenant(
            name:     'Grossiste Douala',
            country:  'CM',
            currency: 'XAF',
            domain:   'grossiste.cm',
        );
        $this->createEnterpriseSubscription($t3);
        $this->seedTeam($t3, 'grossiste.cm', [
            ['name' => 'Emmanuel Mbarga',  'role' => 'admin',   'email' => 'admin@grossiste.cm'],
            ['name' => 'Cécile Mvondo',    'role' => 'manager', 'email' => 'manager@grossiste.cm'],
            ['name' => 'Paul Nkeng',       'role' => 'member',  'email' => 'stock1@grossiste.cm'],
            ['name' => 'Jeanne Abanda',    'role' => 'member',  'email' => 'stock2@grossiste.cm'],
            ['name' => 'Roger Tchinda',    'role' => 'member',  'email' => 'logistique@grossiste.cm'],
            ['name' => 'Alice Fopa',       'role' => 'viewer',  'email' => 'audit@grossiste.cm'],
        ]);
        $this->seedCatalogGrossiste($t3);
        $suppliersDouala = $this->seedSuppliers($t3, 'cm');
        $this->seedCustomersAndOrders($t3, 12, 25, true, $suppliersDouala);

        // ── Couverture démo complète : tous les modules MVP testables ──────────
        $this->seedOperationalDepth($t1, hasPos: true);
        $this->seedOperationalDepth($t2, hasPos: true);
        $this->seedOperationalDepth($t3, hasPos: true);
        $this->seedSecondaryModules($t1, multiWarehouse: false);
        $this->seedSecondaryModules($t2, multiWarehouse: true);
        $this->seedSecondaryModules($t3, multiWarehouse: true);
        $this->seedPromotions();

        $this->command->info('Demo data seeded. 3 tenants, 14 users, catalogue + variantes + stock + caisse + retours + promos.');
        $this->command->newLine();
        $this->command->line('  Logins (password: <comment>Secret123!</comment>):');
        $this->command->line('  <info>Starter (trialing)</info>  admin@afrikstyle.sn  manager@afrikstyle.sn  membre@afrikstyle.sn  lecteur@afrikstyle.sn');
        $this->command->line('  <info>Pro     (active)  </info>  admin@techzone.ci    manager@techzone.ci    vente1@techzone.ci    vente2@techzone.ci');
        $this->command->line('  <info>Enterprise (active)</info>  admin@grossiste.cm   manager@grossiste.cm   stock1@grossiste.cm   audit@grossiste.cm');
    }

    // ── Tenant & subscription helpers ─────────────────────────────────────────

    private function createTenant(string $name, string $country, string $currency, string $domain): Tenant
    {
        $slug = \Str::slug($name);

        return Tenant::updateOrCreate(
            ['slug' => $slug],
            [
                'name'   => $name,
                'domain' => $domain,
                'plan'   => 'starter',
                'status' => 'active',
                'settings' => [
                    'currency'     => $currency,
                    'timezone'     => match($country) {
                        'CI', 'SN', 'ML', 'BF', 'GN' => 'Africa/Abidjan',
                        'CM', 'GA', 'CG'              => 'Africa/Douala',
                        default                        => 'Africa/Abidjan',
                    },
                    'country'      => $country,
                    'locale'       => 'fr',
                    'order_prefix' => strtoupper(substr($slug, 0, 3)),
                ],
            ]
        );
    }

    private function createStarter(Tenant $tenant): void
    {
        if (Subscription::where('tenant_id', $tenant->id)->exists()) return;

        $plan = Plan::where('code', Plan::CODE_STARTER)->firstOrFail();

        Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => $plan->id,
            'status'               => Subscription::STATUS_TRIALING,
            'trial_ends_at'        => now()->addDays(10), // 10 days left
            'current_period_start' => now()->subDays(4),
            'current_period_end'   => now()->addDays(10),
        ]);

        $tenant->update(['plan' => $plan->code, 'subscription_status' => Subscription::STATUS_TRIALING]);
        $this->moduleRegistry->activatePlanModules($tenant, $plan);
    }

    private function createProSubscription(Tenant $tenant): void
    {
        if (Subscription::where('tenant_id', $tenant->id)->exists()) return;

        $plan = Plan::where('code', Plan::CODE_PRO)->firstOrFail();

        Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => $plan->id,
            'status'               => Subscription::STATUS_ACTIVE,
            'trial_ends_at'        => null,
            'current_period_start' => now()->subDays(15),
            'current_period_end'   => now()->addDays(15),
        ]);

        $tenant->update(['plan' => $plan->code, 'subscription_status' => Subscription::STATUS_ACTIVE]);
        $this->moduleRegistry->activatePlanModules($tenant, $plan);
    }

    private function createEnterpriseSubscription(Tenant $tenant): void
    {
        if (Subscription::where('tenant_id', $tenant->id)->exists()) return;

        $plan = Plan::where('code', Plan::CODE_ENTERPRISE)->firstOrFail();

        Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => $plan->id,
            'status'               => Subscription::STATUS_ACTIVE,
            'trial_ends_at'        => null,
            'current_period_start' => now()->subMonths(2),
            'current_period_end'   => now()->addMonths(10),
        ]);

        $tenant->update(['plan' => $plan->code, 'subscription_status' => Subscription::STATUS_ACTIVE]);
        $this->moduleRegistry->activatePlanModules($tenant, $plan);
    }

    // ── Team ──────────────────────────────────────────────────────────────────

    private function seedTeam(Tenant $tenant, string $domain, array $members): void
    {
        // Must set team ID BEFORE assigning roles so Spatie stores tenant_id in model_has_roles
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach ($members as $m) {
            $user = User::updateOrCreate(
                ['email' => $m['email']],
                [
                    'name'      => $m['name'],
                    'password'  => Hash::make('Secret123!'),
                    'tenant_id' => $tenant->id,
                ]
            );
            $user->syncRoles([$m['role']]);
        }

        // Reset to avoid leaking team context to subsequent seeders
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    // ── Catalog: Boutique Afrik Style (vêtements) ─────────────────────────────

    private function seedCatalogAfrikStyle(Tenant $tenant): void
    {
        $tid = $tenant->id;

        $vetements = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'vetements'],
            ['name' => 'Vêtements', 'description' => 'Tenues et pagnes']
        );
        $accessoires = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'accessoires'],
            ['name' => 'Accessoires', 'description' => 'Sacs, bijoux et plus']
        );

        $items = [
            ['Boubou homme bleu',    8500000,  'VET', $vetements->id],
            ['Boubou femme blanc',   7500000,  'VET', $vetements->id],
            ['Pagne wax 6 yards',    3500000,  'PAG', $vetements->id],
            ['Djellaba enfant',      4200000,  'VET', $vetements->id],
            ['Chemise bazin brodée', 6500000,  'CHM', $vetements->id],
            ['Robe bogolan',         9800000,  'ROB', $vetements->id],
            ['Tenue tabaski homme',  12000000, 'TBK', $vetements->id],
            ['Ensemble wax femme',   11500000, 'ENS', $vetements->id],
            ['Sac cuir marron',      5500000,  'SAC', $accessoires->id],
            ['Sac raphia tressé',    3200000,  'SAC', $accessoires->id],
            ['Collier perles',       1800000,  'BIJ', $accessoires->id],
            ['Bracelet argent',      2500000,  'BIJ', $accessoires->id],
            ['Chaussures babouches', 4500000,  'CHX', $accessoires->id],
            ['Foulard soie',         2800000,  'FOU', $accessoires->id],
            ['Chapeau paille',       1500000,  'ACC', $accessoires->id],
        ];

        $seq = 1;
        foreach ($items as [$name, $price, $prefix, $catId]) {
            $sku = sprintf('%s-%04d', $prefix, $seq++);
            $product = Product::updateOrCreate(
                ['tenant_id' => $tid, 'sku' => $sku],
                [
                    'tenant_id'      => $tid,
                    'category_id'    => $catId,
                    'name'           => $name,
                    'price_amount'   => $price,
                    'price_currency' => 'XOF',
                    'cost_amount'    => (int) ($price * 0.6),
                    'status'         => 'active',
                    'has_variants'   => false,
                ]
            );

            Stock::updateOrCreate(
                ['tenant_id' => $tid, 'product_id' => $product->id, 'variant_id' => null],
                ['quantity' => rand(5, 50), 'reserved_quantity' => 0, 'low_stock_threshold' => 3]
            );
        }
    }

    // ── Catalog: TechZone CI (électronique) ───────────────────────────────────

    private function seedCatalogTechZone(Tenant $tenant): void
    {
        $tid = $tenant->id;

        $smartphones = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'smartphones'],
            ['name' => 'Smartphones', 'description' => 'Téléphones mobiles']
        );
        $accessoiresTech = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'accessoires-tech'],
            ['name' => 'Accessoires Tech', 'description' => 'Chargeurs, câbles, coques']
        );
        $ordinateurs = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'ordinateurs'],
            ['name' => 'Ordinateurs', 'description' => 'Laptops et tablettes']
        );
        $tvAudio = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'tv-audio'],
            ['name' => 'TV & Audio', 'description' => 'Télévisions et audio']
        );

        $items = [
            ['Samsung Galaxy A55',       25000000, 'SAM', $smartphones->id,    40],
            ['iPhone 14 128Go',          55000000, 'APL', $smartphones->id,    15],
            ['Tecno Camon 20',           18000000, 'TEC', $smartphones->id,    60],
            ['Infinix Note 30',          16500000, 'INF', $smartphones->id,    45],
            ['Xiaomi Redmi Note 12',     19000000, 'XIA', $smartphones->id,    30],
            ['Samsung Galaxy A15',       12000000, 'SAM', $smartphones->id,    80],
            ['Itel P40',                  6500000, 'ITE', $smartphones->id,   120],
            ['Chargeur rapide 65W',       2500000, 'CHG', $accessoiresTech->id, 150],
            ['Câble USB-C 2m',             800000, 'CAB', $accessoiresTech->id, 200],
            ['Coque Samsung A55',         1200000, 'COQ', $accessoiresTech->id, 80],
            ['Écouteurs Bluetooth',       4500000, 'ECO', $accessoiresTech->id, 60],
            ['Power Bank 20000mAh',       8000000, 'PWB', $accessoiresTech->id, 40],
            ['HP Laptop 15s',            95000000, 'LAP', $ordinateurs->id,    10],
            ['Lenovo IdeaPad Slim 3',    85000000, 'LAP', $ordinateurs->id,    8],
            ['Tablette Samsung Tab A8',  35000000, 'TAB', $ordinateurs->id,    20],
            ['TV Samsung 43" UHD',       95000000, 'TVS', $tvAudio->id,        12],
            ['TV LG 55" OLED',          175000000, 'TVL', $tvAudio->id,         5],
            ['Enceinte Bluetooth JBL',   22000000, 'JBL', $tvAudio->id,        25],
            ['Hisense TV 32"',           35000000, 'TVH', $tvAudio->id,        18],
            ['Barre de son Sony',        45000000, 'SON', $tvAudio->id,        10],
        ];

        $seq = 1;
        foreach ($items as [$name, $price, $prefix, $catId, $qty]) {
            $sku = sprintf('%s-%04d', $prefix, $seq++);
            $product = Product::updateOrCreate(
                ['tenant_id' => $tid, 'sku' => $sku],
                [
                    'tenant_id'              => $tid,
                    'category_id'            => $catId,
                    'name'                   => $name,
                    'price_amount'           => $price,
                    'price_currency'         => 'XOF',
                    'cost_amount'            => (int) ($price * 0.7),
                    'compare_at_price_amount'=> (int) ($price * 1.1),
                    'status'                 => 'active',
                    'has_variants'           => false,
                ]
            );

            Stock::updateOrCreate(
                ['tenant_id' => $tid, 'product_id' => $product->id, 'variant_id' => null],
                ['quantity' => $qty, 'reserved_quantity' => rand(0, 3), 'low_stock_threshold' => 5]
            );
        }
    }

    // ── Catalog: Grossiste Douala (alimentation & ménager) ───────────────────

    private function seedCatalogGrossiste(Tenant $tenant): void
    {
        $tid = $tenant->id;
        $currency = 'XAF';

        $alimentaire = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'alimentaire'],
            ['name' => 'Alimentaire', 'description' => 'Produits alimentaires en gros']
        );
        $menager = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'menager'],
            ['name' => 'Ménager', 'description' => 'Articles ménagers']
        );
        $hygiene = Category::updateOrCreate(
            ['tenant_id' => $tid, 'slug' => 'hygiene'],
            ['name' => 'Hygiène', 'description' => 'Produits d\'hygiène']
        );

        // Convention uniforme : price_amount est TOUJOURS en centimes (× 100), comme
        // les autres catalogues — pas de multiplication dans la boucle.
        $items = [
            ['Riz parfumé 25kg',     1800000, 'RIZ', $alimentaire->id, 200],
            ['Sucre sac 50kg',       3200000, 'SUC', $alimentaire->id, 150],
            ['Huile palm 20L',       2400000, 'HUI', $alimentaire->id, 100],
            ['Farine blé 25kg',      1600000, 'FAR', $alimentaire->id, 80],
            ['Sel iodé 25kg',         450000, 'SEL', $alimentaire->id, 300],
            ['Tomate concentrée x24',1200000, 'TOM', $alimentaire->id, 120],
            ['Sardines x48',         1800000, 'SAR', $alimentaire->id, 90],
            ['Lait en poudre 2.5kg',  980000, 'LAI', $alimentaire->id, 60],
            ['Savon de Marseille x6', 360000, 'SAV', $hygiene->id,    500],
            ['Lessive poudre 5kg',    750000, 'LES', $hygiene->id,    200],
            ['Shampoing x12',         840000, 'SHA', $hygiene->id,    150],
            ['Eau de javel 5L x4',    680000, 'EJA', $hygiene->id,    300],
            ['Casserole inox 5L',    1250000, 'CAS', $menager->id,    80],
            ['Seau plastique 15L',    280000, 'SEA', $menager->id,    400],
            ['Bassine 30L',           420000, 'BAS', $menager->id,    250],
            ['Marmite aluminium 8L',  890000, 'MAR', $menager->id,    120],
        ];

        $seq = 1;
        foreach ($items as [$name, $price, $prefix, $catId, $qty]) {
            $sku = sprintf('%s-%04d', $prefix, $seq++);
            $product = Product::updateOrCreate(
                ['tenant_id' => $tid, 'sku' => $sku],
                [
                    'tenant_id'      => $tid,
                    'category_id'    => $catId,
                    'name'           => $name,
                    'price_amount'   => $price, // centimes
                    'price_currency' => $currency,
                    'cost_amount'    => (int) ($price * 0.75),
                    'status'         => 'active',
                    'has_variants'   => false,
                ]
            );

            Stock::updateOrCreate(
                ['tenant_id' => $tid, 'product_id' => $product->id, 'variant_id' => null],
                ['quantity' => $qty, 'reserved_quantity' => 0, 'low_stock_threshold' => 20]
            );
        }
    }

    // ── Suppliers ─────────────────────────────────────────────────────────────

    private function seedSuppliers(Tenant $tenant, string $country = 'ci'): array
    {
        $tid = $tenant->id;

        $data = $country === 'cm' ? [
            ['code' => 'FOURN-CM-01', 'name' => 'Import Douala SARL',   'email' => 'contact@import-douala.cm', 'phone' => '+237 6 77 00 00 01', 'contact_name' => 'Jean-Pierre Ngassa'],
            ['code' => 'FOURN-CM-02', 'name' => 'Grossiste Yde Nkolo',  'email' => 'info@grossiste-yde.cm',   'phone' => '+237 6 88 00 00 02', 'contact_name' => 'Marie Nkolo'],
            ['code' => 'FOURN-CM-03', 'name' => 'Afrique Import-Export','email' => 'afrique@importexport.com','phone' => '+237 6 55 00 00 03', 'contact_name' => 'Paul Bisseck'],
        ] : [
            ['code' => 'FOURN-CI-01', 'name' => 'Tech Distributors CI', 'email' => 'tech@distrib.ci',         'phone' => '+225 07 00 00 01', 'contact_name' => 'Brou Atsé'],
            ['code' => 'FOURN-CI-02', 'name' => 'Samsung West Africa',  'email' => 'wholesale@samsung-wa.com','phone' => '+225 07 00 00 02', 'contact_name' => 'Amara Kouyaté'],
            ['code' => 'FOURN-CI-03', 'name' => 'Mobile Import Dakar',  'email' => 'mobile@import-dk.sn',    'phone' => '+221 77 00 00 03', 'contact_name' => 'Aliou Sall'],
        ];

        $suppliers = [];
        foreach ($data as $s) {
            $suppliers[] = Supplier::updateOrCreate(
                ['tenant_id' => $tid, 'code' => $s['code']],
                array_merge($s, [
                    'tenant_id'    => $tid,
                    'payment_terms'=> 'net30',
                    'status'       => 'active',
                    'address'      => null,
                ])
            );
        }

        return $suppliers;
    }

    // ── Customers & Orders ────────────────────────────────────────────────────

    private function seedCustomersAndOrders(
        Tenant $tenant,
        int    $customerCount,
        int    $orderCount,
        bool   $withPaymentsAndDeliveries = false,
        array  $suppliers = [],
    ): void {
        $tid      = $tenant->id;
        $currency = $tenant->settings['currency'] ?? 'XOF';
        $products = Product::where('tenant_id', $tid)->get();

        if ($products->isEmpty()) return;

        // -- Customers
        $customerNames = [
            ['Aminata Diallo',  '+221 77 100 10 01', 'aminata@example.sn'],
            ['Modou Fall',      '+221 78 100 10 02', 'modou@example.sn'],
            ['Koffi Mensah',    '+225 07 100 10 03', 'koffi@example.ci'],
            ['Awa Touré',       '+221 76 100 10 04', 'awa@example.sn'],
            ['Sékou Camara',    '+224 62 100 10 05', 'sekou@example.gn'],
            ['Fatima Ben Ali',  '+212 06 100 10 06', 'fatima@example.ma'],
            ['Oumar Diop',      '+221 77 100 10 07', 'oumar@example.sn'],
            ['Nadia Coulibaly', '+225 07 100 10 08', 'nadia@example.ci'],
            ['Alioune Badara',  '+221 77 100 10 09', 'alioune@example.sn'],
            ['Mariam Sanogo',   '+223 66 100 10 10', 'mariam@example.ml'],
            ['Patrick Biya',    '+237 6 100 10 11',  'patrick@example.cm'],
            ['Cécile Toko',     '+237 6 100 10 12',  'cecile@example.cm'],
        ];

        $customers = [];
        for ($i = 0; $i < min($customerCount, count($customerNames)); $i++) {
            [$name, $phone, $email] = $customerNames[$i];
            $localEmail = str_replace('@example.', "@{$name}-{$tid}.", $email);
            $customers[] = Customer::updateOrCreate(
                ['tenant_id' => $tid, 'email' => $localEmail],
                [
                    'tenant_id' => $tid,
                    'name'      => $name,
                    'phone'     => $phone,
                    'email'     => $localEmail,
                    'address'   => null,
                ]
            );
        }

        // -- Orders with various statuses
        $statuses = [
            Order::STATUS_DRAFT,
            Order::STATUS_DRAFT,
            Order::STATUS_CONFIRMED,
            Order::STATUS_CONFIRMED,
            Order::STATUS_CONFIRMED,
            Order::STATUS_FULFILLED,
            Order::STATUS_FULFILLED,
            Order::STATUS_FULFILLED,
            Order::STATUS_FULFILLED,
            Order::STATUS_CANCELLED,
        ];

        $orderSeq = Order::where('tenant_id', $tid)->count() + 1;

        for ($i = 0; $i < $orderCount; $i++) {
            $customer = $customers[$i % count($customers)];
            $status   = $statuses[$i % count($statuses)];
            $nbLines  = rand(1, 4);
            $total    = 0;

            $order = Order::updateOrCreate(
                ['tenant_id' => $tid, 'number' => sprintf('CMD-%04d', $orderSeq++)],
                [
                    'tenant_id'    => $tid,
                    'customer_id'  => $customer->id,
                    'status'       => $status,
                    'currency'     => $currency,
                    'total_amount' => 0, // updated below
                    'fulfilled_at' => $status === Order::STATUS_FULFILLED ? now()->subDays(rand(1, 15)) : null,
                    'cancelled_at' => $status === Order::STATUS_CANCELLED ? now()->subDays(rand(1, 10)) : null,
                ]
            );

            // Order lines
            $pickedProducts = $products->random(min($nbLines, $products->count()));
            foreach ($pickedProducts as $product) {
                $qty            = rand(1, 5);
                $unitPriceCents = $product->price_amount; // already in cents
                $subtotal       = $qty * $unitPriceCents;
                $total         += $subtotal;

                OrderLine::updateOrCreate(
                    ['order_id' => $order->id, 'product_id' => $product->id],
                    [
                        'order_id'         => $order->id,
                        'tenant_id'        => $tid,
                        'product_id'       => $product->id,
                        'variant_id'       => null,
                        'sku'              => $product->sku,
                        'name'             => $product->name,
                        'quantity'         => $qty,
                        'unit_price_cents' => $unitPriceCents,
                    ]
                );
            }

            $order->update(['total_amount' => $total]);

            // Payments & deliveries for fulfilled orders (Pro / Enterprise)
            if ($withPaymentsAndDeliveries && $status === Order::STATUS_FULFILLED) {
                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'tenant_id'   => $tid,
                        'order_id'    => $order->id,
                        'amount_cents'=> $total,
                        'currency'    => $currency,
                        'method'      => collect(['mobile_money', 'cash', 'transfer'])->random(),
                        'reference'   => 'TXN-' . strtoupper(\Str::random(8)),
                        'paid_at'     => now()->subDays(rand(1, 20)),
                    ]
                );

                Delivery::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'tenant_id'       => $tid,
                        'order_id'        => $order->id,
                        'status'          => 'delivered',
                        'carrier'         => collect(['DHL', 'Chronopost CI', 'Dakar Express', 'Yango Delivery'])->random(),
                        'tracking_number' => 'TRK-' . strtoupper(\Str::random(10)),
                        'delivered_at'    => now()->subDays(rand(1, 15)),
                    ]
                );
            }
        }
    }

    // ── Couverture démo approfondie ───────────────────────────────────────────
    //   Entrepôts · variantes + attributs · mouvements de stock · caisse POS ·
    //   retours/SAV · marketplace · paiement manuel. Idempotent.

    private function seedOperationalDepth(Tenant $tenant, bool $hasPos): void
    {
        $tid      = $tenant->id;
        $currency = $tenant->settings['currency'] ?? 'XOF';

        // 1. Entrepôt principal + rattachement du stock existant
        $warehouse = Warehouse::updateOrCreate(
            ['tenant_id' => $tid, 'code' => 'WH-PRINCIPAL'],
            [
                'tenant_id' => $tid, 'name' => 'Entrepôt principal', 'type' => 'warehouse',
                'currency' => $currency, 'is_active' => true, 'is_default' => true,
                'sells_online' => true, 'sort_order' => 1,
            ]
        );
        Stock::where('tenant_id', $tid)->whereNull('warehouse_id')->update(['warehouse_id' => $warehouse->id]);

        // 2. Produit à déclinaisons (variantes N-axes + attributs)
        $variable = Product::updateOrCreate(
            ['tenant_id' => $tid, 'sku' => 'DEMO-VAR-001'],
            [
                'tenant_id' => $tid, 'name' => 'T-shirt personnalisable (démo)',
                'price_amount' => 1500000, 'price_currency' => $currency,
                'cost_amount' => 600000, 'status' => 'active', 'has_variants' => true,
            ]
        );
        foreach ([['Rouge', 'S'], ['Rouge', 'M'], ['Bleu', 'L']] as $i => [$couleur, $taille]) {
            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $variable->id, 'sku' => "DEMO-VAR-001-{$couleur}-{$taille}"],
                [
                    'tenant_id' => $tid, 'name' => "{$couleur} / {$taille}", 'label' => "{$couleur} / {$taille}",
                    'attributes' => ['Couleur' => $couleur, 'Taille' => $taille],
                    'price_amount' => 1500000, 'price_currency' => $currency,
                    'sort_order' => $i, 'is_active' => true,
                ]
            );
            Stock::updateOrCreate(
                ['tenant_id' => $tid, 'product_id' => $variable->id, 'variant_id' => $variant->id],
                ['warehouse_id' => $warehouse->id, 'quantity' => 20, 'reserved_quantity' => 0, 'low_stock_threshold' => 5]
            );
        }

        // 3. Mouvements de stock — historique d'entrée initial (traçabilité)
        foreach (Stock::where('tenant_id', $tid)->get() as $stock) {
            StockMovement::updateOrCreate(
                ['tenant_id' => $tid, 'stock_id' => $stock->id, 'reference' => 'SEED-INIT'],
                [
                    'product_id' => $stock->product_id, 'variant_id' => $stock->variant_id,
                    'type' => StockMovement::TYPE_IN, 'quantity' => $stock->quantity,
                    'quantity_before' => 0, 'quantity_after' => $stock->quantity,
                    'reason' => StockMovement::REASON_DELIVERY, 'note' => 'Stock initial (démo)',
                ]
            );
        }

        $admin = User::where('tenant_id', $tid)->first();

        // 4. Caisse POS — une session clôturée (rapprochée) + une ouverte
        if ($hasPos && $admin) {
            CashRegisterSession::updateOrCreate(
                ['tenant_id' => $tid, 'label' => 'Caisse 1 — clôturée (démo)'],
                [
                    'warehouse_id' => $warehouse->id, 'status' => CashRegisterSession::STATUS_CLOSED,
                    'opening_float_cents' => 5000000, 'total_sales_cents' => 12500000, 'cash_sales_cents' => 9000000,
                    'sales_count' => 7, 'expected_cash_cents' => 14000000, 'counted_cash_cents' => 13950000,
                    'difference_cents' => -50000, 'opened_by' => $admin->id, 'closed_by' => $admin->id,
                    'opened_at' => now()->subDay()->setTime(8, 0), 'closed_at' => now()->subDay()->setTime(18, 0),
                    'notes' => 'Session de démonstration clôturée',
                ]
            );
            CashRegisterSession::updateOrCreate(
                ['tenant_id' => $tid, 'label' => 'Caisse 1 — ouverte (démo)'],
                [
                    'warehouse_id' => $warehouse->id, 'status' => CashRegisterSession::STATUS_OPEN,
                    'opening_float_cents' => 5000000, 'total_sales_cents' => 0, 'cash_sales_cents' => 0,
                    'sales_count' => 0, 'opened_by' => $admin->id, 'opened_at' => now()->setTime(8, 0),
                ]
            );
        }

        // 5. Retour / SAV sur une commande livrée
        $fulfilled = Order::where('tenant_id', $tid)->where('status', Order::STATUS_FULFILLED)->first();
        if ($fulfilled && $admin) {
            // order_returns.number is globally unique → derive it from the tenant's
            // RANDOM uuid tail (v7 uuids share a time PREFIX, which would collide).
            $retNumber = 'RET-' . strtoupper(substr(str_replace('-', '', $tid), -8));
            OrderReturn::updateOrCreate(
                ['number' => $retNumber],
                [
                    'tenant_id' => $tid, 'order_id' => $fulfilled->id, 'status' => OrderReturn::STATUS_PENDING,
                    'reason' => OrderReturn::REASON_DEFECTIVE, 'customer_note' => 'Article défectueux (démo)',
                    'refund_amount_cents' => 0, 'refund_currency' => $currency, 'requested_by' => $admin->id,
                ]
            );
        }

        // 6. Annonce marketplace
        $simpleProduct = Product::where('tenant_id', $tid)->where('has_variants', false)->first();
        if ($simpleProduct) {
            MarketplaceListing::updateOrCreate(
                ['tenant_id' => $tid, 'product_id' => $simpleProduct->id, 'platform' => 'facebook'],
                [
                    'warehouse_id' => $warehouse->id,
                    'external_product_id' => 'FB-' . $simpleProduct->sku,
                    'external_sku' => $simpleProduct->sku,
                    'sync_status' => 'synced', 'last_synced_at' => now()->subHours(2),
                ]
            );
        }

        // 7. Paiement manuel en attente — démo back-office billing
        if (! ManualPayment::where('tenant_id', $tid)->exists()) {
            $plan = Plan::where('code', $tenant->plan)->first();
            if ($plan) {
                ManualPayment::create([
                    'tenant_id' => $tid, 'plan_id' => $plan->id,
                    'amount_cents' => $plan->price_monthly_cents ?: 990000, 'currency' => $currency,
                    'payment_method' => 'bank_transfer',
                    'notes' => 'Virement bancaire (démo) en attente de validation',
                    'status' => ManualPayment::STATUS_PENDING,
                ]);
            }
        }
    }

    // ── Modules MVP secondaires : période fiscale, import, ajustement de stock,
    //    transfert inter-entrepôts (multi-sites). Idempotent. ──────────────────

    private function seedSecondaryModules(Tenant $tenant, bool $multiWarehouse): void
    {
        $tid      = $tenant->id;
        $currency = $tenant->settings['currency'] ?? 'XOF';
        $admin    = User::where('tenant_id', $tid)->first();
        $warehouse = Warehouse::where('tenant_id', $tid)->where('is_default', true)->first();
        if (! $warehouse) {
            return;
        }

        // 1. Période fiscale ouverte (mois courant)
        FiscalPeriod::updateOrCreate(
            [
                'tenant_id' => $tid, 'type' => 'monthly',
                'starts_at' => now()->startOfMonth(), 'ends_at' => now()->endOfMonth(),
            ],
            ['name' => 'Période ' . now()->format('Y-m'), 'status' => 'open']
        );

        // 2. Session d'import terminée (historique import/export)
        ImportSession::updateOrCreate(
            ['tenant_id' => $tid, 'original_filename' => 'produits-demo.xlsx'],
            [
                'performed_by' => $admin?->id, 'type' => ImportSession::TYPE_PRODUCTS,
                'status' => ImportSession::STATUS_COMPLETED, 'mode' => ImportSession::MODE_CREATE_UPDATE,
                'stored_path' => 'imports/demo/produits-demo.xlsx',
                'total_rows' => 50, 'valid_rows' => 48, 'error_rows' => 2, 'imported_rows' => 48,
                'analyzed_at' => now()->subDays(3), 'completed_at' => now()->subDays(3),
            ]
        );

        // 3. Demande d'ajustement de stock en attente (flux demande → approbation)
        $stock = Stock::where('tenant_id', $tid)->first();
        if ($stock && $admin) {
            StockAdjustmentRequest::updateOrCreate(
                ['tenant_id' => $tid, 'stock_id' => $stock->id, 'status' => StockAdjustmentRequest::STATUS_PENDING],
                [
                    'product_id' => $stock->product_id, 'variant_id' => $stock->variant_id,
                    'quantity_before' => $stock->quantity, 'quantity_requested' => $stock->quantity + 5,
                    'delta' => 5, 'value_cents' => 0, 'reason' => 'count',
                    'note' => 'Écart constaté à l’inventaire (démo)', 'requested_by' => $admin->id,
                ]
            );
        }

        // 4. Transfert inter-entrepôts (démo multi-sites) — second entrepôt + transfert reçu
        if ($multiWarehouse) {
            $dest = Warehouse::updateOrCreate(
                ['tenant_id' => $tid, 'code' => 'WH-SECONDAIRE'],
                [
                    'tenant_id' => $tid, 'name' => 'Dépôt secondaire', 'type' => 'warehouse',
                    'currency' => $currency, 'is_active' => true, 'is_default' => false, 'sort_order' => 2,
                ]
            );
            // number is globally unique → derive from the tenant's random uuid tail.
            $transferNumber = 'TRF-' . strtoupper(substr(str_replace('-', '', $tid), -8));
            $transfer = StockTransfer::updateOrCreate(
                ['number' => $transferNumber],
                [
                    'tenant_id' => $tid, 'source_warehouse_id' => $warehouse->id,
                    'destination_warehouse_id' => $dest->id, 'status' => 'completed',
                    'notes' => 'Réassort dépôt secondaire (démo)', 'requested_by' => $admin?->id,
                    'shipped_by' => $admin?->id, 'received_by' => $admin?->id,
                    'shipped_at' => now()->subDays(2), 'received_at' => now()->subDay(), 'completed_at' => now()->subDay(),
                ]
            );
            $prod = Product::where('tenant_id', $tid)->where('has_variants', false)->first();
            if ($prod) {
                StockTransferLine::updateOrCreate(
                    ['transfer_id' => $transfer->id, 'product_id' => $prod->id],
                    [
                        'quantity_requested' => 10, 'quantity_shipped' => 10,
                        'quantity_received' => 10, 'line_status' => 'received',
                    ]
                );
            }
        }
    }

    // ── Promotions globales (plateforme) ──────────────────────────────────────

    private function seedPromotions(): void
    {
        Promotion::updateOrCreate(
            ['code' => 'BIENVENUE20'],
            [
                'description' => '20% la première année', 'discount_type' => 'percent',
                'discount_value' => 20, 'max_uses' => 100, 'current_uses' => 0, 'is_active' => true,
                'valid_from' => now()->subMonth(), 'valid_until' => now()->addMonths(6),
            ]
        );
        Promotion::updateOrCreate(
            ['code' => 'LANCEMENT5000'],
            [
                'description' => '5 000 offerts', 'discount_type' => 'fixed_cents',
                'discount_value' => 500000, 'max_uses' => 50, 'current_uses' => 0, 'is_active' => true,
                'valid_from' => now()->subWeek(), 'valid_until' => now()->addMonths(3),
            ]
        );
    }
}
