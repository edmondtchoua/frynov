<?php
namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\CountryRule;

class RegistrationRuleService
{
    public function check(string $country): void
    {
        $rule = CountryRule::forCountry($country);
        if (!$rule) return;
        if ($rule->is_blocked) {
            throw new \DomainException("L'inscription n'est pas disponible dans votre pays ({$country}).");
        }
    }

    public function requiresPendingApproval(string $country): bool
    {
        $rule = CountryRule::forCountry($country);
        return $rule?->requires_approval ?? false;
    }

    public function isBlocked(string $country): bool
    {
        $rule = CountryRule::forCountry($country);
        return $rule?->is_blocked ?? false;
    }

    public function defaultsForCountry(string $country): array
    {
        $rule = CountryRule::forCountry($country);
        return ["currency" => $rule?->default_currency, "timezone" => $rule?->default_timezone];
    }
}
