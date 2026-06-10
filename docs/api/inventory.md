# API — Inventaire & Stock

Toutes les routes sont sous `/api/inventory` et nécessitent `Authorization: Bearer {token}`.

---

## Consultation

### GET `/api/inventory/stock`

Liste tous les produits avec leur niveau de stock (paginé, 50/page).

```http
GET /api/inventory/stock
Authorization: Bearer {token}
```

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": "uuid",
      "product_id": "uuid",
      "variant_id": null,
      "quantity": 47,
      "reserved_quantity": 2,
      "low_stock_threshold": 5,
      "product": { "id": "uuid", "sku": "VET-0001", "name": "Boubou Sénégalais" }
    }
  ],
  "meta": { "current_page": 1, "total": 34 }
}
```

---

### GET `/api/inventory/stock/{productId}`

Stock d'un produit spécifique.

**Query params :**
- `variant_id` (uuid, optionnel) — pour une variante précise

```http
GET /api/inventory/stock/f47ac10b?variant_id=a1b2c3d4
```

**Réponse 200 :**
```json
{
  "stock": {
    "id": "uuid",
    "quantity": 20,
    "reserved_quantity": 3
  },
  "available": 17,
  "is_low_stock": false
}
```

---

### GET `/api/inventory/stock/{productId}/movements`

Historique des mouvements de stock pour un produit (paginé).

**Query params :**
- `variant_id` (uuid, optionnel)
- `per_page` (int, défaut 20)

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "in",
      "quantity": 30,
      "quantity_before": 0,
      "quantity_after": 30,
      "reason": "delivery",
      "reference": "BL-2026-001",
      "note": null,
      "performed_by": "user-uuid",
      "created_at": "2026-05-30T08:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "total": 12 }
}
```

---

### GET `/api/inventory/alerts`

Produits en stock bas (quantité ≤ seuil d'alerte).

**Réponse 200 :**
```json
[
  {
    "id": "uuid",
    "product_id": "uuid",
    "quantity": 2,
    "low_stock_threshold": 5,
    "product": { "sku": "VET-0001", "name": "Boubou Sénégalais" }
  }
]
```

---

## Opérations manuelles

### POST `/api/inventory/stock/{productId}/move-in`

Entrée en stock manuelle.

```json
{
  "quantity": 30,
  "reason": "delivery",        // delivery | return | manual
  "reference": "BL-2026-001", // optionnel
  "note": "Commande Dakar",   // optionnel
  "variant_id": "uuid"        // optionnel
}
```

**Réponse 201 :**
```json
{
  "movement": { "type": "in", "quantity": 30, "quantity_before": 0, "quantity_after": 30, ... },
  "stock": { "quantity": 30, "available": 30 }
}
```

---

### POST `/api/inventory/stock/{productId}/move-out`

Sortie de stock manuelle.

```json
{
  "quantity": 5,
  "reason": "sale",   // sale | loss | manual
  "reference": "CMD-2026-042"
}
```

**Réponse 201** — même structure que `move-in`.

**Réponse 422** — stock insuffisant :
```json
{
  "message": "Insufficient stock for VET-0001: requested 10, available 3.",
  "available": 3
}
```

**Réponse 503** — lock temporaire (retry) :
```json
{ "message": "Could not acquire stock lock. Retry." }
```

---

### POST `/api/inventory/stock/{productId}/adjust`

Ajustement à une quantité absolue (inventaire physique).

```json
{
  "quantity": 73,
  "note": "Inventaire du 30/05/2026 — 27 unités manquantes détectées"
}
```

**Réponse 200** — même structure que `move-in`.

---

## Scan-to-action

### POST `/api/inventory/scan`

Point d'entrée principal pour le POS et les scanners Bluetooth. Résout le SKU et effectue l'action demandée.

```json
{
  "sku": "VET-0001",
  "action": "move_in",         // move_in | move_out | check
  "quantity": 1,               // requis sauf pour "check"
  "reason": "sale",            // optionnel — déduit de l'action si absent
  "reference": "CMD-2026-001"  // optionnel
}
```

### Action `check` — consultation sans mouvement

```json
{
  "sku": "VET-0001",
  "action": "check"
}
```

**Réponse 200 :**
```json
{
  "sku": "VET-0001",
  "stock": { "quantity": 47, "reserved_quantity": 2 },
  "available": 45,
  "is_low_stock": false
}
```

### Raisons par défaut selon l'action

| `action` | Raison auto |
|----------|-------------|
| `move_in` | `delivery` |
| `move_out` | `sale` |

---

## Opérations batch

### POST `/api/inventory/deliveries`

Réception complète d'une livraison multi-produits.

```json
{
  "reference": "BL-2026-042",
  "items": [
    { "product_id": "uuid1", "quantity": 30 },
    { "product_id": "uuid2", "variant_id": "uuid3", "warehouse_id": "uuid4", "quantity": 15, "unit_cost_cents": 1200, "note": "Taille L seulement" }
  ]
}
```

| Validation | Valeur |
|-----------|--------|
| `items` max | 200 lignes |
| `quantity` max par item | 10 000 |
| `warehouse_id` (RC-4) | optionnel — entrepôt cible par ligne (défaut : entrepôt par défaut du tenant) |
| `unit_cost_cents` | optionnel — coût d'achat de la réception (alimente le CMUP) |

> **Périmètre d'accès (RC-4).** Un opérateur restreint à certains sites (`user_warehouses`) ne peut
> réceptionner que dans ses entrepôts autorisés — une ligne ciblant un entrepôt interdit renvoie **403**.
> Les managers/admins ne sont pas restreints.

---

### GET `/api/catalog/products/{id}/variant-stock-matrix`

Matrice d'entrée de stock « best-ERP » qui peuple la grille multi-variantes × entrepôt : lignes =
variantes (ou le produit simple), colonnes = entrepôts **accessibles** à l'utilisateur, cellule =
stock courant. Renvoie **422** pour un produit non stockable (service/digital).

```json
{
  "product_id": "uuid",
  "product_name": "T-shirt",
  "has_variants": true,
  "warehouses": [{ "id": "wh1", "name": "Abidjan", "code": "WH-ABJ", "is_default": true }],
  "rows": [
    { "variant_id": "v1", "label": "Rouge", "sku": "TSHIRT-R",
      "cells": { "wh1": { "quantity": 12, "available": 12, "unit_cost_cents": 1000 } } }
  ]
}
```

La grille frontend collecte les quantités saisies par cellule puis POST le tout vers
`/api/inventory/deliveries` (une ligne par couple variante × entrepôt).

**Réponse 201 :**
```json
{
  "movements": [...],
  "count": 2
}
```

---

### POST `/api/inventory/count`

Inventaire physique batch — ajuste chaque produit à la quantité comptée.

```json
{
  "items": [
    { "product_id": "uuid1", "quantity": 47, "note": "Compté le 30/05" },
    { "product_id": "uuid2", "quantity": 12 }
  ]
}
```

**Réponse 200** — même structure que `/deliveries`.

---

## Intégration Flutter POS

```dart
// Scan de réception (Bluetooth scanner → SKU)
final response = await api.post('/inventory/scan', {
  'sku': scannedSku,
  'action': 'move_in',
  'quantity': receivedQty,
  'reason': 'delivery',
  'reference': deliveryNoteNumber,
});

// Scan de vente (caisse)
final response = await api.post('/inventory/scan', {
  'sku': scannedSku,
  'action': 'move_out',
  'quantity': 1,
  'reason': 'sale',
  'reference': orderId,
});

// Vérification rapide du stock
final response = await api.post('/inventory/scan', {
  'sku': scannedSku,
  'action': 'check',
});
```
