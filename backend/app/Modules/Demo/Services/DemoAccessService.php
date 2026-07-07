<?php

namespace App\Modules\Demo\Services;

use App\Modules\Demo\Mail\DemoAccessMail;
use App\Modules\Demo\Models\DemoRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Orchestration de l'octroi d'un accès démo : provisionne le tenant éphémère,
 * envoie l'email d'accès, met à jour la demande. Utilisé par l'approbation
 * back-office (mode manuel) et par le mode automatique du formulaire public.
 */
class DemoAccessService
{
    public function __construct(private readonly DemoProvisioningService $provisioning) {}

    /**
     * Provisionne + envoie les accès. Idempotent au niveau métier : si la demande
     * a déjà un tenant démo, on ne re-provisionne pas (renvoi possible via resend()).
     */
    public function grant(DemoRequest $request, ?string $actorId = null): DemoRequest
    {
        if ($request->demo_tenant_id) {
            return $this->resend($request);
        }

        $result = $this->provisioning->provisionFor($request);

        $this->sendAccessEmail($request, $result['user']->email, $result['password'], $result['expires_at']);

        $request->update([
            'status'         => DemoRequest::STATUS_ACCESS_SENT,
            'reviewed_by'    => $actorId,
            'reviewed_at'    => now(),
            'access_sent_at' => now(),
        ]);

        return $request->refresh();
    }

    /**
     * Renvoie les accès : réinitialise le mot de passe de l'utilisateur démo
     * existant (le mot de passe original n'est jamais stocké en clair) et ré-expédie.
     */
    public function resend(DemoRequest $request): DemoRequest
    {
        $user = $request->demo_user_id
            ? \App\Models\User::withoutGlobalScopes()->find($request->demo_user_id)
            : null;

        if (! $user) {
            // Pas de tenant/user provisionné : (re)provisionne proprement.
            $request->update(['demo_tenant_id' => null, 'demo_user_id' => null]);
            return $this->grant($request);
        }

        $password = \Illuminate\Support\Str::password(14);
        $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($password)])->save();

        $expiresAt = $request->demo_access_expires_at ?? now()->addDays((int) config('demo.access_ttl_days', 14));

        $this->sendAccessEmail($request, $user->email, $password, $expiresAt);

        $request->update(['access_sent_at' => now(), 'status' => DemoRequest::STATUS_ACCESS_SENT]);

        return $request->refresh();
    }

    private function sendAccessEmail(DemoRequest $request, string $loginEmail, string $password, $expiresAt): void
    {
        $loginUrl = rtrim((string) config('demo.app_url'), '/').'/login';
        $until    = $expiresAt instanceof \DateTimeInterface ? $expiresAt->format('d/m/Y') : (string) $expiresAt;

        try {
            Mail::to($request->email)->send(new DemoAccessMail(
                demoRequest: $request,
                loginEmail: $loginEmail,
                temporaryPassword: $password,
                loginUrl: $loginUrl,
                expiresAt: $until,
            ));
            $request->update(['access_email_status' => 'sent']);
        } catch (\Throwable $e) {
            $request->update(['access_email_status' => 'failed']);
            Log::warning('[demo] envoi email acces echoue', ['demo_request' => $request->id, 'error' => $e->getMessage()]);
        }
    }
}
