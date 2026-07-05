<?php

namespace App\Modules\Platform\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Models\CountryRule;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCountryRuleTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Corp', 'slug' => 'corp', 'plan' => 'starter', 'status' => 'active']);

        // is_super_admin is NOT fillable — promote explicitly (mirrors AdminApiTest).
        $superAdmin = User::create(['name' => 'SA', 'email' => 'sa@frynov.com', 'password' => Hash::make('Admin123!'), 'tenant_id' => null]);
        $superAdmin->promoteToSuperAdmin();

        $regular = User::create(['name' => 'U', 'email' => 'u@corp.sn', 'password' => Hash::make('User123!'), 'tenant_id' => $tenant->id]);

        $this->adminToken = $superAdmin->createToken('api')->plainTextToken;
        $this->userToken  = $regular->createToken('api')->plainTextToken;
    }

    private function adminAuth(): array { return ['Authorization' => "Bearer {$this->adminToken}"]; }

    private function userAuth(): array { return ['Authorization' => "Bearer {$this->userToken}"]; }

    #[Test]
    public function super_admin_can_list_country_rules(): void
    {
        CountryRule::create(['country_code' => 'SN', 'default_currency' => 'XOF', 'is_active' => true]);

        $this->getJson('/api/admin/country-rules', $this->adminAuth())
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('data.0.country_code', 'SN');
    }

    #[Test]
    public function super_admin_can_create_a_rule_and_codes_are_uppercased(): void
    {
        $this->postJson('/api/admin/country-rules', [
            'country_code'      => 'ci',
            'default_currency'  => 'xof',
            'requires_approval' => true,
            'allowed_plans'     => ['starter', 'pro'],
        ], $this->adminAuth())
            ->assertCreated()
            ->assertJsonPath('country_code', 'CI')
            ->assertJsonPath('default_currency', 'XOF')
            ->assertJsonPath('requires_approval', true);

        $this->assertDatabaseHas('country_rules', ['country_code' => 'CI']);
    }

    #[Test]
    public function a_duplicate_country_code_is_rejected_case_insensitively(): void
    {
        CountryRule::create(['country_code' => 'SN', 'is_active' => true]);

        // lowercase "sn" must still collide with the stored "SN".
        $this->postJson('/api/admin/country-rules', ['country_code' => 'sn'], $this->adminAuth())
            ->assertStatus(422)
            ->assertJsonValidationErrors('country_code');
    }

    #[Test]
    public function a_non_iso_country_code_is_rejected(): void
    {
        $this->postJson('/api/admin/country-rules', ['country_code' => 'SEN'], $this->adminAuth())
            ->assertStatus(422)
            ->assertJsonValidationErrors('country_code');
    }

    #[Test]
    public function super_admin_can_update_a_rule(): void
    {
        $rule = CountryRule::create(['country_code' => 'CM', 'default_currency' => 'XAF', 'is_blocked' => false]);

        $this->patchJson("/api/admin/country-rules/{$rule->id}", ['is_blocked' => true, 'requires_approval' => true], $this->adminAuth())
            ->assertOk()
            ->assertJsonPath('is_blocked', true)
            ->assertJsonPath('requires_approval', true);

        $this->assertDatabaseHas('country_rules', ['id' => $rule->id, 'is_blocked' => true]);
    }

    #[Test]
    public function super_admin_can_delete_a_rule(): void
    {
        $rule = CountryRule::create(['country_code' => 'GH', 'is_active' => true]);

        $this->deleteJson("/api/admin/country-rules/{$rule->id}", [], $this->adminAuth())->assertOk();

        $this->assertDatabaseMissing('country_rules', ['id' => $rule->id]);
    }

    #[Test]
    public function a_regular_user_cannot_manage_country_rules(): void
    {
        $rule = CountryRule::create(['country_code' => 'SN', 'is_active' => true]);

        $this->getJson('/api/admin/country-rules', $this->userAuth())->assertStatus(403);
        $this->postJson('/api/admin/country-rules', ['country_code' => 'TG'], $this->userAuth())->assertStatus(403);
        $this->patchJson("/api/admin/country-rules/{$rule->id}", ['is_blocked' => true], $this->userAuth())->assertStatus(403);
        $this->deleteJson("/api/admin/country-rules/{$rule->id}", [], $this->userAuth())->assertStatus(403);
    }

    #[Test]
    public function a_guest_cannot_manage_country_rules(): void
    {
        $this->getJson('/api/admin/country-rules')->assertStatus(401);
    }
}
