<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Services\EmailChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Endpoints for the authenticated user's own profile.
 *
 * GET  /api/auth/me            → AuthController::me()  (unchanged)
 * PATCH /api/me/profile        → update name / email
 * POST  /api/me/password       → change password (requires current password)
 */
class UserProfileController extends Controller
{
    /**
     * PATCH /api/me/profile
     * Update the authenticated user's display name and/or email address.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        // Le nom s'applique immédiatement.
        if ($request->has('name')) {
            $user->name = $request->input('name');
        }
        $user->save();

        // RC-11 F-6 — un changement d'email n'est PAS appliqué directement : il exige une confirmation
        // par code envoyé à la NOUVELLE adresse (anti-usurpation / faute de frappe). Le nom reste appliqué.
        $emailVerificationRequired = false;
        $pendingEmail = null;
        if ($request->filled('email')) {
            $newEmail = strtolower(trim((string) $request->input('email')));
            if ($newEmail !== strtolower((string) $user->email)) {
                app(EmailChangeService::class)->request($user, $newEmail);
                $emailVerificationRequired = true;
                $pendingEmail = $newEmail;
            }
        }

        return response()->json([
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email, // inchangé tant que non confirmé
            ],
            'email_verification_required' => $emailVerificationRequired,
            'pending_email'               => $pendingEmail,
            'message' => $emailVerificationRequired
                ? 'Profil mis à jour. Un code de confirmation a été envoyé à votre nouvelle adresse.'
                : 'Profil mis à jour.',
        ]);
    }

    /** POST /api/me/email/verify — {code} → applique le nouvel email après vérification (RC-11 F-6). */
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $result = app(EmailChangeService::class)->confirm($request->user(), $data['code']);

        if (! $result['ok']) {
            $msg = ($result['reason'] ?? '') === 'taken'
                ? 'Cette adresse email est déjà utilisée.'
                : 'Code invalide ou expiré.';

            return response()->json(['message' => $msg], 422);
        }

        return response()->json([
            'data'    => ['email' => $result['email']],
            'message' => 'Adresse email mise à jour.',
        ]);
    }

    /**
     * POST /api/me/password
     * Change the authenticated user's password.
     * Requires the current password for verification.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors'  => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        // Revoke all other tokens so existing sessions are invalidated
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Mot de passe modifié. Les autres sessions ont été révoquées.']);
    }

    /**
     * GET /api/me/sessions
     * List the authenticated user's active API tokens.
     */
    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'last_used_at' => $t->last_used_at?->toISOString(),
                'created_at'   => $t->created_at->toISOString(),
                'is_current'   => $t->id === $request->user()->currentAccessToken()->id,
            ]);

        return response()->json(['data' => $tokens]);
    }

    /**
     * DELETE /api/me/sessions/{tokenId}
     * Revoke a specific session / token.
     */
    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        if ($current->id === $tokenId) {
            return response()->json(['message' => 'Impossible de révoquer la session courante.'], 422);
        }

        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json(['message' => 'Session révoquée.']);
    }
}
