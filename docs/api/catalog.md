# API — Catalogue produits

Toutes les routes sont préfixées `/api/catalog`. Les routes protégées nécessitent `Authorization: Bearer {token}`.

---

## Catégories

### GET `/api/catalog/categories`

Liste toutes les catégories du tenant.

```http
GET /api/catalog/categories
Authorization: Bearer {token}
```

**Réponse 200 :**
```json
[
  {
    "id": "uuid",
    "name": "Vêtements",
    "slug": "vetements",
    "parent_id": null,
    "sort_order": 1,
    "is_active": true,
    "children": [
      { "id": "uuid", "name": "Boubous", "slug": "boubous", "parent_id": "uuid-parent" }
    ]
  }
]
```

### POST `/api/catalog/categories`

Créer une catégorie.

```json
{
  "name": "Vêtements",
  "parent_id": null,        // optionnel — UUID d'une catégorie parente
  "description": "...",     // optionnel
  "sort_order": 1           // optionnel, défaut 0
}
```

**Réponse 201** — objet catégorie créé.

---

## Produits

### GET `/api/catalog/products`

Liste les produits du tenant avec filtres.

**Paramètres de requête :**

| Paramètre | Type | Description |
|-----------|------|-------------|
| `status` | string | Filtrer par statut (`active`, `draft`, `archived`) |
| `category_id` | uuid | Filtrer par catégorie |
| `search` | string | Recherche dans nom + SKU |
| `page` | int | Pagination (défaut 1) |
| `per_page` | int | Résultats/page (défaut 20, max 100) |

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": "f47ac10b-...",
      "sku": "VET-0001",
      "name": "Boubou Sénégalais",
      "status": "active",
      "price": {
        "amount": 25000,
        "currency": "XOF",
        "formatted": "25 000 XOF"
      },
      "compare_at_price": null,
      "is_on_sale": false,
      "stock_quantity": 42,
      "has_variants": false,
      "category": { "id": "...", "name": "Boubous" }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 147
  }
}
```

### POST `/api/catalog/products`

Créer un produit.

```json
{
  "name": "Boubou Sénégalais",
  "sku": "VET-0001",           // optionnel — auto-généré si absent
  "category_id": "uuid",       // optionnel
  "price_amount": 25000,       // en centimes, obligatoire
  "price_currency": "XOF",     // obligatoire
  "compare_at_price_amount": 35000,  // optionnel — prix barré
  "cost_amount": 15000,        // optionnel — prix d'achat
  "description": "...",        // optionnel
  "status": "draft"            // optionnel, défaut "draft"
}
```

**Réponse 201** — objet produit créé.

### GET `/api/catalog/products/{id}`

Détail d'un produit avec ses variantes.

**Réponse 200 :**
```json
{
  "id": "f47ac10b-...",
  "sku": "VET-0001",
  "name": "Boubou Sénégalais",
  "has_variants": true,
  "variants": [
    {
      "id": "uuid",
      "sku": "VET-0001-V1",
      "name": "Rouge / L",
      "attributes": { "Couleur": "Rouge", "Taille": "L" },
      "price": { "amount": 25000, "currency": "XOF", "formatted": "25 000 XOF" },
      "stock_quantity": 5
    }
  ]
}
```

### PUT `/api/catalog/products/{id}`

Mettre à jour un produit (champs partiels acceptés).

### DELETE `/api/catalog/products/{id}` *(archive)*

Archive le produit (ne le supprime pas physiquement). Statut passe à `archived`.

**Réponse 204**

### GET `/api/catalog/products/sku/{sku}`

Chercher un produit par SKU. Route **publique** (sans authentification) — utilisée par les scanners POS.

**Réponse 200 :** objet produit simplifié + `variants`.
**Réponse 404 :** produit introuvable.

---

## Variantes

### POST `/api/catalog/products/{id}/variants`

Créer une variante pour un produit.

```json
{
  "name": "Rouge / L",
  "attributes": { "Couleur": "Rouge", "Taille": "L" },
  "sku": "VET-0001-V1",     // optionnel — auto-généré
  "price_amount": 27000,    // optionnel — hérite du parent si absent
  "price_currency": "XOF",  // requis si price_amount fourni
  "stock_quantity": 10
}
```

**Réponse 201** — objet variante. Le produit parent a désormais `has_variants = true`.

---

## Codes produits

### GET `/api/catalog/products/{id}/qrcode`

Retourne le QR code SVG du produit.

```http
GET /api/catalog/products/f47ac10b/qrcode
Authorization: Bearer {token}
```

**Réponse 200** — `Content-Type: image/svg+xml`

```xml
<svg xmlns="http://www.w3.org/2000/svg" ...>...</svg>
```

Le QR encode : `{"sku":"VET-0001","id":"f47ac10b-...","name":"Boubou Sénégalais"}`

### GET `/api/catalog/products/{id}/barcode`

Retourne le code-barres Code128 SVG.

**Réponse 200** — `Content-Type: image/svg+xml`

### GET `/api/catalog/products/{id}/codes`

Retourne QR + barcode en JSON (pour affichage dans l'app).

**Réponse 200 :**
```json
{
  "sku": "VET-0001",
  "qr": { "format": "svg", "data": "<svg ...>...</svg>" },
  "barcode": { "format": "svg", "type": "code128", "data": "<svg ...>...</svg>" }
}
```
