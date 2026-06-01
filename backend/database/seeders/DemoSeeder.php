<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Payments\Models\Payment;
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

        $this->command->info('Demo data seeded. 3 tenants, 14 users, full catalog + orders.');
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

        $items = [
            ['Riz parfumé 25kg',     18000, 'RIZ', $alimentaire->id, 200],
            ['Sucre sac 50kg',       32000, 'SUC', $alimentaire->id, 150],
            ['Huile palm 20L',       24000, 'HUI', $alimentaire->id, 100],
            ['Farine blé 25kg',      16000, 'FAR', $alimentaire->id, 80],
            ['Sel iodé 25kg',         4500, 'SEL', $alimentaire->id, 300],
            ['Tomate concentrée x24',12000, 'TOM', $alimentaire->id, 120],
            ['Sardines x48',         18000, 'SAR', $alimentaire->id, 90],
            ['Lait en poudre 2.5kg',  9800, 'LAI', $alimentaire->id, 60],
            ['Savon de Marseille x6', 3600, 'SAV', $hygiene->id,    500],
            ['Lessive poudre 5kg',    7500, 'LES', $hygiene->id,    200],
            ['Shampoing x12',         8400, 'SHA', $hygiene->id,    150],
            ['Eau de javel 5L x4',    6800, 'EJA', $hygiene->id,    300],
            ['Casserole inox 5L',    12500, 'CAS', $menager->id,    80],
            ['Seau plastique 15L',    2800, 'SEA', $menager->id,    400],
            ['Bassine 30L',           4200, 'BAS', $menager->id,    250],
            ['Marmite aluminium 8L',  8900, 'MAR', $menager->id,    120],
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
                    'price_amount'   => $price * 100, // en centimes
                    'price_currency' => $currency,
                    'cost_amount'    => (int) ($price * 100 * 0.75),
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
}
