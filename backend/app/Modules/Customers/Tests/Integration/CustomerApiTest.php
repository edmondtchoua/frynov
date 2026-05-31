<?php

namespace App\Modules\Customers\Tests\Integration;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Boutique Test',
            'slug'   => 'boutique-test',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@boutique-test.com',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->token = $this->user->createToken('api')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Client',
            'email'     => 'test@example.com',
        ], $overrides));
    }

    #[Test]
    public function it_lists_customers_for_authenticated_user(): void
    {
        $this->makeCustomer(['name' => 'Alice', 'email' => 'alice@test.com']);
        $this->makeCustomer(['name' => 'Bob', 'email' => null]);

        $res = $this->getJson('/api/customers', $this->auth());

        $res->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Alice');
    }

    #[Test]
    public function it_filters_by_search(): void
    {
        $this->makeCustomer(['name' => 'Amina Diallo', 'email' => 'amina@test.com']);
        $this->makeCustomer(['name' => 'Awa Ndiaye', 'email' => null]);

        $res = $this->getJson('/api/customers?search=Diallo', $this->auth());

        $res->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_requires_auth_to_list_customers(): void
    {
        $this->getJson('/api/customers')->assertUnauthorized();
    }

    #[Test]
    public function it_creates_a_customer(): void
    {
        $res = $this->postJson('/api/customers', [
            'name'  => 'Nouveau Client',
            'email' => 'nouveau@example.com',
            'phone' => '+221 77 999 99 99',
        ], $this->auth());

        $res->assertCreated()
            ->assertJsonPath('data.name', 'Nouveau Client')
            ->assertJsonPath('data.email', 'nouveau@example.com');

        $this->assertDatabaseHas('customers', [
            'name'      => 'Nouveau Client',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_validates_name_is_required(): void
    {
        $res = $this->postJson('/api/customers', ['email' => 'no-name@test.com'], $this->auth());

        $res->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_email_format(): void
    {
        $res = $this->postJson('/api/customers', [
            'name'  => 'Bad Email',
            'email' => 'not-an-email',
        ], $this->auth());

        $res->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_shows_a_customer(): void
    {
        $customer = $this->makeCustomer();

        $res = $this->getJson("/api/customers/{$customer->id}", $this->auth());

        $res->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', $customer->name);
    }

    #[Test]
    public function it_returns_404_for_unknown_customer(): void
    {
        $this->getJson('/api/customers/non-existent-id', $this->auth())
            ->assertNotFound();
    }

    #[Test]
    public function it_updates_a_customer(): void
    {
        $customer = $this->makeCustomer(['name' => 'Before']);

        $res = $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'After',
        ], $this->auth());

        $res->assertOk()->assertJsonPath('data.name', 'After');
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'After']);
    }

    #[Test]
    public function it_deletes_a_customer(): void
    {
        $customer = $this->makeCustomer();

        $this->deleteJson("/api/customers/{$customer->id}", [], $this->auth())
            ->assertNoContent();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    #[Test]
    public function it_cannot_access_another_tenants_customer(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active',
        ]);
        $otherCustomer = Customer::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Private Customer',
        ]);

        $this->getJson("/api/customers/{$otherCustomer->id}", $this->auth())
            ->assertNotFound();
    }

    #[Test]
    public function it_searches_customers_via_endpoint(): void
    {
        $this->makeCustomer(['name' => 'Fatou Ba', 'email' => null]);
        $this->makeCustomer(['name' => 'Ibou Sy', 'email' => null]);

        $res = $this->getJson('/api/customers/search?q=Fatou', $this->auth());

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fatou Ba');
    }
}
