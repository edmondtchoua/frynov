<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\Password;

/**
 * RC-12 F-5 — acceptation d'une invitation d'équipe (public, throttlé) : le membre pose son mot de
 * passe avec le code reçu par email.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /** POST /api/auth/accept-invitation — {email, code, password}. */
    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'code'     => ['required', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $ok = $this->invitations->accept($data['email'], $data['code'], $data['password']);

        if (! $ok) {
            return response()->json(['message' => 'Invitation invalide ou expirée.'], 422);
        }

        return response()->json(['message' => 'Compte activé — vous pouvez vous connecter.']);
    }
}
