<?php

namespace App\Modules\Auth\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-9 — durcissement plateforme : en-têtes de sécurité (F-10) + plafond de débit global (F-9).
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function security_headers_are_present_on_api_responses(): void
    {
        // Route publique (réponse générique) — les en-têtes s'appliquent quel que soit le statut.
        $res = $this->postJson('/api/portal/login', ['email' => 'x@y.sn', 'password' => 'whatever']);

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function the_global_api_rate_limit_returns_429_when_exceeded(): void
    {
        config(['security.api_rate_limit' => 3]); // réactivé et bas pour ce test

        // 3 requêtes tolérées (422 identifiants invalides), la 4ᵉ est plafonnée globalement.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/portal/login', ['email' => 'a@b.sn', 'password' => 'nope'])
                ->assertStatus(422);
        }

        $this->postJson('/api/portal/login', ['email' => 'a@b.sn', 'password' => 'nope'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
