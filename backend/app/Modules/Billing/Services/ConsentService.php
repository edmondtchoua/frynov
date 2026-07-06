<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Models\SubscriptionConsent;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Enregistrement du consentement (P2). Trace IP/user-agent/version, immuable. Le texte et la version
 * du consentement sont centralisés ici (source de vérité) pour rester cohérents entre front et back.
 */
class ConsentService
{
    /** Version courante du texte de consentement au changement de plan. */
    public const PLAN_CHANGE_VERSION = '2026-07-05';

    /** Texte exact présenté et accepté (figé dans la trace). */
    public const PLAN_CHANGE_TEXT =
        "Je confirme ma demande de changement de plan. J'accepte que les conditions de mon abonnement "
        ."(plan, périodicité, modules, quotas) soient modifiées en conséquence, que le montant affiché "
        ."soit dû, et je consens aux notifications liées au traitement de cette demande.";

    /**
     * Consentement AUTOMATIQUE (case cochée) capté depuis la requête HTTP, lié à une demande.
     */
    public function recordPlatform(
        Request $request,
        Tenant $tenant,
        User $user,
        SubscriptionChangeRequest $changeRequest,
    ): SubscriptionConsent {
        return SubscriptionConsent::create([
            'tenant_id'           => $tenant->id,
            'user_id'             => $user->id,
            'action_type'         => SubscriptionConsent::ACTION_PLAN_CHANGE,
            'consent_text'        => self::PLAN_CHANGE_TEXT,
            'consent_version'     => self::PLAN_CHANGE_VERSION,
            'accepted_at'         => now(),
            'ip_address'          => $request->ip(),
            'user_agent'          => substr((string) $request->userAgent(), 0, 512),
            'source'              => SubscriptionConsent::SOURCE_PLATFORM,
            'related_entity_type' => SubscriptionChangeRequest::class,
            'related_entity_id'   => $changeRequest->id,
        ]);
    }

    /**
     * Consentement MANUEL saisi par un admin (accord reçu hors plateforme).
     */
    public function recordManual(
        Tenant $tenant,
        User $recordedBy,
        SubscriptionChangeRequest $changeRequest,
        string $source,
        ?string $comment = null,
        ?string $proofPath = null,
    ): SubscriptionConsent {
        $source = in_array($source, [
            SubscriptionConsent::SOURCE_ADMIN, SubscriptionConsent::SOURCE_EMAIL,
            SubscriptionConsent::SOURCE_PHONE, SubscriptionConsent::SOURCE_DOCUMENT,
            SubscriptionConsent::SOURCE_OTHER,
        ], true) ? $source : SubscriptionConsent::SOURCE_ADMIN;

        return SubscriptionConsent::create([
            'tenant_id'           => $tenant->id,
            'user_id'             => null, // accord hors plateforme
            'action_type'         => SubscriptionConsent::ACTION_PLAN_CHANGE,
            'consent_text'        => self::PLAN_CHANGE_TEXT,
            'consent_version'     => self::PLAN_CHANGE_VERSION,
            'accepted_at'         => now(),
            'source'              => $source,
            'related_entity_type' => SubscriptionChangeRequest::class,
            'related_entity_id'   => $changeRequest->id,
            'comment'             => $comment,
            'proof_path'          => $proofPath,
            'created_by'          => $recordedBy->id,
        ]);
    }
}
