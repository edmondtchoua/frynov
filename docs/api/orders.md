# API Orders

Base URL : `/api/orders`  
Authentification : `Authorization: Bearer <token>` + `X-Tenant-Slug: <slug>` (via intercepteur Axios)

---

## GET /api/orders

Liste paginée des commandes du tenant authentifié.

**Query params**

| Param    | Type   | Description                                             |
|----------|--------|---------------------------------------------------------|
| status   | string | Filtre : `draft`, `confirmed`, `fulfilled`, `cancelled` |
| page     | int    | Page courante (défaut : 1)                              |
| per_page | int    | Taille de page (défaut : 20)                            |

**Réponse 200**
```json
{
  "data": [
    {
      "id": "uuid",
      "number": "ORD-00001",
      "status": "confirmed",
      "total_amount": 75000,
      "currency": "XOF",
      "note": null,
      "fulfilled_at": null,
      "cancelled_at": null,
      "created_at": "2026-05-30T14:00:00Z",
      "lines": [
        {
          "id": "uuid",
          "sku": "VET-0001",
          "name": "Boubou Sénégalais",
          "quantity": 3,
          "unit_price_cents": 25000
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 20,
    "total": 72
  }
}
```

---

## POST /api/orders

Crée une commande en statut **draft**. Aucun impact sur le stock.

**Corps**
```json
{
  "items": [
    {
      "product_id": "uuid",
      "variant_id": null,
      "quantity": 3,
      "unit_price_cents": 25000
    }
  ],
  "customer_id": null,
  "note": "Commande urgente"
}
```

> `unit_price_cents` est optionnel — le prix du catalogue est utilisé si absent.

**Réponse 201**
```json
{
  "id": "uuid",
  "number": "ORD-00001",
  "status": "draft",
  "total_amount": 75000,
  "currency": "XOF",
  "lines": [...]
}
```

**Erreurs**

| Code | Cause                                |
|------|--------------------------------------|
| 401  | Non authentifié                      |
| 422  | Validation échouée (items vides, …)  |

---

## GET /api/orders/{id}

Détail d'une commande avec ses lignes.

**Réponse 200** : objet `Order` complet  
**Réponse 404** : commande inconnue pour ce tenant

---

## POST /api/orders/{id}/confirm

Confirme une commande draft et **réserve le stock** de chaque ligne.

**Corps** : aucun

**Réponse 200** : commande mise à jour (`status: "confirmed"`)

**Erreurs**

| Code | Cause                                             |
|------|---------------------------------------------------|
| 404  | Commande introuvable                              |
| 422  | Statut invalide (déjà confirmée, annulée, …)     |
| 422  | `{ "message": "...", "available": 12 }` — stock insuffisant |
| 503  | Verrou Redis non acquis (réessayer dans 1 s)      |

---

## POST /api/orders/{id}/fulfill

Marque une commande **livrée** — consomme le stock réservé (moveOut + release).

**Corps** : aucun

**Réponse 200** : commande avec `status: "fulfilled"` et `fulfilled_at` renseigné

**Erreurs** : identiques à `/confirm` (404, 422 état, 422 stock, 503)

---

## POST /api/orders/{id}/cancel

Annule une commande (`draft` ou `confirmed`). Libère les réservations si confirmée.

**Corps** : aucun

**Réponse 200** : commande avec `status: "cancelled"` et `cancelled_at` renseigné

**Erreurs**

| Code | Cause                                          |
|------|------------------------------------------------|
| 404  | Commande introuvable                           |
| 422  | Statut invalide (fulfilled ou déjà cancelled)  |

---

## Exemple Flutter / Dart (POS mobile)

```dart
// Créer une commande
final order = await api.post('/api/orders', body: {
  'items': [
    {'product_id': productId, 'quantity': 2},
  ],
});

// Confirmer (réserve le stock)
await api.post('/api/orders/${order['id']}/confirm');

// Encaisser → livrer
await api.post('/api/orders/${order['id']}/fulfill');
```

---

## Notes importantes

1. **Numérotation** : séquentielle par tenant, format `ORD-XXXXX` (5 chiffres zéro-paddés).
2. **Montants** : toujours en centimes XOF. L'affichage côté front divise par 100 ou utilise `Intl.NumberFormat` avec `currency: 'XOF'`.
3. **Snapshots** : `sku` et `name` dans les lignes sont figés au moment de la commande. Un renommage produit ultérieur ne les affecte pas.
4. **Idempotence** : confirmer une commande déjà confirmée retourne 422 — gérer ce cas dans les clients.
5. **503 (lock)** : implémenter un retry exponentiel côté client (1 s, 2 s, 4 s max).
