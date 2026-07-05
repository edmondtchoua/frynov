<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\TwoFactorService;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-13 F-4 — 2FA par code email : vérification du second facteur (public) et activation/désactivation
 * par l'utilisateur (authentifié).
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuthService $auth,
        private readonly AuditService $audit,
    ) {}

    /** POST /api/auth/2fa/verify — {email, code} → délivre le token si le code est valide. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code'  => ['required', 'string', 'max:10'],
        ]);

        $tenantId = $request->input('tenant_id') ?? $request->attributes->get('tenant')?->id;
        $user = $this->twoFactor->verify($data['email'], $data['code'], $tenantId);

        if (! $user) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        if ($user->tenant_id) {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        }
        try {
            $this->audit->log('auth.login', $user->tenant_id, $user->id, $user, [],
                ['email' => $user->email, 'via' => '2fa'], null,
                $user->getRoleNames()->first() ?? 'user', $request->ip(), $request->userAgent());
        } catch (\Throwable) {}

        return response()->json([
            'token' => $this->auth->issueTokenFor($user),
            'user'  => new UserResource($user),
        ]);
    }

    /** POST /api/me/2fa — {enabled: bool} → active/désactive la 2FA de l'utilisateur connecté. */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $request->user()->update(['two_factor_enabled' => $data['enabled']]);

        return response()->json([
            'data'    => ['two_factor_enabled' => (bool) $data['enabled']],
            'message' => $data['enabled'] ? 'Double authentification activée.' : 'Double authentification désactivée.',
        ]);
    }
}
