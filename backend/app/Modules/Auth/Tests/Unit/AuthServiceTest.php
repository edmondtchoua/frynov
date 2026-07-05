<?php

namespace App\Modules\Auth\Tests\Unit;

use App\Models\User;
use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Exceptions\TenantInactiveException;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Tenants\Models\Tenant;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Extends Laravel TestCase (app bootstrapped) but does NOT use RefreshDatabase.
// All DB calls are mocked via UserRepositoryInterface.
class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    /** @var MockInterface&UserRepositoryInterface */
    private MockInterface $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users       = Mockery::mock(UserRepositoryInterface::class);
        $this->authService = new AuthService($this->users);
    }

    #[Test]
    public function it_validates_correct_password(): void
    {
        $user           = new User();
        $user->password = 'secret123'; // 'hashed' cast applies Hash::make automatically

        $this->assertTrue($this->authService->validateCredentials($user, 'secret123'));
    }

    #[Test]
    public function it_rejects_wrong_password(): void
    {
        $user           = new User();
        $user->password = 'secret123';

        $this->assertFalse($this->authService->validateCredentials($user, 'mauvais_mdp'));
    }

    #[Test]
    public function it_allows_super_admin_without_tenant(): void
    {
        $user                 = new User();
        $user->is_super_admin = true;

        $this->assertTrue($this->authService->canAccessTenant($user));
    }

    #[Test]
    public function it_allows_user_with_active_tenant(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->allows('isActive')->andReturn(true);

        $user                 = new User();
        $user->is_super_admin = false;
        $user->setRelation('tenant', $tenant);

        $this->assertTrue($this->authService->canAccessTenant($user));
    }

    #[Test]
    public function it_denies_user_with_inactive_tenant(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->allows('isActive')->andReturn(false);

        $user                 = new User();
        $user->is_super_admin = false;
        $user->setRelation('tenant', $tenant);

        $this->assertFalse($this->authService->canAccessTenant($user));
    }

    #[Test]
    public function it_denies_user_without_any_tenant(): void
    {
        $user                 = new User();
        $user->is_super_admin = false;
        $user->setRelation('tenant', null);

        $this->assertFalse($this->authService->canAccessTenant($user));
    }

    #[Test]
    public function it_throws_on_inactive_tenant_during_login(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->allows('isActive')->andReturn(false);

        $user                 = new User();
        $user->is_super_admin = false;
        $user->password       = 'secret123';
        $user->setRelation('tenant', $tenant);

        $this->users->allows('findByEmail')->andReturn($user);

        $this->expectException(TenantInactiveException::class);

        $this->authService->login('user@example.com', 'secret123');
    }

    #[Test]
    public function it_throws_on_invalid_credentials(): void
    {
        $this->users->allows('findByEmail')->andReturn(null);

        $this->expectException(InvalidCredentialsException::class);

        $this->authService->login('nobody@example.com', 'wrong');
    }
}
