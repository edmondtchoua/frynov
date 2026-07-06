<?php

namespace App\Modules\Demo\Mail;

use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email de fin de démo : propose une activation réelle / un contact commercial. */
class DemoEndedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isEn() ? 'Your Frynov demo has ended' : 'Votre démo Frynov est terminée',
        );
    }

    public function content(): Content
    {
        $name = e($this->demoRequest->fullName());

        $html = $this->isEn()
            ? "<p>Hello {$name},</p><p>Your Frynov demo access has ended. We hope you enjoyed it! "
                ."To activate a real account or talk to our team, simply reply to this email.</p>"
                ."<p>— The Frynov team</p>"
            : "<p>Bonjour {$name},</p><p>Votre accès de démonstration Frynov est arrivé à échéance. "
                ."Nous espérons qu'il vous a plu ! Pour activer un compte réel ou échanger avec notre équipe, "
                ."répondez simplement à cet email.</p><p>— L'équipe Frynov</p>";

        return new Content(htmlString: $html);
    }

    private function isEn(): bool
    {
        return ($this->demoRequest->locale ?? 'fr') === 'en';
    }
}
