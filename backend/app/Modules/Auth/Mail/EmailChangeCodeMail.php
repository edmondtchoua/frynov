<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * RC-11 F-6 — code de confirmation de changement d'email (envoyé à la NOUVELLE adresse).
 */
class EmailChangeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $userName,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Frynov — confirmez votre nouvelle adresse email');
    }

    public function content(): Content
    {
        $name = e($this->userName);
        $code = e($this->code);

        return new Content(htmlString: <<<HTML
            <p>Bonjour {$name},</p>
            <p>Pour confirmer cette adresse comme nouvel email de votre compte Frynov, saisissez ce code :</p>
            <p><strong style="font-size:1.3em;letter-spacing:2px">{$code}</strong> (valable {$this->ttlMinutes} minutes)</p>
            <p>Si vous n'êtes pas à l'origine de ce changement, ignorez cet email — votre adresse actuelle reste inchangée.</p>
            <p>— L'équipe Frynov</p>
            HTML);
    }
}
