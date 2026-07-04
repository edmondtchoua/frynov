<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-6A — administration des notifications par tenant : canaux (SMTP / proxy API / log), modèles
 * (surcharge des globaux), journal d'envoi, test de canal.
 */
class NotificationAdminController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    // ── Canaux ──────────────────────────────────────────────────────────────

    /** GET /api/notifications/channels */
    public function channels(Request $request): JsonResponse
    {
        $channels = NotificationChannel::where('tenant_id', $request->user()->tenant_id)
            ->latest()->get()->map(fn (NotificationChannel $c) => $c->toApiArray());

        return response()->json(['data' => $channels]);
    }

    /** POST /api/notifications/channels */
    public function storeChannel(Request $request): JsonResponse
    {
        $data = $this->validateChannel($request);

        $channel = NotificationChannel::create(array_merge($data, [
            'tenant_id' => $request->user()->tenant_id,
        ]));
        $this->ensureSingleDefault($channel);

        return response()->json(['data' => $channel->fresh()->toApiArray()], 201);
    }

    /** PATCH /api/notifications/channels/{id} */
    public function updateChannel(Request $request, string $id): JsonResponse
    {
        $channel = $this->findChannel($request, $id);
        if (! $channel) {
            return response()->json(['message' => 'Canal introuvable.'], 404);
        }

        $data = $this->validateChannel($request, partial: true);

        // Fusion de config : les clés envoyées écrasent, les secrets absents sont CONSERVÉS
        // (la SPA ne renvoie jamais les secrets existants).
        if (array_key_exists('config', $data)) {
            $data['config'] = array_merge($channel->config ?? [], $data['config'] ?? []);
        }

        $channel->update($data);
        $this->ensureSingleDefault($channel);

        return response()->json(['data' => $channel->fresh()->toApiArray()]);
    }

    /** DELETE /api/notifications/channels/{id} */
    public function destroyChannel(Request $request, string $id): JsonResponse
    {
        $channel = $this->findChannel($request, $id);
        if (! $channel) {
            return response()->json(['message' => 'Canal introuvable.'], 404);
        }

        $channel->delete();

        return response()->json(['message' => 'Canal supprimé.']);
    }

    /** POST /api/notifications/channels/{id}/test — envoi immédiat de vérification. */
    public function testChannel(Request $request, string $id): JsonResponse
    {
        $channel = $this->findChannel($request, $id);
        if (! $channel) {
            return response()->json(['message' => 'Canal introuvable.'], 404);
        }

        $request->validate(['recipient' => ['required', 'string', 'max:190']]);

        try {
            $this->notifications->sendTest($channel, $request->input('recipient'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Échec du test : ' . $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Message de test envoyé.']);
    }

    // ── Modèles ─────────────────────────────────────────────────────────────

    /** GET /api/notifications/templates — fusion globaux + surcharges du tenant. */
    public function templates(Request $request): JsonResponse
    {
        $tenantId  = $request->user()->tenant_id;
        $templates = NotificationTemplate::where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderBy('code')->orderByRaw('tenant_id IS NULL') // surcharge avant global
            ->get()
            ->unique(fn ($t) => "{$t->code}|{$t->channel}|{$t->locale}")
            ->values()
            ->map(fn (NotificationTemplate $t) => $t->toApiArray());

        return response()->json(['data' => $templates]);
    }

    /** PUT /api/notifications/templates — crée/actualise la SURCHARGE tenant d'un modèle. */
    public function upsertTemplate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'code'      => ['required', 'string', 'max:64'],
            'channel'   => ['required', Rule::in(NotificationChannel::CHANNELS)],
            'locale'    => ['nullable', 'string', 'max:5'],
            'subject'   => ['nullable', 'string', 'max:190'],
            'body'      => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = NotificationTemplate::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'code'      => $data['code'],
                'channel'   => $data['channel'],
                'locale'    => $data['locale'] ?? 'fr',
            ],
            [
                'subject'   => $data['subject'] ?? null,
                'body'      => $data['body'],
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return response()->json(['data' => $template->toApiArray()]);
    }

    // ── Journal ─────────────────────────────────────────────────────────────

    /** GET /api/notifications/outbox */
    public function outbox(Request $request): JsonResponse
    {
        $items = NotificationOutbox::where('tenant_id', $request->user()->tenant_id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(30);

        $items->getCollection()->transform(fn (NotificationOutbox $o) => $o->toApiArray());

        return response()->json($items);
    }

    // ── Privé ───────────────────────────────────────────────────────────────

    private function findChannel(Request $request, string $id): ?NotificationChannel
    {
        return NotificationChannel::where('tenant_id', $request->user()->tenant_id)
            ->where('id', $id)->first();
    }

    private function validateChannel(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'channel'      => [$req, Rule::in(NotificationChannel::CHANNELS)],
            'provider'     => [$req, Rule::in(NotificationChannel::PROVIDERS)],
            'name'         => [$req, 'string', 'max:120'],
            'config'       => ['nullable', 'array'],
            'from_name'    => ['nullable', 'string', 'max:120'],
            'from_address' => ['nullable', 'string', 'max:190'],
            'is_active'    => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
        ]);
    }

    /** Un seul canal par défaut par type : poser is_default retire le flag des autres. */
    private function ensureSingleDefault(NotificationChannel $channel): void
    {
        if ($channel->fresh()->is_default) {
            NotificationChannel::withoutTenantScope()
                ->where('tenant_id', $channel->tenant_id)
                ->where('channel', $channel->channel)
                ->where('id', '!=', $channel->id)
                ->update(['is_default' => false]);
        }
    }
}
