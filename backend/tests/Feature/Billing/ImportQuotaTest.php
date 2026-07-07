<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\PlanLimit;
use App\Modules\Billing\Services\QuotaService;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit RBAC P2 — le quota mensuel d'imports (`max_imports_per_month`) est désormais appliqué.
 */
class ImportQuotaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function monthly_import_quota_is_enforced(): void
    {
        $this->seed(PlansSeeder::class);
        PlanLimit::where('plan_id', Plan::where('code', 'starter')->value('id'))->update(['max_imports_per_month' => 1]);

        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => 'starter', 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
        $quotas = app(QuotaService::class);

        // 0 import ce mois → autorisé.
        $quotas->assertCanCreateImport($tenant);

        // 1 import déjà réalisé ce mois → le suivant est bloqué.
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        ImportSession::create([
            'tenant_id' => $tenant->id, 'performed_by' => $user->id, 'type' => 'products',
            'status' => 'completed', 'mode' => 'create_only',
            'original_filename' => 'import.xlsx', 'stored_path' => 'imports/x.xlsx',
        ]);

        $this->expectException(\DomainException::class);
        $quotas->assertCanCreateImport($tenant);
    }
}
