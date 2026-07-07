<?php

namespace App\Modules\Demo\Mail;

use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accusé de réception envoyé au prospect après soumission du formulaire de démo.
 */
class DemoRequestReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest) {}

    public function envelope(): Envelope
    {
        $subject = $this->isEn()
            ? 'Frynov — we received your demo request'
            : 'Frynov — nous avons bien reçu votre demande de démo';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $name = e($this->demoRequest->fullName());

        $html = $this->isEn()
            ? <<<HTML
                <p>Hello {$name},</p>
                <p>Thank you for your interest in <strong>Frynov</strong>. We have received your
                demo request and our team will get back to you shortly with your access.</p>
                <p>— The Frynov team</p>
                HTML
            : <<<HTML
                <p>Bonjour {$name},</p>
                <p>Merci de votre intérêt pour <strong>Frynov</strong>. Nous avons bien reçu votre
                demande de démonstration ; notre équipe revient vers vous très vite avec vos accès.</p>
                <p>— L'équipe Frynov</p>
                HTML;

        return new Content(htmlString: $html);
    }

    private function isEn(): bool
    {
        return ($this->demoRequest->locale ?? 'fr') === 'en';
    }
}
