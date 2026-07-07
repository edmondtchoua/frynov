<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P5 outillage — l'endpoint usage/quota expose la consommation vs la limite du plan par ressource.
 */
class UsageReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_usage_versus_plan_limits(): void
    {
        $this->seed(PlansSeeder::class);
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => Plan::CODE_STARTER, 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status' => 'trialing', 'interval' => 'monthly', 'currency' => 'XOF', 'market_code' => 'waemu',
            'current_period_start' => now(), 'current_period_end' => now()->addDays(14),
        ]);
        Sanctum::actingAs(User::factory()->create(['tenant_id' => $tenant->id]));

        $res = $this->getJson('/api/me/subscription/usage')->assertOk()->assertJsonPath('plan', 'starter');
        $rows = collect($res->json('data'));

        // Utilisateurs : plan Découverte plafonné à 1 ; l'utilisateur courant compte.
        $users = $rows->firstWhere('resource', 'users');
        $this->assertSame(1, $users['limit']);
        $this->assertSame(1, $users['usage']);
        $this->assertSame(0, $users['remaining']);
        $this->assertSame(100, $users['percent']);

        // Produits : limite 100, aucun produit → illimité NON (limite chiffrée).
        $products = $rows->firstWhere('resource', 'products');
        $this->assertSame(100, $products['limit']);
        $this->assertSame(0, $products['usage']);

        // La synthèse couvre bien les 6 ressources.
        $this->assertEqualsCanonicalizing(
            ['users', 'products', 'customers', 'warehouses', 'orders', 'imports'],
            $rows->pluck('resource')->all(),
        );
    }
}
