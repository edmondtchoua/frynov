# API Digital (Produits digitaux — RC-5E)

Base URL : `/api/digital`
Authentification : `Authorization: Bearer <token>` + appartenance tenant.
Révocation réservée **manager / admin**.

> Les **droits d'accès** (entitlements) ne se créent pas via l'API : ils sont **générés à la livraison**
> de la commande pour les produits qui se livrent en `download`/`license` (cf. [API Orders](orders.md)).

---

## GET /api/digital/orders/{orderId}/entitlements

Droits d'accès générés pour une commande. **Sans secret** (ni jeton ni clé de licence).

**Réponse 200**
```json
{
  "data": [
    {
      "id": "uuid",
      "product_id": "uuid",
      "order_line_id": "uuid",
      "customer_id": "uuid",
      "fulfillment_type": "license",
      "status": "active",
      "granted_at": "2026-06-14T00:00:00Z",
      "expires_at": null,
      "product_name": "Ebook PHP"
    }
  ],
  "count": 1
}
```

**Réponse 404** : commande inconnue pour ce tenant.

---

## GET /api/digital/access/{token}

Vérifie un droit d'accès via son **jeton opaque** et — si l'accès est **actif** — **révèle le secret**
(clé de licence) et les **liens de téléchargement signés** (`download_urls`, RC-5I) des fichiers privés
du produit. C'est le point qui **remplace** l'exposition directe d'un fichier privé.

**Réponse 200** (accès actif)
```json
{
  "data": {
    "id": "uuid",
    "fulfillment_type": "license",
    "status": "active",
    "access_token": "uuid",
    "license_key": "A1B2-C3D4-E5F6-G7H8",
    "download_urls": [
      { "asset_id": "uuid", "name": "ebook.pdf", "size_bytes": 12345, "expires_in": 900,
        "url": "https://…/api/digital/download/{token}/{asset}?expires=…&signature=…" }
    ]
  }
}
```

| Code | Cause |
|------|-------|
| 200  | Accès actif → secret révélé |
| 403  | `{ "message": "...", "status": "revoked" }` — accès révoqué ou expiré |
| 404  | Jeton inconnu **pour ce tenant** (isolation : aucune fuite de secret cross-tenant) |

---

## POST /api/digital/entitlements/{id}/revoke  *(manager/admin)*

Révoque un accès : `status → revoked`. Tout appel d'accès ultérieur renvoie **403**.

**Réponse 200** : `{ "data": { ...entitlement, "status": "revoked" } }`
**Réponse 404** : accès inconnu pour ce tenant.

---

## Fichiers privés (RC-5I)

### POST /api/digital/products/{productId}/assets  *(manager/admin)*

Upload **multipart** (`file`, ≤ 50 Mo) d'un fichier privé attaché à un produit **digital** (sinon 422).
Stocké sur un disque privé ; le `path` n'est jamais renvoyé.

**Réponse 201** : `{ "data": { "id", "name", "size_bytes", "mime", "version", "is_active" } }`

### GET /api/digital/products/{productId}/assets

Liste les fichiers d'un produit (sans `path`/`disk`).

### GET /api/digital/download/{token}/{asset}  *(lien signé, hors auth)*

Télécharge le fichier via un **lien signé et expirable** (généré par `GET /access/{token}`). Le tenant
est dérivé du jeton ; l'accessibilité de l'entitlement est **revérifiée** (révocation → 403 même si la
signature est encore valide). Codes : **200** (flux fichier) · **403** (signature invalide/expirée, ou
accès révoqué/expiré) · **404** (jeton inconnu, ou asset n'appartenant pas au produit de l'entitlement).

---

## Notes

1. **Sans stock** : les produits digitaux/services (`stock_tracking=none`) sont **commandables sans
   stock** depuis RC-5E (l'entitlement EST la preuve de livraison).
2. **Idempotence** : réémettre pour une commande déjà dotée ne crée pas de doublon.
3. **V1** : pas de stockage de fichier réel ni de lien signé client-facing (à venir) ; un entitlement
   par ligne.
