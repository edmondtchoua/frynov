<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Digital\Models\PortalAccount;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * RC-7C / recette QA — cloisonnement des comptes clients du portail.
 *
 * Un `PortalAccount` s'authentifie sur le MÊME guard `sanctum` que les utilisateurs de l'espace de
 * travail. Son token ne doit servir QUE les routes publiques du portail (`api/portal/*`). Sur toute
 * autre route, on refuse (403) — sinon un token portail atteindrait des routes `auth:sanctum`
 * dépourvues du middleware `tenant` (ex. `PATCH api/me/profile`, Import/Export) et pourrait changer
 * son email ou lire des données au-delà du portail, contournant la vérification par code de RC-7C.
 *
 * Appliqué globalement au groupe `api` (bootstrap/app.php) : couvre toutes les routes présentes et
 * futures, sans énumération. Un token d'utilisateur tenant n'est jamais impacté.
 */
class GuardPortalPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        // N'inspecter que les requêtes porteuses d'un jeton, hors préfixe portail légitime. On résout
        // le jeton DIRECTEMENT (findToken) plutôt que via le guard `sanctum` : ce middleware de groupe
        // s'exécute avant `auth:sanctum`, et la résolution directe est déterministe.
        $bearer = $request->bearerToken();
        if ($bearer && ! $request->is('api/portal', 'api/portal/*')) {
            $token = PersonalAccessToken::findToken($bearer);
            if ($token && $token->tokenable instanceof PortalAccount) {
                return response()->json(['message' => 'Jeton non autorisé pour cette ressource.'], 403);
            }
        }

        return $next($request);
    }
}
