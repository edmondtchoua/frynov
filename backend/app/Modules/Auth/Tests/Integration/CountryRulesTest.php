<?php
namespace App\Modules\Auth\Tests\Integration;

use App\Modules\Auth\Models\CountryRule;
use App\Modules\Auth\Services\RegistrationRuleService;
use App\Modules\Billing\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CountryRulesTest extends TestCase
{
    use RefreshDatabase;
    private RegistrationRuleService $svc;
    protected function setUp(): void {
        parent::setUp();
        $this->svc = app(RegistrationRuleService::class);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
    }
    #[Test] public function no_rule_allows_registration(): void {
        $this->svc->check('SN');
        $this->assertTrue(true);
    }
    #[Test] public function blocked_country_throws(): void {
        CountryRule::create(['country_code' => 'XX', 'is_blocked' => true]);
        $this->expectException(\DomainException::class);
        $this->svc->check('XX');
    }
    #[Test] public function requires_approval_flag(): void {
        CountryRule::create(['country_code' => 'YY', 'requires_approval' => true]);
        $this->assertTrue($this->svc->requiresPendingApproval('YY'));
        $this->assertFalse($this->svc->requiresPendingApproval('SN'));
    }
    #[Test] public function defaults_returned(): void {
        CountryRule::create(['country_code' => 'SN', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Dakar']);
        $d = $this->svc->defaultsForCountry('SN');
        $this->assertSame('XOF', $d['currency']);
    }
    #[Test] public function blocked_country_register_returns_403(): void {
        CountryRule::create(['country_code' => 'ZZ', 'is_blocked' => true]);
        $this->postJson('/api/auth/register', [
            'name'                  => 'T',
            'email'                 => 't@zz.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'company_name'          => 'Corp',
            'country'               => 'ZZ',
            'currency'              => 'USD',
        ])->assertStatus(403);
    }
}
