<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * RC-13 F-4 — code de connexion à deux facteurs (envoyé à l'email du compte).
 */
class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $userName,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Frynov — votre code de connexion');
    }

    public function content(): Content
    {
        $name = e($this->userName);
        $code = e($this->code);

        return new Content(htmlString: <<<HTML
            <p>Bonjour {$name},</p>
            <p>Votre code de connexion Frynov : <strong style="font-size:1.3em;letter-spacing:2px">{$code}</strong></p>
            <p>Il est valable {$this->ttlMinutes} minutes. Si vous n'avez pas tenté de vous connecter,
            changez votre mot de passe : quelqu'un connaît peut-être vos identifiants.</p>
            <p>— L'équipe Frynov</p>
            HTML);
    }
}
