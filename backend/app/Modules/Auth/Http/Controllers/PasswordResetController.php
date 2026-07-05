<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\Password;

/**
 * RC-10 F-3 — endpoints publics de réinitialisation de mot de passe (par code email). Throttlés côté
 * routes ; réponse générique anti-énumération sur la demande.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $service) {}

    /** POST /api/auth/forgot-password — {email} → envoie un code (réponse générique). */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        $this->service->request($data['email']);

        return response()->json([
            'message' => 'Si un compte correspond à cet email, un code de réinitialisation vient d\'être envoyé.',
        ]);
    }

    /** POST /api/auth/reset-password — {email, code, password} → applique le nouveau mot de passe. */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'code'     => ['required', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $ok = $this->service->reset($data['email'], $data['code'], $data['password']);

        if (! $ok) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé — vous pouvez vous connecter.']);
    }
}
