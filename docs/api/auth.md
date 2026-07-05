# API — Authentification

Base URL : `https://{tenant}.etech.sn/api` ou `https://api.etech.sn/api` avec header `X-Tenant-Slug`.

---

## POST `/api/auth/login`

Connexion d'un utilisateur. Retourne un Bearer token.

### Requête

```http
POST /api/auth/login
Content-Type: application/json
X-Tenant-Slug: boutique-dakar
```

```json
{
  "email": "owner@boutique-dakar.sn",
  "password": "Secret123!"
}
```

### Réponse 200 — Succès

```json
{
  "token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
  "user": {
    "id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "name": "Propriétaire",
    "email": "owner@boutique-dakar.sn",
    "tenant_id": "a1b2c3d4-...",
    "created_at": "2026-05-30T10:00:00Z"
  }
}
```

### Réponses d'erreur

| Code | Raison |
|------|--------|
| `422` | Champs manquants ou invalides |
| `401` | Email ou mot de passe incorrect |
| `403` | Tenant inactif / suspendu |

---

## POST `/api/auth/register`

Inscription d'un nouvel utilisateur dans un tenant existant.

### Requête

```http
POST /api/auth/register
Content-Type: application/json
X-Tenant-Slug: boutique-dakar
```

```json
{
  "name": "Caissier Amadou",
  "email": "amadou@boutique-dakar.sn",
  "password": "MonMot2Passe!",
  "password_confirmation": "MonMot2Passe!"
}
```

### Réponse 201 — Créé

```json
{
  "token": "2|xyz...",
  "user": { "id": "...", "name": "Caissier Amadou", ... }
}
```

### Réponses d'erreur

| Code | Raison |
|------|--------|
| `422` | Email déjà utilisé, mot de passe trop court, etc. |
| `400` | Aucun tenant résolu dans la requête |

---

## GET `/api/auth/me`

Retourne le profil de l'utilisateur connecté.

### Requête

```http
GET /api/auth/me
Authorization: Bearer {token}
```

### Réponse 200

```json
{
  "id": "f47ac10b-...",
  "name": "Propriétaire",
  "email": "owner@boutique-dakar.sn",
  "tenant": {
    "id": "a1b2c3d4-...",
    "name": "Boutique Dakar",
    "slug": "boutique-dakar",
    "plan": "starter",
    "status": "active"
  },
  "roles": ["admin"],
  "permissions": ["products.create", "inventory.edit", ...]
}
```

---

## POST `/api/auth/forgot-password` *(RC-10 F-3, public, throttle 3/10 min)*

Demande un **code de réinitialisation** (6 chiffres) envoyé par email. Réponse **toujours générique**
(anti-énumération) — qu'un compte existe ou non.

```http
POST /api/auth/forgot-password
{ "email": "user@exemple.sn" }
```
**200** → `{ "message": "Si un compte correspond à cet email, un code … vient d'être envoyé." }`

## POST `/api/auth/reset-password` *(RC-10 F-3, public, throttle 5/10 min)*

Applique un nouveau mot de passe avec le code reçu. Le code expire en **30 min** et est **brûlé après
5 tentatives**. Un reset réussi **révoque toutes les sessions** de l'utilisateur.

```http
POST /api/auth/reset-password
{ "email": "user@exemple.sn", "code": "123456", "password": "NouveauMdp1", "password_confirmation": "NouveauMdp1" }
```
**200** → `{ "message": "Mot de passe réinitialisé …" }` · **422** → code invalide/expiré ou mot de passe faible.

## POST `/api/auth/accept-invitation` *(RC-12 F-5, public, throttle 5/10 min)*

Active un compte **invité** : le membre pose son mot de passe avec le **code** reçu par email (valable
7 jours, brûlé après 5 tentatives). `POST /api/workspace/users` ne renvoie plus de mot de passe
temporaire — il envoie ce code par email (`invitation_sent: true`).

```http
POST /api/auth/accept-invitation
{ "email": "membre@exemple.sn", "code": "123456", "password": "MonMdp1", "password_confirmation": "MonMdp1" }
```
**200** → `{ "message": "Compte activé …" }` · **422** → invitation invalide/expirée ou déjà acceptée.

## POST `/api/me/email/verify` *(RC-11 F-6, auth)*

Confirme un **changement d'email** demandé via `PATCH /api/me/profile`. Le nouvel email n'est appliqué
qu'après ce code (envoyé à la nouvelle adresse ; 30 min ; brûlé après 5 tentatives).

```http
POST /api/me/email/verify
Authorization: Bearer {token}
{ "code": "123456" }
```
**200** → `{ "data": { "email": "nouveau@exemple.sn" }, "message": "Adresse email mise à jour." }` ·
**422** → code invalide/expiré, ou adresse déjà prise entre-temps.

> `PATCH /api/me/profile` applique le **nom** immédiatement ; un **email** différent renvoie
> `email_verification_required: true` + `pending_email` sans changer l'email courant.

## 2FA par code email *(RC-13 F-4)*

Opt-in par utilisateur (`POST /api/me/2fa {enabled}`). Quand elle est active :

- **`POST /api/auth/login`** ne renvoie **pas** de token : `{ "two_factor_required": true, "email": … }`
  et un code (6 chiffres, 10 min) part à l'email du compte.
- **`POST /api/auth/2fa/verify`** `{email, code}` (public, throttle 10/10 min) → `{ "token", "user" }`.

## POST `/api/auth/logout`

Révoque le token actuel.

### Requête

```http
POST /api/auth/logout
Authorization: Bearer {token}
```

### Réponse 204 — No Content

---

## POST `/api/auth/refresh`

Révoque le token actuel et en émet un nouveau (rotation).

### Requête

```http
POST /api/auth/refresh
Authorization: Bearer {token}
```

### Réponse 200

```json
{
  "token": "3|newtoken..."
}
```

---

## Authentification des requêtes protégées

Toutes les routes protégées nécessitent :

```http
Authorization: Bearer {token}
```

Optionnellement, le contexte tenant peut être précisé via :

```http
X-Tenant-ID: a1b2c3d4-e5f6-7890-abcd-ef1234567890
# ou
X-Tenant-Slug: boutique-dakar
```

Si le token appartient à un utilisateur avec un `tenant_id`, le tenant est résolu automatiquement sans header supplémentaire.
