<?php

namespace App\Modules\Demo\Services;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Demo\Models\DemoRequest;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Tenants\Services\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisionne (et détruit) un tenant de DÉMONSTRATION éphémère et isolé pour un
 * prospect. Chaque demande approuvée obtient SON tenant jetable (is_demo=true) :
 * données fictives, expiration, aucune interaction possible avec les vrais tenants
 * (garanti par TenantScope). À l'expiration, revoke() démonte l'accès.
 */
class DemoProvisioningService
{
    public function __construct(
        private readonly TenantProvisioningService $tenants,
        private readonly ModuleRegistryService $modules,
    ) {}

    /**
     * Crée le tenant démo + l'utilisateur admin + un jeu de données crédible.
     *
     * @return array{tenant: Tenant, user: User, password: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function provisionFor(DemoRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $ttlDays   = (int) config('demo.access_ttl_days', 14);
            $expiresAt = now()->addDays($ttlDays);

            $companyName = $request->company ?: ($request->fullName().' — Démo');

            // 1) Tenant éphémère marqué démo
            $tenant = $this->tenants->provision([
                'name' => Str::limit('Démo · '.$companyName, 60, ''),
                'plan' => Plan::CODE_PRO,
            ]);
            $tenant->update([
                'is_demo'         => true,
                'demo_expires_at' => $expiresAt,
                'status'          => 'active',
            ]);

            // 2) Abonnement Pro actif + activation des modules du plan
            $plan = Plan::where('code', Plan::CODE_PRO)->first();
            if ($plan) {
                Subscription::create([
                    'tenant_id'            => $tenant->id,
                    'plan_id'              => $plan->id,
                    'status'               => Subscription::STATUS_ACTIVE,
                    'trial_ends_at'        => null,
                    'current_period_start' => now(),
                    'current_period_end'   => $expiresAt,
                ]);
                $tenant->update(['plan' => $plan->code, 'subscription_status' => Subscription::STATUS_ACTIVE]);
                $this->modules->activatePlanModules($tenant, $plan);
            }

            // 3) Utilisateur admin démo (mot de passe temporaire aléatoire)
            $password = Str::password(14);
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($tenant->id);

            $user = User::create([
                'name'              => $request->fullName(),
                'email'             => $this->demoEmail($request, $tenant),
                'password'          => Hash::make($password),
                'tenant_id'         => $tenant->id,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles(['admin']);

            $registrar->setPermissionsTeamId(null);

            // 4) Jeu de données de démonstration (fictif)
            $this->seedDemoData($tenant);

            // 5) Trace sur la demande
            $request->update([
                'demo_tenant_id'         => $tenant->id,
                'demo_user_id'           => $user->id,
                'demo_access_expires_at' => $expiresAt,
            ]);

            return ['tenant' => $tenant, 'user' => $user, 'password' => $password, 'expires_at' => $expiresAt];
        });
    }

    /**
     * Révoque et démonte l'accès démo à l'expiration : suppression des utilisateurs
     * (donc plus aucun login possible) + tenant marqué et soft-supprimé. Les données
     * fictives deviennent inaccessibles (aucun compte, tenant hors service).
     */
    public function revoke(DemoRequest $request): void
    {
        DB::transaction(function () use ($request) {
            if ($request->demo_tenant_id) {
                $users = User::withoutGlobalScopes()->where('tenant_id', $request->demo_tenant_id)->get();
                foreach ($users as $user) {
                    $user->tokens()->delete();
                    $user->forceDelete();
                }

                $tenant = Tenant::withTrashed()->find($request->demo_tenant_id);
                if ($tenant) {
                    $tenant->update(['status' => 'cancelled']);
                    $tenant->delete();
                }
            }

            $request->update(['status' => DemoRequest::STATUS_EXPIRED]);
        });
    }

    private function demoEmail(DemoRequest $request, Tenant $tenant): string
    {
        // Email de connexion isolé au tenant démo (unicité globale garantie par le
        // suffixe tenant), pour ne jamais entrer en collision avec un vrai compte.
        $local = Str::of($request->email)->before('@')->slug()->limit(24, '');

        return "{$local}+demo-{$tenant->id}@demo.frynov.app";
    }

    private function seedDemoData(Tenant $tenant): void
    {
        $tid = $tenant->id;

        $cat1 = Category::create(['tenant_id' => $tid, 'slug' => 'demo-boutique', 'name' => 'Boutique', 'description' => 'Articles de démonstration']);
        $cat2 = Category::create(['tenant_id' => $tid, 'slug' => 'demo-accessoires', 'name' => 'Accessoires', 'description' => 'Accessoires de démonstration']);

        $items = [
            ['Article démo A', 1500000, 'DEMO', $cat1->id],
            ['Article démo B', 2500000, 'DEMO', $cat1->id],
            ['Article démo C', 3500000, 'DEMO', $cat1->id],
            ['Article démo D',  900000, 'DEMO', $cat1->id],
            ['Accessoire démo E', 1200000, 'DACC', $cat2->id],
            ['Accessoire démo F', 1800000, 'DACC', $cat2->id],
            ['Accessoire démo G',  600000, 'DACC', $cat2->id],
            ['Accessoire démo H', 2200000, 'DACC', $cat2->id],
        ];

        $seq = 1;
        foreach ($items as [$name, $price, $prefix, $catId]) {
            $product = Product::create([
                'tenant_id'      => $tid,
                'category_id'    => $catId,
                'sku'            => sprintf('%s-%04d', $prefix, $seq++),
                'name'           => $name,
                'price_amount'   => $price,
                'price_currency' => 'XOF',
                'cost_amount'    => (int) ($price * 0.6),
                'status'         => 'active',
                'has_variants'   => false,
            ]);

            Stock::create([
                'tenant_id'           => $tid,
                'product_id'          => $product->id,
                'variant_id'          => null,
                'quantity'            => rand(8, 60),
                'reserved_quantity'   => 0,
                'low_stock_threshold' => 5,
            ]);
        }

        $customers = [
            ['Client Démo Un',    'client1'],
            ['Client Démo Deux',  'client2'],
            ['Client Démo Trois', 'client3'],
        ];
        foreach ($customers as [$name, $handle]) {
            Customer::create([
                'tenant_id' => $tid,
                'name'      => $name,
                'email'     => "{$handle}+{$tid}@demo.frynov.app",
                'phone'     => '+221 77 000 00 00',
            ]);
        }
    }
}
