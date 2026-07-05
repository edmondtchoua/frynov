<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Mail\UserInvitationMail;
use App\Modules\Auth\Services\InvitationService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-12 F-5 — acceptation d'invitation : le membre pose son mot de passe avec le code reçu par email.
 */
class InvitationAcceptTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $inviter;
    private User $invitee;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->tenant = Tenant::create(['name' => 'Inv', 'slug' => 'inv-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->inviter = User::create(['name' => 'Admin', 'email' => 'admin@inv.sn', 'password' => 'AdminPass1', 'tenant_id' => $this->tenant->id]);
        // Membre créé sans mot de passe utilisable (aléatoire), comme le fait l'invitation.
        $this->invitee = User::create(['name' => 'Awa', 'email' => 'awa@inv.sn', 'password' => \Illuminate\Support\Str::random(40), 'tenant_id' => $this->tenant->id]);
    }

    private function inviteAndCaptureCode(): string
    {
        app(InvitationService::class)->invite($this->invitee, $this->inviter);

        $code = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $m) use (&$code) {
            $code = $m->code;

            return $m->hasTo('awa@inv.sn');
        });

        return (string) $code;
    }

    #[Test]
    public function accepting_with_a_valid_code_sets_the_password_and_allows_login(): void
    {
        $code = $this->inviteAndCaptureCode();

        $this->postJson('/api/auth/accept-invitation', [
            'email' => 'awa@inv.sn', 'code' => $code,
            'password' => 'MyNewPass1', 'password_confirmation' => 'MyNewPass1',
        ])->assertOk();

        $this->invitee->refresh();
        $this->assertTrue(Hash::check('MyNewPass1', $this->invitee->password));
        $this->assertDatabaseHas('user_invitations', ['user_id' => $this->invitee->id]);
        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('user_invitations')->where('user_id', $this->invitee->id)->value('accepted_at'));

        // Le login fonctionne désormais avec le mot de passe choisi.
        $this->postJson('/api/auth/login', ['email' => 'awa@inv.sn', 'password' => 'MyNewPass1'])->assertOk();
    }

    #[Test]
    public function a_wrong_code_is_rejected(): void
    {
        $this->inviteAndCaptureCode();

        $this->postJson('/api/auth/accept-invitation', [
            'email' => 'awa@inv.sn', 'code' => '000000',
            'password' => 'MyNewPass1', 'password_confirmation' => 'MyNewPass1',
        ])->assertStatus(422);
    }

    #[Test]
    public function an_invitation_cannot_be_accepted_twice(): void
    {
        $code = $this->inviteAndCaptureCode();
        $payload = ['email' => 'awa@inv.sn', 'code' => $code, 'password' => 'MyNewPass1', 'password_confirmation' => 'MyNewPass1'];

        $this->postJson('/api/auth/accept-invitation', $payload)->assertOk();
        $this->postJson('/api/auth/accept-invitation', $payload)->assertStatus(422); // déjà acceptée
    }
}
