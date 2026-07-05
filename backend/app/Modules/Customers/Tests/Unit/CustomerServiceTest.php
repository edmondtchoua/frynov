<?php

namespace App\Modules\Customers\Tests\Unit;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerService $service;
    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CustomerService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Boutique Test',
            'slug'   => 'boutique-test',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@test.com',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_creates_a_customer(): void
    {
        $customer = $this->service->create([
            'name'  => 'Amina Diallo',
            'email' => 'amina@example.com',
            'phone' => '+221 77 000 00 00',
        ], $this->tenant->id);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('Amina Diallo', $customer->name);
        $this->assertEquals($this->tenant->id, $customer->tenant_id);
        $this->assertDatabaseHas('customers', ['email' => 'amina@example.com']);
    }

    #[Test]
    public function it_creates_a_customer_without_email(): void
    {
        $customer = $this->service->create([
            'name'  => 'Oumar Sy',
            'phone' => '+221 76 111 11 11',
        ], $this->tenant->id);

        $this->assertNull($customer->email);
        $this->assertEquals('Oumar Sy', $customer->name);
    }

    #[Test]
    public function it_stores_address_as_json(): void
    {
        $customer = $this->service->create([
            'name'    => 'Fatou Ba',
            'address' => [
                'street'  => 'Rue 10 x 15',
                'city'    => 'Dakar',
                'country' => 'Sénégal',
                'zip'     => '10000',
            ],
        ], $this->tenant->id);

        $this->assertEquals('Dakar', $customer->address['city']);
        $this->assertEquals('Sénégal', $customer->address['country']);
    }

    #[Test]
    public function it_updates_a_customer(): void
    {
        $customer = $this->service->create(['name' => 'Moussa Traoré'], $this->tenant->id);

        $updated = $this->service->update($customer, [
            'name'  => 'Moussa Traoré Jr.',
            'email' => 'moussa@example.com',
        ]);

        $this->assertEquals('Moussa Traoré Jr.', $updated->name);
        $this->assertEquals('moussa@example.com', $updated->email);
    }

    #[Test]
    public function it_soft_deletes_a_customer(): void
    {
        $customer = $this->service->create(['name' => 'À Supprimer'], $this->tenant->id);
        $id       = $customer->id;

        $this->service->delete($customer);

        $this->assertSoftDeleted('customers', ['id' => $id]);
        $this->assertDatabaseMissing('customers', ['id' => $id, 'deleted_at' => null]);
    }

    #[Test]
    public function it_finds_a_customer_by_id(): void
    {
        $customer = $this->service->create(['name' => 'Findable'], $this->tenant->id);
        $found    = $this->service->findOrFail($customer->id, $this->tenant->id);

        $this->assertEquals($customer->id, $found->id);
    }

    #[Test]
    public function it_throws_404_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active',
        ]);

        $customer = $this->service->create(['name' => 'Wrong Tenant'], $this->tenant->id);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findOrFail($customer->id, $otherTenant->id);
    }

    #[Test]
    public function it_searches_customers_by_name_email_or_phone(): void
    {
        $this->service->create(['name' => 'Alpha Diallo', 'phone' => '+221770001'], $this->tenant->id);
        $this->service->create(['name' => 'Beta Diallo', 'email' => 'beta@test.com'], $this->tenant->id);
        $this->service->create(['name' => 'Gamma Koné'], $this->tenant->id);

        $byName  = $this->service->search('Diallo', $this->tenant->id);
        $byEmail = $this->service->search('beta@', $this->tenant->id);
        $byPhone = $this->service->search('0001', $this->tenant->id);

        $this->assertCount(2, $byName);
        $this->assertCount(1, $byEmail);
        $this->assertCount(1, $byPhone);
    }

    #[Test]
    public function it_paginates_the_customer_list(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->service->create(['name' => "Client {$i}"], $this->tenant->id);
        }

        $page1 = $this->service->list($this->tenant->id, ['per_page' => 10]);

        $this->assertEquals(10, $page1->perPage());
        $this->assertEquals(25, $page1->total());
        $this->assertEquals(3, $page1->lastPage());
    }
}
