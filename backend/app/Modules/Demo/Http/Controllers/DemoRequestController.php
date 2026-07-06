<?php

namespace App\Modules\Demo\Http\Controllers;

use App\Modules\Demo\Http\Requests\StoreDemoRequestRequest;
use App\Modules\Demo\Mail\DemoRequestInternalMail;
use App\Modules\Demo\Mail\DemoRequestReceivedMail;
use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller;

/**
 * Endpoint PUBLIC : réception d'une demande de démo depuis le formulaire de contact.
 */
class DemoRequestController extends Controller
{
    public function store(StoreDemoRequestRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['website']); // honeypot retiré

        $demoRequest = DemoRequest::create([
            ...$data,
            'consent_demo_email' => (bool) ($data['consent_demo_email'] ?? false),
            'locale'             => $data['locale'] ?? app()->getLocale() ?: 'fr',
            'ip_address'         => $request->ip(),
            'user_agent'         => substr((string) $request->userAgent(), 0, 2000),
            'status'             => DemoRequest::STATUS_PENDING_REVIEW,
        ]);

        // Accusé de réception prospect + notification interne (best-effort : un échec
        // d'email n'invalide jamais l'enregistrement de la demande).
        $this->safeMail(
            fn () => Mail::to($demoRequest->email)->send(new DemoRequestReceivedMail($demoRequest)),
            'demo.ack',
            $demoRequest->id,
        );

        if ($to = config('demo.internal_notification_email')) {
            $this->safeMail(
                fn () => Mail::to($to)->send(new DemoRequestInternalMail($demoRequest)),
                'demo.internal',
                $demoRequest->id,
            );
        }

        return response()->json([
            'message' => $demoRequest->locale === 'en'
                ? 'Thank you! Your demo request has been received. We will email you your access shortly.'
                : 'Merci ! Votre demande de démo a bien été reçue. Vous recevrez vos accès par email très prochainement.',
            'id' => $demoRequest->id,
        ], 201);
    }

    private function safeMail(callable $send, string $kind, string $id): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning("[demo] envoi email {$kind} échoué", ['demo_request' => $id, 'error' => $e->getMessage()]);
        }
    }
}
