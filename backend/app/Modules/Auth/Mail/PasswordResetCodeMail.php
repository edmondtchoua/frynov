<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * RC-10 F-3 — email de code de réinitialisation de mot de passe (plateforme, mailer natif).
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $userName,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Frynov — code de réinitialisation de mot de passe');
    }

    public function content(): Content
    {
        $name = e($this->userName);
        $code = e($this->code);

        return new Content(htmlString: <<<HTML
            <p>Bonjour {$name},</p>
            <p>Vous avez demandé à réinitialiser votre mot de passe Frynov.</p>
            <p>Votre code de vérification : <strong style="font-size:1.3em;letter-spacing:2px">{$code}</strong></p>
            <p>Ce code est valable {$this->ttlMinutes} minutes. Si vous n'êtes pas à l'origine de cette
            demande, ignorez cet email — votre mot de passe reste inchangé.</p>
            <p>— L'équipe Frynov</p>
            HTML);
    }
}
