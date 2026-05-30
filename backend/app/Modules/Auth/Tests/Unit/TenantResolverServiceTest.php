<?php

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Services\TenantResolverService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class TenantResolverServiceTest extends TestCase
{
    private TenantResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TenantResolverService();
    }

    #[Test]
    public function it_extracts_subdomain_from_host(): void
    {
        $method = $this->getExtractSubdomainMethod();

        $request = Request::create('http://boutique-dakar.erp-africa.com/api/login');
        $result  = $method->invoke($this->resolver, $request);

        $this->assertEquals('boutique-dakar', $result);
    }

    #[Test]
    public function it_ignores_api_subdomain(): void
    {
        $method  = $this->getExtractSubdomainMethod();
        $request = Request::create('http://api.erp-africa.com/auth/login');

        $this->assertNull($method->invoke($this->resolver, $request));
    }

    #[Test]
    public function it_ignores_www_subdomain(): void
    {
        $method  = $this->getExtractSubdomainMethod();
        $request = Request::create('http://www.erp-africa.com/auth/login');

        $this->assertNull($method->invoke($this->resolver, $request));
    }

    #[Test]
    public function it_returns_null_for_localhost(): void
    {
        $method  = $this->getExtractSubdomainMethod();
        $request = Request::create('http://localhost/api/login');

        $this->assertNull($method->invoke($this->resolver, $request));
    }

    #[Test]
    public function it_returns_null_when_no_tenant_headers_and_no_subdomain(): void
    {
        $request = Request::create('http://localhost/api/login');

        $this->assertNull($request->header('X-Tenant-ID'));
        $this->assertNull($request->header('X-Tenant-Slug'));

        $subdomainMethod = $this->getExtractSubdomainMethod();
        $this->assertNull($subdomainMethod->invoke($this->resolver, $request));
    }

    private function getExtractSubdomainMethod(): \ReflectionMethod
    {
        $reflection = new ReflectionClass($this->resolver);
        $method     = $reflection->getMethod('extractSubdomain');
        $method->setAccessible(true);

        return $method;
    }
}
