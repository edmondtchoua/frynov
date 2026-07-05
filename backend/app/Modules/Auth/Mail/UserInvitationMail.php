<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * RC-12 F-5 — email d'invitation à rejoindre un espace de travail (code d'activation).
 */
class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $inviteeName,
        public string $workspaceName,
        public string $inviterName,
        public string $acceptUrl,
        public int $ttlDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Frynov — invitation à rejoindre {$this->workspaceName}");
    }

    public function content(): Content
    {
        $name = e($this->inviteeName);
        $ws   = e($this->workspaceName);
        $by   = e($this->inviterName);
        $code = e($this->code);
        $url  = e($this->acceptUrl);

        return new Content(htmlString: <<<HTML
            <p>Bonjour {$name},</p>
            <p>{$by} vous invite à rejoindre l'espace <strong>{$ws}</strong> sur Frynov.</p>
            <p>Pour activer votre compte et choisir votre mot de passe, rendez-vous sur
            <a href="{$url}">{$url}</a> et saisissez ce code :</p>
            <p><strong style="font-size:1.3em;letter-spacing:2px">{$code}</strong> (valable {$this->ttlDays} jours)</p>
            <p>— L'équipe Frynov</p>
            HTML);
    }
}
