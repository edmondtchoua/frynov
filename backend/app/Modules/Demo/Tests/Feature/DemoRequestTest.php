<?php

namespace App\Modules\Demo\Tests\Feature;

use App\Modules\Demo\Mail\DemoRequestInternalMail;
use App\Modules\Demo\Mail\DemoRequestReceivedMail;
use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'         => 'Awa',
            'last_name'          => 'Traoré',
            'email'              => 'awa@example.com',
            'phone'              => '+221 77 000 00 00',
            'company'            => 'Boutique Awa',
            'country'            => 'SN',
            'primary_need'       => 'Gestion de stock',
            'modules'            => ['catalog', 'inventory'],
            'message'            => 'Je voudrais tester la démo.',
            'consent_contact'    => true,
            'consent_demo_email' => true,
            'source'             => 'landing',
            'locale'             => 'fr',
        ], $overrides);
    }

    #[Test]
    public function a_prospect_can_submit_a_demo_request(): void
    {
        Mail::fake();

        $res = $this->postJson('/api/demo-requests', $this->payload());

        $res->assertCreated()->assertJsonStructure(['message', 'id']);

        $this->assertDatabaseHas('demo_requests', [
            'email'           => 'awa@example.com',
            'company'         => 'Boutique Awa',
            'status'          => DemoRequest::STATUS_PENDING_REVIEW,
            'consent_contact' => true,
        ]);

        $row = DemoRequest::first();
        $this->assertSame(['catalog', 'inventory'], $row->modules);
        $this->assertNotNull($row->ip_address);
    }

    #[Test]
    public function it_sends_an_acknowledgement_and_an_internal_notification(): void
    {
        Mail::fake();

        $this->postJson('/api/demo-requests', $this->payload())->assertCreated();

        Mail::assertSent(DemoRequestReceivedMail::class, fn ($m) => $m->hasTo('awa@example.com'));
        Mail::assertSent(DemoRequestInternalMail::class);
    }

    #[Test]
    public function the_honeypot_field_blocks_bots(): void
    {
        Mail::fake();

        $this->postJson('/api/demo-requests', $this->payload(['website' => 'http://spam.example']))
            ->assertStatus(422);

        $this->assertDatabaseCount('demo_requests', 0);
        Mail::assertNothingSent();
    }

    #[Test]
    public function consent_is_required(): void
    {
        $this->postJson('/api/demo-requests', $this->payload(['consent_contact' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent_contact');
    }

    #[Test]
    public function email_is_required_and_validated(): void
    {
        $this->postJson('/api/demo-requests', $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
