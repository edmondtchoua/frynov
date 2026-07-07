<?php

namespace App\Modules\Demo\Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Demo\Mail\DemoAccessMail;
use App\Modules\Demo\Models\DemoRequest;
use App\Modules\Demo\Services\DemoAccessService;
use App\Modules\Demo\Services\DemoProvisioningService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\PlanModulesSeeder;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ErpModulesSeeder::class, PlansSeeder::class, PlanModulesSeeder::class]);
    }

    private function newRequest(): DemoRequest
    {
        return DemoRequest::create([
            'first_name'      => 'Awa', 'last_name' => 'Traoré',
            'email'           => 'awa@example.com', 'company' => 'Boutique Awa',
            'consent_contact' => true, 'locale' => 'fr',
            'status'          => DemoRequest::STATUS_PENDING_REVIEW,
        ]);
    }

    #[Test]
    public function granting_access_provisions_an_isolated_demo_tenant_and_emails_credentials(): void
    {
        Mail::fake();

        $req = $this->newRequest();
        app(DemoAccessService::class)->grant($req);
        $req->refresh();

        // Statut + traçabilité
        $this->assertSame(DemoRequest::STATUS_ACCESS_SENT, $req->status);
        $this->assertNotNull($req->demo_tenant_id);
        $this->assertNotNull($req->demo_user_id);
        $this->assertNotNull($req->access_sent_at);
        $this->assertTrue($req->demo_access_expires_at->isFuture());

        // Tenant marqué démo + éphémère
        $tenant = Tenant::find($req->demo_tenant_id);
        $this->assertTrue($tenant->isDemo());
        $this->assertNotNull($tenant->demo_expires_at);

        // Utilisateur admin rattaché au tenant démo
        $user = User::find($req->demo_user_id);
        $this->assertSame($tenant->id, $user->tenant_id);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->fresh()->hasRole('admin'));

        // Données de démo isolées à ce tenant
        $this->assertSame(8, Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        Mail::assertSent(DemoAccessMail::class, fn ($m) => $m->hasTo('awa@example.com'));
    }

    #[Test]
    public function revoking_access_removes_the_demo_user_and_expires_the_request(): void
    {
        Mail::fake();

        $req = $this->newRequest();
        $access = app(DemoAccessService::class);
        $access->grant($req);
        $req->refresh();

        $userId = $req->demo_user_id;
        $tenantId = $req->demo_tenant_id;

        app(DemoProvisioningService::class)->revoke($req);
        $req->refresh();

        $this->assertSame(DemoRequest::STATUS_EXPIRED, $req->status);
        $this->assertNull(User::withoutGlobalScopes()->find($userId));
        $this->assertNull(Tenant::find($tenantId)); // soft-deleted → hors scope par défaut
    }

    #[Test]
    public function provisioning_twice_for_the_same_company_does_not_collide_on_slug(): void
    {
        Mail::fake();
        $access = app(DemoAccessService::class);

        $r1 = $this->newRequest();          // company = Boutique Awa
        $access->grant($r1);
        $t1 = $r1->refresh()->demo_tenant_id;

        // Révoque le 1er (tenant soft-supprimé, slug conservé par la contrainte UNIQUE)
        app(DemoProvisioningService::class)->revoke($r1);

        // Un 2e prospect de la MÊME entreprise ne doit pas provoquer de collision de slug
        $r2 = DemoRequest::create([
            'email' => 'awa2@example.com', 'company' => 'Boutique Awa',
            'consent_contact' => true, 'locale' => 'fr', 'status' => DemoRequest::STATUS_PENDING_REVIEW,
        ]);
        $access->grant($r2);
        $t2 = $r2->refresh()->demo_tenant_id;

        $this->assertNotNull($t2);
        $this->assertNotSame($t1, $t2);
        $this->assertSame(DemoRequest::STATUS_ACCESS_SENT, $r2->status);
    }

    #[Test]
    public function auto_mode_provisions_immediately_on_form_submit(): void
    {
        Mail::fake();
        config(['demo.mode' => 'auto']);

        $this->postJson('/api/demo-requests', [
            'email' => 'lead@example.com', 'consent_contact' => true, 'locale' => 'fr',
        ])->assertCreated();

        $req = DemoRequest::where('email', 'lead@example.com')->first();
        $this->assertSame(DemoRequest::STATUS_ACCESS_SENT, $req->status);
        $this->assertNotNull($req->demo_tenant_id);
        Mail::assertSent(DemoAccessMail::class);
    }
}
