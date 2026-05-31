# API Suppliers — Référence

Base URL : `/api/suppliers`  
Auth : `Authorization: Bearer {token}`

---

## GET /api/suppliers

Liste paginée des fournisseurs.

### Paramètres query

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Recherche sur nom, code, email |
| `status` | string | `active` \| `inactive` |
| `per_page` | int | Défaut 20 |

### Réponse 200

```json
{
  "data": [
    {
      "id": "uuid",
      "code": "F001",
      "name": "TextilePro",
      "email": "contact@textilepro.com",
      "phone": "+221771234567",
      "contact_name": "Moussa Diop",
      "address": { "city": "Dakar", "country": "SN" },
      "payment_terms": "30 jours",
      "notes": null,
      "status": "active",
      "created_at": "2026-05-31T10:00:00Z",
      "updated_at": "2026-05-31T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 58 }
}
```

---

## POST /api/suppliers

### Corps

```json
{
  "name": "TextilePro",
  "email": "contact@textilepro.com",
  "phone": "+221771234567",
  "contact_name": "Moussa Diop",
  "address": { "city": "Dakar" },
  "payment_terms": "30 jours",
  "notes": "Délai habituel 5 jours",
  "code": "F001",
  "status": "active"
}
```

`name` est obligatoire. `code` sera auto-généré si absent. `status` défaut `active`.

### Réponse 201 — `{ "data": {...} }`

---

## GET /api/suppliers/{id}

### Réponse 200 — `{ "data": {...} }`

---

## PUT /api/suppliers/{id}

Même corps que POST (tous les champs optionnels). Retourne 200.

---

## DELETE /api/suppliers/{id}

Soft delete. Retourne 204.

---

## GET /api/suppliers/search?q=textile

Recherche typeahead. Retourne les 10 premiers résultats correspondants.

### Réponse 200

```json
{
  "data": [
    { "id": "uuid", "code": "F001", "name": "TextilePro" }
  ]
}
```
