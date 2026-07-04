<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Models\NotificationTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * RC-6A — cœur des notifications sortantes.
 *
 * `notify()` : résout le canal par défaut actif du tenant + le modèle (surcharge tenant → global),
 * rend les `{{placeholders}}` et dépose en OUTBOX (pending). **Best-effort** : sans canal actif ou
 * sans modèle, on n'interrompt JAMAIS le flux métier appelant (retour null).
 *
 * `flush()` : expédie l'outbox (cron), retry borné (3), via le transport du canal :
 *   - `smtp`     → mailer à la volée (config chiffrée du canal) ;
 *   - `http_api` → POST générique vers un proxy/agrégateur (URL + headers + payload template, tous
 *                  dynamiques — couvre SMS, WhatsApp Business, passerelles email HTTP) ;
 *   - `log`      → journal applicatif (dev / recette).
 */
class NotificationService
{
    // ── Émission ────────────────────────────────────────────────────────────

    public function notify(
        string $tenantId,
        string $templateCode,
        string $recipient,
        array $data = [],
        string $channelType = NotificationChannel::CHANNEL_EMAIL,
    ): ?NotificationOutbox {
        if ($recipient === '') {
            return null;
        }

        $channel = $this->defaultChannel($tenantId, $channelType);
        if (! $channel) {
            return null; // aucun canal actif : best-effort, le flux métier continue
        }

        $template = $this->resolveTemplate($tenantId, $templateCode, $channelType);
        if (! $template) {
            return null;
        }

        return NotificationOutbox::create([
            'tenant_id'     => $tenantId,
            'channel_id'    => $channel->id,
            'channel'       => $channelType,
            'template_code' => $templateCode,
            'recipient'     => $recipient,
            'subject'       => $template->subject ? $this->render($template->subject, $data) : null,
            'body'          => $this->render($template->body, $data),
            'status'        => NotificationOutbox::STATUS_PENDING,
        ]);
    }

    /** Canal par défaut actif d'un type (is_default d'abord, sinon le plus récent actif). */
    public function defaultChannel(string $tenantId, string $channelType): ?NotificationChannel
    {
        return NotificationChannel::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channelType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->latest()
            ->first();
    }

    /** Modèle applicable : surcharge tenant sinon modèle global (tenant_id null). */
    public function resolveTemplate(string $tenantId, string $code, string $channelType, string $locale = 'fr'): ?NotificationTemplate
    {
        return NotificationTemplate::where('code', $code)
            ->where('channel', $channelType)
            ->where('locale', $locale)
            ->where('is_active', true)
            // NB : un IN (…, NULL) ne matche jamais NULL — orWhereNull obligatoire pour les globaux.
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderByRaw('tenant_id IS NULL') // tenant (0) avant global (1)
            ->first();
    }

    /** Rendu `{{placeholder}}` — les clés absentes deviennent une chaîne vide. */
    public function render(string $text, array $data): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn (array $m) => (string) ($data[$m[1]] ?? ''),
            $text,
        );
    }

    // ── Expédition (cron) ───────────────────────────────────────────────────

    /** @return array{sent:int,failed:int} */
    public function flush(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;

        $batch = NotificationOutbox::withoutTenantScope()
            ->where('status', NotificationOutbox::STATUS_PENDING)
            ->where('attempts', '<', NotificationOutbox::MAX_ATTEMPTS)
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($batch as $item) {
            $channel = NotificationChannel::withoutTenantScope()->find($item->channel_id);

            try {
                if (! $channel || ! $channel->is_active) {
                    throw new \RuntimeException('Canal indisponible ou désactivé.');
                }
                $this->deliver($channel, $item);
                $item->update([
                    'status'   => NotificationOutbox::STATUS_SENT,
                    'attempts' => $item->attempts + 1,
                    'sent_at'  => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $attempts = $item->attempts + 1;
                $item->update([
                    'attempts'   => $attempts,
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                    // Échec définitif après MAX_ATTEMPTS, sinon repassera au prochain flush.
                    'status'     => $attempts >= NotificationOutbox::MAX_ATTEMPTS
                        ? NotificationOutbox::STATUS_FAILED
                        : NotificationOutbox::STATUS_PENDING,
                ]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Envoi de test immédiat sur un canal (bouton « Tester » de la SPA). @throws \Throwable */
    public function sendTest(NotificationChannel $channel, string $recipient): void
    {
        $probe = new NotificationOutbox([
            'recipient' => $recipient,
            'subject'   => 'Test Frynov — canal ' . $channel->name,
            'body'      => "Ceci est un message de test du canal « {$channel->name} ». Si vous le recevez, la configuration est opérationnelle.",
        ]);

        $this->deliver($channel, $probe);
    }

    // ── Transports ──────────────────────────────────────────────────────────

    private function deliver(NotificationChannel $channel, NotificationOutbox $item): void
    {
        match ($channel->provider) {
            NotificationChannel::PROVIDER_SMTP     => $this->deliverSmtp($channel, $item),
            NotificationChannel::PROVIDER_HTTP_API => $this->deliverHttpApi($channel, $item),
            NotificationChannel::PROVIDER_LOG      => $this->deliverLog($channel, $item),
            default => throw new \RuntimeException("Provider inconnu : {$channel->provider}"),
        };
    }

    /** Mailer construit à la volée depuis la config chiffrée du canal (host/port/username/password/encryption). */
    private function deliverSmtp(NotificationChannel $channel, NotificationOutbox $item): void
    {
        $cfg = $channel->config ?? [];

        // Recette QA — config incomplète (host manquant après rotation de clé, etc.) : échec explicite
        // et immédiat, visible dans `last_error` du journal, plutôt qu'une tentative SMTP absurde.
        if (empty($cfg['host'])) {
            throw new \RuntimeException('Canal SMTP sans hôte configuré.');
        }

        $mailer = Mail::build([
            'transport'  => 'smtp',
            'host'       => $cfg['host'] ?? '',
            'port'       => (int) ($cfg['port'] ?? 587),
            'username'   => $cfg['username'] ?? null,
            'password'   => $cfg['password'] ?? null,
            'encryption' => $cfg['encryption'] ?? 'tls',
            'timeout'    => 15,
        ]);

        $mailer->raw($item->body, function ($message) use ($channel, $item) {
            $message->to($item->recipient)
                ->subject($item->subject ?? 'Notification')
                ->from($channel->from_address ?: 'no-reply@frynov.app', $channel->from_name ?: 'Frynov');
        });
    }

    /**
     * POST générique vers un proxy/agrégateur. Config : `url` (requis), `method` (POST), `headers`
     * (map), `payload` (map JSON dont les valeurs supportent {{to}}, {{message}}, {{subject}},
     * {{from}}, {{from_name}}) — assez dynamique pour brancher n'importe quel fournisseur sans code.
     */
    private function deliverHttpApi(NotificationChannel $channel, NotificationOutbox $item): void
    {
        $cfg = $channel->config ?? [];
        $url = (string) ($cfg['url'] ?? '');
        if ($url === '') {
            throw new \RuntimeException('Canal http_api sans URL.');
        }

        $vars = [
            'to'        => $item->recipient,
            'message'   => $item->body,
            'subject'   => (string) $item->subject,
            'from'      => (string) $channel->from_address,
            'from_name' => (string) $channel->from_name,
        ];

        $payload = collect($cfg['payload'] ?? ['to' => '{{to}}', 'message' => '{{message}}', 'from' => '{{from}}'])
            ->map(fn ($v) => is_string($v) ? $this->render($v, $vars) : $v)
            ->all();

        $response = Http::withHeaders((array) ($cfg['headers'] ?? []))
            ->timeout(15)
            ->send(strtoupper((string) ($cfg['method'] ?? 'POST')), $url, ['json' => $payload]);

        if ($response->failed()) {
            throw new \RuntimeException("Proxy API {$response->status()} : " . mb_substr($response->body(), 0, 300));
        }
    }

    private function deliverLog(NotificationChannel $channel, NotificationOutbox $item): void
    {
        Log::info('[notification]', [
            'channel'   => $channel->channel,
            'recipient' => $item->recipient,
            'subject'   => $item->subject,
            'body'      => $item->body,
        ]);
    }
}
