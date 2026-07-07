<?php

namespace App\Modules\Demo\Mail;

use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Rappel envoyé quelques jours avant l'expiration de l'accès démo. */
class DemoReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest, public string $expiresAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isEn() ? 'Your Frynov demo is ending soon' : 'Votre démo Frynov se termine bientôt',
        );
    }

    public function content(): Content
    {
        $name  = e($this->demoRequest->fullName());
        $until = e($this->expiresAt);

        $html = $this->isEn()
            ? "<p>Hello {$name},</p><p>Your Frynov demo access ends on <strong>{$until}</strong>. "
                ."Would you like to keep your data and activate a real account? Just reply to this email.</p>"
                ."<p>— The Frynov team</p>"
            : "<p>Bonjour {$name},</p><p>Votre accès de démonstration Frynov se termine le <strong>{$until}</strong>. "
                ."Vous souhaitez conserver vos données et activer un compte réel ? Répondez simplement à cet email.</p>"
                ."<p>— L'équipe Frynov</p>";

        return new Content(htmlString: $html);
    }

    private function isEn(): bool
    {
        return ($this->demoRequest->locale ?? 'fr') === 'en';
    }
}
