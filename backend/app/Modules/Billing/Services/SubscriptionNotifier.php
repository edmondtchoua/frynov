<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Models\SubscriptionNotification;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tenants\Models\Tenant;

/**
 * P2 — notifications du cycle de vie d'une demande de changement de plan. RÉUTILISE l'outbox existant
 * (`NotificationService::notify` → `notification_outbox`, historisé, expédié par cron). Best-effort :
 * une notification qui échoue n'interrompt JAMAIS le flux métier (submit/approbation).
 *
 * Modèles globaux attendus (locale fr, canal email) : `billing.subscription_submitted`,
 * `billing.subscription_activated`, `billing.subscription_rejected`, `billing.subscription_admin_new`.
 */
class SubscriptionNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Demande soumise → accusé au tenant + alerte aux admins internes. */
    public function submitted(SubscriptionChangeRequest $request): void
    {
        $this->safe(function () use ($request) {
            $data = $this->payload($request);

            $this->toTenant($request, 'billing.subscription_submitted', $data);
            $this->inApp($request, 'submitted', 'info', 'Demande de changement de plan reçue',
                "Votre passage au plan {$data['new_plan']} est en attente de validation.");

            foreach ($this->superAdminEmails() as $email) {
                $this->notifications->notify($request->tenant_id, 'billing.subscription_admin_new', $email, $data);
            }
        });
    }

    /** Plan activé → confirmation au tenant. */
    public function activated(SubscriptionChangeRequest $request): void
    {
        $this->safe(function () use ($request) {
            $data = $this->payload($request);
            $this->toTenant($request, 'billing.subscription_activated', $data);
            $this->inApp($request, 'activated', 'info', 'Nouveau plan activé',
                "Votre plan {$data['new_plan']} est désormais actif.");
        });
    }

    /** Demande refusée → information au tenant (avec motif). */
    public function rejected(SubscriptionChangeRequest $request): void
    {
        $this->safe(function () use ($request) {
            $data = $this->payload($request);
            $this->toTenant($request, 'billing.subscription_rejected', $data);
            $this->inApp($request, 'rejected', 'warning', 'Demande de changement de plan refusée',
                $data['reason'] !== '' ? "Motif : {$data['reason']}" : null);
        });
    }

    /** Demande de correction (admin) → information au tenant (avec la consigne). */
    public function correctionRequested(SubscriptionChangeRequest $request, string $reason): void
    {
        $this->safe(function () use ($request, $reason) {
            $data = array_merge($this->payload($request), ['reason' => $reason]);
            $this->toTenant($request, 'billing.subscription_correction', $data);
            $this->inApp($request, 'correction', 'warning', 'Correction demandée sur votre demande de plan',
                "Consigne : {$reason}");
        });
    }

    /** P2b — dépose une notification IN-APP (cloche) pour le tenant. tenant_id explicite (contexte admin). */
    private function inApp(SubscriptionChangeRequest $request, string $type, string $severity, string $title, ?string $body): void
    {
        SubscriptionNotification::create([
            'tenant_id'         => $request->tenant_id,
            'change_request_id' => $request->id,
            'type'              => $type,
            'severity'          => $severity,
            'title'             => $title,
            'body'              => $body,
        ]);
    }

    // ── Interne ────────────────────────────────────────────────────────────────

    private function toTenant(SubscriptionChangeRequest $request, string $template, array $data): void
    {
        $recipient = $this->tenantRecipient($request->tenant);
        if ($recipient !== '') {
            $this->notifications->notify($request->tenant_id, $template, $recipient, $data, NotificationChannel::CHANNEL_EMAIL);
        }
    }

    /** @return array<string,string> Placeholders du modèle. */
    private function payload(SubscriptionChangeRequest $request): array
    {
        $snapshot = $request->plan_snapshot ?? [];

        return [
            'tenant_name' => (string) ($request->tenant?->name ?? ''),
            'old_plan'    => (string) ($snapshot['from_plan_code'] ?? ''),
            'new_plan'    => (string) ($snapshot['plan_name'] ?? $snapshot['plan_code'] ?? ''),
            'interval'    => (string) $request->interval,
            'quantity'    => (string) $request->quantity,
            'amount'      => number_format($request->net_payable_minor / 100, 0, ',', ' ').' '.$request->currency,
            'status'      => (string) $request->status,
            'reason'      => (string) ($request->rejection_reason ?? ''),
        ];
    }

    /** E-mail de facturation du tenant, sinon premier utilisateur (mirroir de RenewalService). */
    private function tenantRecipient(?Tenant $tenant): string
    {
        if (! $tenant) {
            return '';
        }

        return (string) ($tenant->settings['billing_email']
            ?? User::where('tenant_id', $tenant->id)->orderBy('created_at')->value('email')
            ?? '');
    }

    /** @return array<int,string> */
    private function superAdminEmails(): array
    {
        return User::where('is_super_admin', true)->pluck('email')->filter()->values()->all();
    }

    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable) {
            // best-effort : jamais bloquant.
        }
    }
}
