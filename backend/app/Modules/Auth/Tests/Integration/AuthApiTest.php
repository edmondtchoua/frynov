<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'     => 'Boutique Dakar',
            'slug'     => 'boutique-dakar',
            'plan'     => 'starter',
            'status'   => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr'],
        ]);
    }

    #[Test]
    public function it_returns_token_on_valid_credentials(): void
    {
        User::create([
            'name'      => 'Aminata Diallo',
            'email'     => 'aminata@boutique-dakar.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'     => 'aminata@boutique-dakar.sn',
            'password'  => 'Secret123!',
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'tenant_id']]);
    }

    #[Test]
    public function it_returns_422_on_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function it_returns_401_on_wrong_password(): void
    {
        User::create([
            'name'      => 'Koffi Mensah',
            'email'     => 'koffi@boutique-dakar.sn',
            'password'  => Hash::make('CorrectPass1!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'     => 'koffi@boutique-dakar.sn',
            'password'  => 'WrongPass1!',
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_returns_401_on_me_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_returns_user_on_me_endpoint(): void
    {
        $user = User::create([
            'name'      => 'Fatou Sow',
            'email'     => 'fatou@boutique-dakar.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $token    = $user->createToken('api')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'fatou@boutique-dakar.sn');
    }

    #[Test]
    public function it_invalidates_token_on_logout(): void
    {
        $user = User::create([
            'name'      => 'Ibrahim Coulibaly',
            'email'     => 'ibrahim@boutique-dakar.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        // Verify the token row was deleted from the DB
        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    #[Test]
    public function it_returns_403_when_tenant_is_suspended(): void
    {
        $this->tenant->update(['status' => 'suspended']);

        User::create([
            'name'      => 'Moussa Traoré',
            'email'     => 'moussa@boutique-dakar.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'     => 'moussa@boutique-dakar.sn',
            'password'  => 'Secret123!',
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function a_login_named_route_exists_so_auth_redirects_do_not_500(): void
    {
        // Regression: the auth middleware redirects unauthenticated browser hits to
        // route('login'). Without a `login` named route this threw
        // "Route [login] not defined" (e.g. opening /api/export/* directly).
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('login'));
        $this->get('/login')->assertStatus(401);
    }
}
