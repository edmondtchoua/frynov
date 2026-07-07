<?php

namespace App\Modules\Demo\Mail;

use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification interne : une nouvelle demande de démo est arrivée.
 */
class DemoRequestInternalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande de démo — '.$this->demoRequest->fullName(),
        );
    }

    public function content(): Content
    {
        $r       = $this->demoRequest;
        $name    = e($r->fullName());
        $email   = e($r->email);
        $company = e($r->company ?: '—');
        $phone   = e($r->phone ?: '—');
        $country = e($r->country ?: '—');
        $need    = e($r->primary_need ?: '—');
        $modules = e($r->modules ? implode(', ', $r->modules) : '—');
        $message = e($r->message ?: '—');
        $source  = e($r->source ?: '—');

        return new Content(htmlString: <<<HTML
            <p>Une nouvelle demande de démonstration a été soumise :</p>
            <ul>
              <li><strong>Nom</strong> : {$name}</li>
              <li><strong>Email</strong> : {$email}</li>
              <li><strong>Entreprise</strong> : {$company}</li>
              <li><strong>Téléphone</strong> : {$phone}</li>
              <li><strong>Pays</strong> : {$country}</li>
              <li><strong>Besoin principal</strong> : {$need}</li>
              <li><strong>Modules</strong> : {$modules}</li>
              <li><strong>Source</strong> : {$source}</li>
            </ul>
            <p><strong>Message :</strong><br>{$message}</p>
            <p>Traitez cette demande depuis le back-office (Demandes de démo).</p>
            HTML);
    }
}
