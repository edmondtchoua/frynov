<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Tenants\Models\Tenant;

/**
 * RC-5J — renouvellement & relance (dunning) des abonnements, exécuté quotidiennement par
 * `billing:process-renewals`. Les paiements étant MANUELS (mobile money approuvé par l'admin), il n'y a
 * pas de prélèvement automatique : le job rappelle, dégrade, puis suspend.
 *
 *  1. RAPPELS  — abonnements payants actifs arrivant à échéance (J-7 / J-3 / J-1) : trace un rappel
 *     idempotent dans `metadata['renewal_reminders']` + audit `billing.renewal_reminder`.
 *  2. ÉCHÉANCE — `active|trialing` dont la période est finie :
 *       - plan **gratuit** → la période ROULE d'un intervalle (rien à payer), statut `active` ;
 *       - plan **payant** → `past_due` (l'accès reste ouvert pendant la grâce, reste dû tracé).
 *  3. GRÂCE    — `past_due` dont la période est finie depuis plus de GRACE_DAYS → `suspended`
 *     (raison `renewal_overdue`). Les `past_due` d'acompte échelonné (période jamais démarrée,
 *     `current_period_end` null) ne sont JAMAIS suspendus par ce job.
 */
class RenewalService
{
    public const GRACE_DAYS     = 7;
    public const REMINDER_DAYS  = [7, 3, 1];

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array{reminders:int,past_due:int,rolled:int,suspended:int} */
    public function processRenewals(): array
    {
        $summary = [
            'reminders' => $this->sendReminders(),
            ...$this->expirePeriods(),
            'suspended' => $this->suspendOverdue(),
        ];

        return $summary;
    }

    // ── 1. Rappels avant échéance ───────────────────────────────────────────

    private function sendReminders(): int
    {
        $count = 0;

        $upcoming = Subscription::withoutTenantScope()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), now()->addDays(max(self::REMINDER_DAYS))])
            ->with('plan')
            ->get();

        foreach ($upcoming as $sub) {
            if ($this->isFreePlan($sub)) {
                continue; // rien à payer → pas de relance
            }

            $daysLeft = (int) ceil(now()->diffInDays($sub->current_period_end, false));
            // Bucket de rappel le plus PRÉCIS : plus petit seuil couvrant les jours restants
            // (J-2 → bucket 3, pas 7). Un bucket émis n'est jamais réémis pour la même période.
            $bucket = collect(self::REMINDER_DAYS)->sort()->first(fn (int $d) => $daysLeft <= $d);
            if ($bucket === null) {
                continue;
            }

            $sent = $sub->metadata['renewal_reminders'] ?? [];
            if (in_array($bucket, $sent, true)) {
                continue; // idempotent : ce bucket a déjà été rappelé pour cette période
            }

            $sub->update(['metadata' => array_merge($sub->metadata ?? [], [
                'renewal_reminders' => [...$sent, $bucket],
            ])]);

            $this->audit->log(
                action: 'billing.renewal_reminder',
                tenantId: $sub->tenant_id,
                userId: null,
                subject: $sub,
                newValues: ['days_left' => $daysLeft, 'bucket' => $bucket, 'period_end' => $sub->current_period_end?->toISOString()],
            );
            // RC-6A — email de relance (best-effort : sans canal configuré, seul l'audit trace).
            $this->notifyBilling($sub, 'billing.renewal_reminder', ['days_left' => $daysLeft]);
            $count++;
        }

        return $count;
    }

    // ── 2. Échéance dépassée ────────────────────────────────────────────────

    /** @return array{past_due:int,rolled:int,scheduled:int} */
    private function expirePeriods(): array
    {
        $pastDue   = 0;
        $rolled    = 0;
        $scheduled = 0;

        $expired = Subscription::withoutTenantScope()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->with('plan')
            ->get();

        foreach ($expired as $sub) {
            // P4 — changement DIFFÉRÉ planifié : on l'applique à l'échéance (prioritaire sur roll/past_due).
            if (! empty($sub->metadata['scheduled_change'])) {
                if ($this->applyScheduledChange($sub, $sub->metadata['scheduled_change'])) {
                    $scheduled++;
                    continue;
                }
            }

            if ($this->isFreePlan($sub)) {
                // Plan gratuit : la période roule d'un intervalle, l'accès continue.
                $end = $sub->current_period_end;
                $sub->update([
                    'status'               => Subscription::STATUS_ACTIVE,
                    'current_period_start' => $end,
                    'current_period_end'   => $sub->interval === Subscription::INTERVAL_YEARLY
                        ? $end->copy()->addYear()
                        : $end->copy()->addMonth(),
                    // Nouvelle période → les rappels repartent de zéro.
                    'metadata'             => array_merge($sub->metadata ?? [], ['renewal_reminders' => []]),
                ]);
                $this->syncTenant($sub, Subscription::STATUS_ACTIVE);
                $rolled++;
                continue;
            }

            $sub->update(['status' => Subscription::STATUS_PAST_DUE]);
            $this->syncTenant($sub, Subscription::STATUS_PAST_DUE);
            $this->audit->log(
                action: 'billing.renewal_overdue',
                tenantId: $sub->tenant_id,
                userId: null,
                subject: $sub,
                newValues: ['period_end' => $sub->current_period_end?->toISOString(), 'grace_days' => self::GRACE_DAYS],
            );
            $this->notifyBilling($sub, 'billing.renewal_overdue'); // RC-6A
            $pastDue++;
        }

        return ['past_due' => $pastDue, 'rolled' => $rolled, 'scheduled' => $scheduled];
    }

    /**
     * P4 — applique à l'échéance un changement de plan DIFFÉRÉ (déjà payé + approuvé). Enchaîne la
     * nouvelle période à la fin du cycle courant et active la demande liée. Best-effort : un échec
     * n'interrompt pas le job (renvoie false → le cycle suit le traitement normal).
     */
    private function applyScheduledChange(Subscription $sub, array $scheduled): bool
    {
        try {
            $plan   = Plan::find($scheduled['plan_id'] ?? null);
            $tenant = Tenant::withoutGlobalScopes()->find($sub->tenant_id);
            if (! $plan || ! $tenant) {
                return false;
            }

            $admin = ! empty($scheduled['approved_by']) ? \App\Models\User::find($scheduled['approved_by']) : null;

            $newSub = $this->subscriptions->changePlan(
                $tenant,
                $plan,
                $admin, // approbateur d'origine → statut actif
                $scheduled['interval'] ?? Subscription::INTERVAL_MONTHLY,
                settle: true,
                periodStart: $sub->current_period_end, // enchaîne au cycle suivant
                periods: (int) ($scheduled['quantity'] ?? 1),
            );
            $newSub->update([
                'currency'          => $scheduled['currency'] ?? $newSub->currency,
                'market_code'       => $scheduled['market_code'] ?? $newSub->market_code,
                'amount_paid_minor' => (int) ($scheduled['amount_paid_minor'] ?? 0),
            ]);

            // Active la demande différée (approved → activated).
            if (! empty($scheduled['change_request_id'])) {
                $cr = SubscriptionChangeRequest::find($scheduled['change_request_id']);
                if ($cr && ! $cr->isTerminal()) {
                    $cr->update(['status' => SubscriptionChangeRequest::STATUS_ACTIVATED, 'activated_at' => now()]);
                }
            }

            $this->audit->log(
                action: 'billing.scheduled_change_applied',
                tenantId: $sub->tenant_id,
                userId: null,
                subject: $newSub,
                newValues: ['plan' => $plan->code, 'interval' => $scheduled['interval'] ?? null],
            );

            return true;
        } catch (\Throwable) {
            return false; // best-effort : on ne bloque jamais le renouvellement
        }
    }

    // ── 3. Grâce expirée → suspension ───────────────────────────────────────

    private function suspendOverdue(): int
    {
        $count = 0;

        $overdue = Subscription::withoutTenantScope()
            ->where('status', Subscription::STATUS_PAST_DUE)
            // Un acompte échelonné a une période JAMAIS démarrée (end null) → hors périmètre.
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now()->subDays(self::GRACE_DAYS))
            ->get();

        foreach ($overdue as $sub) {
            $tenant = Tenant::withoutGlobalScopes()->find($sub->tenant_id);
            if (! $tenant) {
                continue;
            }

            $this->subscriptions->suspend($tenant, 'renewal_overdue');
            $this->audit->log(
                action: 'billing.renewal_suspended',
                tenantId: $sub->tenant_id,
                userId: null,
                subject: $sub,
                oldValues: ['status' => Subscription::STATUS_PAST_DUE],
                newValues: ['status' => Subscription::STATUS_SUSPENDED, 'reason' => 'renewal_overdue'],
            );
            $this->notifyBilling($sub, 'billing.renewal_suspended'); // RC-6A
            $count++;
        }

        return $count;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Le plan ne coûte rien pour la périodicité de l'abonnement (starter gratuit…). */
    private function isFreePlan(Subscription $sub): bool
    {
        $plan = $sub->plan ?? Plan::find($sub->plan_id);
        if (! $plan) {
            return false;
        }

        $interval = $sub->interval === Subscription::INTERVAL_YEARLY ? 'yearly' : 'monthly';

        // RC-18 (M-4) — prix LOCALISÉ d'abord : le marché de l'abonnement (sinon le marché canonique
        // de sa devise, sinon 'global') fait foi. Les colonnes legacy ne servent que de repli — les
        // lire en premier classait mal les plans à grille PlanPrice (facturé à tort / jamais facturé).
        $market = $sub->market_code
            ?: ($sub->currency ? \App\Modules\Billing\Support\Markets::canonicalForCurrency($sub->currency) : null)
            ?: 'global';

        $localized = $plan->priceForMarket($market, $interval);
        if ($localized !== null) {
            return (int) $localized->base_amount_minor === 0;
        }

        $price = $interval === 'yearly'
            ? (int) $plan->price_yearly_cents
            : (int) $plan->price_monthly_cents;

        return $price === 0;
    }

    private function syncTenant(Subscription $sub, string $status): void
    {
        Tenant::withoutGlobalScopes()
            ->where('id', $sub->tenant_id)
            ->update(['subscription_status' => $status]);
    }

    /**
     * RC-6A — email billing du tenant : `settings['billing_email']` sinon l'email du premier
     * utilisateur. Émission best-effort (jamais bloquante pour le job).
     */
    private function notifyBilling(Subscription $sub, string $templateCode, array $extra = []): void
    {
        try {
            $tenant = Tenant::withoutGlobalScopes()->find($sub->tenant_id);
            if (! $tenant) {
                return;
            }

            $recipient = (string) ($tenant->settings['billing_email']
                ?? \App\Models\User::where('tenant_id', $tenant->id)->orderBy('created_at')->value('email')
                ?? '');
            if ($recipient === '') {
                return;
            }

            $plan = $sub->plan ?? Plan::find($sub->plan_id);

            $this->notifications->notify($tenant->id, $templateCode, $recipient, array_merge([
                'tenant_name' => $tenant->name,
                'plan'        => $plan?->name ?? $tenant->plan,
                'period_end'  => $sub->current_period_end?->format('d/m/Y') ?? '',
                'grace_days'  => self::GRACE_DAYS,
            ], $extra));
        } catch (\Throwable) {
            // best-effort : l'échec de notification n'interrompt jamais le dunning
        }
    }
}
