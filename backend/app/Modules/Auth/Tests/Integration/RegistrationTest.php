<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the admin role (normally done by RolesAndPermissionsSeeder)
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Seed starter plan (needed to create subscription)
        Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);
    }

    #[Test]
    public function a_new_user_can_register_and_creates_tenant_and_subscription(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'company_name'          => 'Boutique Dakar',
            'name'                  => 'Fatou Diallo',
            'email'                 => 'fatou@boutique-dakar.sn',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'tenant_id', 'roles'],
            ]);

        // Tenant was created
        $this->assertDatabaseHas('tenants', ['name' => 'Boutique Dakar']);

        // User is linked to the tenant and has admin role
        $user = \App\Models\User::where('email', 'fatou@boutique-dakar.sn')->firstOrFail();
        $this->assertNotNull($user->tenant_id);
        $this->assertTrue($user->hasRole('admin'));

        // Subscription was created
        $tenant = Tenant::find($user->tenant_id);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status'    => Subscription::STATUS_TRIALING,
        ]);
    }

    #[Test]
    public function registration_requires_company_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Fatou Diallo',
            'email'                 => 'fatou@test.sn',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);
    }

    #[Test]
    public function registration_requires_unique_email(): void
    {
        \App\Models\User::create([
            'name'      => 'Existing',
            'email'     => 'existing@test.sn',
            'password'  => bcrypt('Secret123!'),
            'tenant_id' => null,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'company_name'          => 'Test Co',
            'name'                  => 'Someone Else',
            'email'                 => 'existing@test.sn',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function registration_requires_strong_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'company_name'          => 'Test Co',
            'name'                  => 'Fatou Diallo',
            'email'                 => 'fatou@test.sn',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function login_works_after_registration(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name'          => 'Boutique Test',
            'name'                  => 'Mamadou Ba',
            'email'                 => 'mamadou@test.sn',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertStatus(201);

        $this->postJson('/api/auth/login', [
            'email'    => 'mamadou@test.sn',
            'password' => 'Secret123!',
        ])->assertStatus(200)
          ->assertJsonStructure(['token', 'user']);
    }
}
