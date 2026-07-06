<?php

namespace App\Modules\Demo\Tests\Feature;

use App\Models\User;
use App\Modules\Demo\Mail\DemoAccessMail;
use App\Modules\Demo\Mail\DemoEndedMail;
use App\Modules\Demo\Mail\DemoReminderMail;
use App\Modules\Demo\Models\DemoRequest;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\PlanModulesSeeder;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDemoRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ErpModulesSeeder::class, PlansSeeder::class, PlanModulesSeeder::class]);
    }

    private function superAdmin(): User
    {
        $u = new User(['name' => 'Ops', 'email' => 'ops@frynov.com', 'password' => bcrypt('x')]);
        $u->is_super_admin = true;
        $u->save();

        return $u;
    }

    private function request(array $o = []): DemoRequest
    {
        return DemoRequest::create(array_merge([
            'email' => 'lead@example.com', 'consent_contact' => true, 'locale' => 'fr',
            'status' => DemoRequest::STATUS_PENDING_REVIEW,
        ], $o));
    }

    #[Test]
    public function non_admin_cannot_access_the_backoffice(): void
    {
        $this->getJson('/api/admin/demo-requests')->assertStatus(401);

        $member = User::create(['name' => 'M', 'email' => 'm@x.com', 'password' => bcrypt('x')]);
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/demo-requests')->assertStatus(403);
    }

    #[Test]
    public function admin_can_list_with_stats_and_filter(): void
    {
        $this->request(['email' => 'a@x.com']);
        $this->request(['email' => 'b@x.com', 'status' => DemoRequest::STATUS_REJECTED]);

        $res = $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/admin/demo-requests?status='.DemoRequest::STATUS_REJECTED)
            ->assertOk()
            ->assertJsonStructure(['data' => ['data'], 'stats' => ['total', 'new', 'sent', 'rejected']]);

        $this->assertSame(1, $res->json('data.total'));
        $this->assertSame(2, $res->json('stats.total'));
    }

    #[Test]
    public function admin_can_approve_which_provisions_and_sends_access(): void
    {
        Mail::fake();
        $req = $this->request();

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson("/api/admin/demo-requests/{$req->id}/approve")
            ->assertOk();

        $req->refresh();
        $this->assertSame(DemoRequest::STATUS_ACCESS_SENT, $req->status);
        $this->assertNotNull($req->demo_tenant_id);
        Mail::assertSent(DemoAccessMail::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'demo.access_granted']);
    }

    #[Test]
    public function admin_can_reject_convert_and_edit_notes(): void
    {
        $admin = $this->superAdmin();
        $req = $this->request();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/demo-requests/{$req->id}/reject", ['reason' => 'Concurrent'])
            ->assertOk();
        $this->assertSame(DemoRequest::STATUS_REJECTED, $req->refresh()->status);
        $this->assertSame('Concurrent', $req->rejection_reason);

        $req2 = $this->request(['email' => 'c@x.com']);
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/demo-requests/{$req2->id}/convert")->assertOk();
        $this->assertSame(DemoRequest::STATUS_CONVERTED, $req2->refresh()->status);
        $this->assertNotNull($req2->converted_at);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/demo-requests/{$req2->id}/notes", ['internal_notes' => 'Rappeler lundi'])
            ->assertOk();
        $this->assertSame('Rappeler lundi', $req2->refresh()->internal_notes);
    }

    #[Test]
    public function revoke_expired_command_tears_down_and_emails_end_of_demo(): void
    {
        Mail::fake();
        $req = $this->request();
        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson("/api/admin/demo-requests/{$req->id}/approve")->assertOk();

        // Force l'expiration
        $req->refresh()->update(['demo_access_expires_at' => now()->subDay()]);

        $this->artisan('demo:revoke-expired')->assertSuccessful();

        $this->assertSame(DemoRequest::STATUS_EXPIRED, $req->refresh()->status);
        $this->assertNull(User::withoutGlobalScopes()->find($req->demo_user_id));
        Mail::assertSent(DemoEndedMail::class);
    }

    #[Test]
    public function reminder_command_notifies_before_expiry_once(): void
    {
        Mail::fake();
        config(['demo.reminder_days_before' => 3]);

        $req = $this->request();
        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson("/api/admin/demo-requests/{$req->id}/approve")->assertOk();
        $req->refresh()->update(['demo_access_expires_at' => now()->addDay()]);

        $this->artisan('demo:send-reminders')->assertSuccessful();
        Mail::assertSent(DemoReminderMail::class);
        $this->assertNotNull($req->refresh()->reminder_sent_at);

        // Deuxième passage : pas de doublon
        Mail::fake();
        $this->artisan('demo:send-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
