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
