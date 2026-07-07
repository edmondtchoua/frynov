<?php

namespace App\Modules\Demo\Mail;

use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email contenant les accès de démonstration (lien + identifiant + mot de passe
 * temporaire + date de validité). Insiste sur le caractère fictif des données.
 */
class DemoAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DemoRequest $demoRequest,
        public string $loginEmail,
        public string $temporaryPassword,
        public string $loginUrl,
        public string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isEn() ? 'Your Frynov demo access' : 'Vos accès de démonstration Frynov',
        );
    }

    public function content(): Content
    {
        $name  = e($this->demoRequest->fullName());
        $email = e($this->loginEmail);
        $pass  = e($this->temporaryPassword);
        $url   = e($this->loginUrl);
        $until = e($this->expiresAt);

        $html = $this->isEn()
            ? <<<HTML
                <p>Hello {$name},</p>
                <p>Your <strong>Frynov</strong> demo workspace is ready. Log in with:</p>
                <ul>
                  <li><strong>Login page</strong>: <a href="{$url}">{$url}</a></li>
                  <li><strong>Email</strong>: {$email}</li>
                  <li><strong>Temporary password</strong>: {$pass}</li>
                </ul>
                <p>This access is valid until <strong>{$until}</strong>.</p>
                <p><em>All data in this workspace is fictitious and for demonstration only.</em></p>
                <p>— The Frynov team</p>
                HTML
            : <<<HTML
                <p>Bonjour {$name},</p>
                <p>Votre espace de démonstration <strong>Frynov</strong> est prêt. Connectez-vous avec :</p>
                <ul>
                  <li><strong>Page de connexion</strong> : <a href="{$url}">{$url}</a></li>
                  <li><strong>Identifiant</strong> : {$email}</li>
                  <li><strong>Mot de passe temporaire</strong> : {$pass}</li>
                </ul>
                <p>Cet accès est valable jusqu'au <strong>{$until}</strong>.</p>
                <p><em>Toutes les données de cet espace sont fictives et servent uniquement à la démonstration.</em></p>
                <p>— L'équipe Frynov</p>
                HTML;

        return new Content(htmlString: $html);
    }

    private function isEn(): bool
    {
        return ($this->demoRequest->locale ?? 'fr') === 'en';
    }
}
