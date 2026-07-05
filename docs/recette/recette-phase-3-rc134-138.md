# Recette QA — Phase 3 (rc.134 → rc.138)

> **Date :** 2026-06-30 · **Périmètre :** RC-7B (dette vue-tsc), RC-7C (comptes clients portail),
> RC-7D (accès digital par exemplaire), RC-7E (crédits de communication), RC-7F (webhook Mobile Money).
> **Méthode :** deux revues indépendantes en lecture seule — audit **sécurité** (multi-tenant,
> auth, IDOR, webhook) et revue **adverse** (logique métier) — puis vérification de chaque finding
> dans le code, correctifs avec tests de régression. **Verdict : GO après correctifs (rc.139).**

## Correctifs livrés (rc.139)

### Sécurité

| # | Sévérité | Sujet | Correctif |
|---|----------|-------|-----------|
| SEC-1 | **Critique** | Un token de compte portail (même guard `sanctum` que les users) pouvait atteindre `PATCH /api/me/profile` (route `auth:sanctum` **sans** `tenant`) et **changer son email sans re-vérification** → vol des achats digitaux d'autrui via `my-purchases`. | Middleware **global** `GuardPortalPrincipal` : un token portail ne sert QUE `api/portal/*` (403 ailleurs). Résolution directe du jeton (`PersonalAccessToken::findToken`) — couvre aussi les routes de modules chargées hors groupe `api`. Token portail émis avec l'ability `portal`. |
| SEC-2 | **Haute** | `TenantScope` **fail-open** : un principal authentifié sans `tenant_id` (compte portail) désactivait tout filtrage → fuite cross-tenant potentielle sur toute requête `HasTenant` non scopée explicitement ; `EnsureTenantHasModule` laissait aussi passer. | `TenantScope` **fail-closed** : principal authentifié non super-admin sans tenant → sentinelle impossible (zéro résultat). `EnsureTenantHasModule` refuse (403) un principal sans tenant. |
| SEC-3 | Moyenne | `register` re-spammait un code à chaque appel (bombardement email + drain des crédits du vendeur). | Pas de nouveau code tant qu'un code non expiré est valide (le mot de passe est tout de même mis à jour). |
| SEC-4 | Moyenne | Code à 6 chiffres sans limite de tentatives (brute-force sur 30 min). | Compteur `verification_attempts` : au-delà de `MAX_VERIFY_ATTEMPTS` (5), le code est **brûlé**. Comparaison `hash_equals`. |
| SEC-6 | Moyenne | Webhook Mobile Money : montant absent (mauvais `field_map`) → **crédit sans vérification**. | Montant **obligatoire** et correspondant, sinon `needs_review` (fail-closed). |

### Logique métier

| # | Sévérité | Sujet | Correctif |
|---|----------|-------|-----------|
| AR-1 | **Haute** | Pré-hijack de compte portail : `register` écrasait le mot de passe d'un compte non vérifié et `verify` n'exigeait pas le mot de passe → un tiers pouvait faire vérifier son mot de passe par la victime. | `verify` exige désormais le **mot de passe** (lie l'activation à qui l'a défini). |
| AR-2 | Moyenne | Révocation digitale au prorata comptait les retours **approuvés non restockés** → sur-révocation, irréversible si le retour est ensuite rejeté. | Ne compter que les retours **restockés** + le retour en cours. |
| AR/E | Moyenne | Statut outbox `no_credit` **terminal** : messages perdus même après recharge (or `email` est facturé par défaut → tous les emails d'un tenant neuf mouraient). | À la recharge, les envois `no_credit` du canal repassent en `pending`. |
| AR-7 | Moyenne | Commande de recharge `needs_review` : cul-de-sac inclosable + créditable par un 2ᵉ webhook → **double crédit** avec une recharge manuelle. | `needs_review` **non** auto-crédité (action opérateur requise) et **annulable**. |
| AR/F | Moyenne | XOF sans sous-unité : les fournisseurs envoient l'unité majeure → tout paiement réel partait en `needs_review`. | Facteur `amount_scale` configurable (défaut 1). |
| AR | Basse | `my-purchases` listait les accès **expirés** par date. | Filtre `expires_at` (cohérent avec `isAccessible`). |
| AR | Basse | `revokeDownToActive` révoquait les exemplaires les plus anciens (probablement déjà activés). | Révoque les plus **récents** d'abord (garde les anciens). |
| AR | Basse | `credit()` acceptait un montant négatif (invariant « jamais sous zéro » cassable). | Clamp à 0, mouvement journalisant la variation réelle. |
| AR (front) | Basse | Annulation d'une commande MoMo : échec silencieux sans rafraîchissement ; écran « code envoyé » sans issue si compte déjà vérifié. | Rechargement systématique après annulation ; lien « Déjà inscrit ? Se connecter ». |

## Tests de régression ajoutés

- `PortalSecurityTest` (5) : token portail refusé hors portail (SEC-1), vérification exige le mot de
  passe (AR-1), code brûlé après N tentatives (SEC-4), pas de re-envoi tant que le code est valide (SEC-3).
- `CommunicationCreditTest` (+2) : réarmement des `no_credit` à la recharge ; ajustement négatif clampé.
- `RechargeWebhookTest` (+4) : montant absent → `needs_review` ; `needs_review` non auto-crédité ;
  `needs_review` annulable ; `amount_scale` (unité majeure).
- `ReturnVoidSpecialTest` (+1) : retour approuvé non restocké ne sur-révoque pas.

## Vérifié sans problème (extraits)

Idempotence de l'émission par exemplaire (comptage inclut les révoqués, pas de doublon) ; blocage
métier `no_credit`/remboursement sur échec ; signature HMAC temps constant + secret absent → 503 ;
replay webhook idempotent (verrou de ligne) ; IDOR recharges/mouvements/soldes scopés tenant ;
énumération de comptes (réponses génériques) ; routes tenant rejettent déjà les tokens portail (400).

## Points de suite (hors périmètre correctif)

- Sur-retour (quantité retournée > vendue) non plafonné à `create`/`approve` — antérieur à la Phase 3.
- Token portail sans expiration ni révocation serveur au `logout` (localStorage) — durcissement futur
  (expiration Sanctum + `logout` révoquant `currentAccessToken`).
- Normalisation de la casse des emails `Customer` à l'écriture (impact PostgreSQL).
