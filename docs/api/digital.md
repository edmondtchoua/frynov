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
(clé de licence et/ou lien de téléchargement signé à venir). C'est le point qui **remplace** l'exposition
directe d'un fichier privé.

**Réponse 200** (accès actif)
```json
{
  "data": {
    "id": "uuid",
    "fulfillment_type": "license",
    "status": "active",
    "access_token": "uuid",
    "license_key": "A1B2-C3D4-E5F6-G7H8"
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

## Notes

1. **Sans stock** : les produits digitaux/services (`stock_tracking=none`) sont **commandables sans
   stock** depuis RC-5E (l'entitlement EST la preuve de livraison).
2. **Idempotence** : réémettre pour une commande déjà dotée ne crée pas de doublon.
3. **V1** : pas de stockage de fichier réel ni de lien signé client-facing (à venir) ; un entitlement
   par ligne.
