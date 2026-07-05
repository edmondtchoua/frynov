<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ManualPaymentService $svc;
    private Tenant $tenant;
    private User $admin;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // proofs are stored privately (security audit)
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->svc  = app(ManualPaymentService::class);
        $this->plan = Plan::firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'price_monthly_cents' => 15000, 'price_yearly_cents' => 150000,
            'currency' => 'XOF', 'trial_days' => 14,
            'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $this->tenant = Tenant::create([
            'name' => 'MPS', 'slug' => 'mps-test',
            'plan' => 'starter', 'status' => 'active', 'settings' => [],
        ]);
        $this->admin = User::create([
            'name' => 'SA', 'email' => 'sa@mps.sn',
            'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id,
            'is_super_admin' => true,
        ]);
    }

    private function submitPayment(): ManualPayment
    {
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        return $this->svc->submit($this->tenant, $this->plan, 15000, 'XOF', 'mobile_money', $file, null);
    }

    #[Test]
    public function can_submit_payment_with_proof(): void
    {
        $mp = $this->submitPayment();

        $this->assertSame(ManualPayment::STATUS_PENDING, $mp->status);
        $this->assertNotNull($mp->proof_path);
        Storage::disk('local')->assertExists($mp->proof_path);
        Storage::disk('public')->assertMissing($mp->proof_path);
    }

    #[Test]
    public function approve_activates_subscription(): void
    {
        $mp = $this->submitPayment();
        $this->svc->approve($mp, $this->admin);

        $this->assertSame(ManualPayment::STATUS_APPROVED, $mp->fresh()->status);
        $sub = Subscription::where('tenant_id', $this->tenant->id)->latest()->first();
        $this->assertNotNull($sub);
        $this->assertSame('active', $sub->status);
    }

    #[Test]
    public function reject_marks_payment_rejected(): void
    {
        $mp = $this->submitPayment();
        $this->svc->reject($mp, $this->admin, 'Preuve illisible');

        $this->assertSame(ManualPayment::STATUS_REJECTED, $mp->fresh()->status);
        $this->assertSame('Preuve illisible', $mp->fresh()->rejection_reason);
    }

    #[Test]
    public function second_approve_is_idempotent(): void
    {
        // approving twice should not crash — service is idempotent on already-approved
        $mp = $this->submitPayment();
        $this->svc->approve($mp, $this->admin);
        $this->svc->approve($mp->fresh(), $this->admin); // should not throw

        $this->assertSame(ManualPayment::STATUS_APPROVED, $mp->fresh()->status);
    }
}
