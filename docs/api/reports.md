# API Reports — Référence

Base URL : `/api/reports`  
Authentification : `Authorization: Bearer {token}` (Sanctum)  
Isolation : chaque endpoint est scopé au `tenant_id` de l'utilisateur connecté.

---

## GET /api/reports/dashboard

Vue d'ensemble du jour : revenus, commandes, produits actifs, alertes stock.

### Réponse 200

```json
{
  "kpis": {
    "revenue_today": 125000,
    "revenue_today_change": 5.2,
    "orders_today": 12,
    "orders_today_change": -2.1,
    "active_products": 234,
    "low_stock_alerts": 8
  },
  "revenue_chart": [
    { "date": "2026-05-25", "amount": 45000, "count": 3 },
    { "date": "2026-05-26", "amount": 80000, "count": 5 },
    { "date": "2026-05-27", "amount": 0,     "count": 0 },
    { "date": "2026-05-28", "amount": 120000,"count": 7 },
    { "date": "2026-05-29", "amount": 60000, "count": 4 },
    { "date": "2026-05-30", "amount": 95000, "count": 6 },
    { "date": "2026-05-31", "amount": 125000,"count": 8 }
  ],
  "recent_orders": [
    {
      "id": "uuid",
      "number": "ORD-2026-0042",
      "status": "confirmed",
      "total_amount": 48000,
      "currency": "EUR",
      "customer_id": "uuid",
      "created_at": "2026-05-31T10:22:00Z",
      "customer": { "id": "uuid", "name": "Amina Diallo" }
    }
  ],
  "top_products": [
    {
      "product_id": "uuid",
      "product_name": "Tissu Wax 6m",
      "sku": "WX-001",
      "total_qty": 45,
      "total_revenue": 225000
    }
  ]
}
```

**Notes :**
- `revenue_today_change` et `orders_today_change` : pourcentage vs hier, `null` si hier = 0.
- `revenue_chart` : toujours 7 points, un par jour glissant.
- `recent_orders` : 5 dernières commandes (toutes statuts).
- `top_products` : 5 meilleurs produits sur les 30 derniers jours.
- Montants en **centimes**.

---

## GET /api/reports/sales

Rapport de ventes sur une période.

### Paramètres

| Paramètre | Type | Valeurs | Défaut |
|-----------|------|---------|--------|
| `period`  | string | `7d` \| `30d` \| `90d` \| `1y` | `7d` |

### Réponse 200

```json
{
  "period": "30d",
  "total_revenue": 4250000,
  "total_orders": 87,
  "revenue_chart": [
    { "date": "2026-05-01", "amount": 120000, "count": 3 },
    ...
  ],
  "top_products": [
    {
      "product_id": "uuid",
      "product_name": "Tissu Wax 6m",
      "sku": "WX-001",
      "total_qty": 150,
      "total_revenue": 750000
    }
  ],
  "by_method": [
    { "method": "mobile_money", "amount": 2100000, "count": 45 },
    { "method": "cash",         "amount": 1500000, "count": 30 },
    { "method": "card",         "amount": 650000,  "count": 12 }
  ]
}
```

**Notes :**
- `revenue_chart` : N points (7, 30, 90 ou 365) — chaque point = 1 jour.
- `total_revenue` = somme de tous les `amount` du chart.
- `top_products` : 10 meilleurs produits, lignes des commandes NON annulées.
- `by_method` trié par montant décroissant.

---

## GET /api/reports/stock

Rapport de stock : valeur, ruptures, alertes.

### Réponse 200

```json
{
  "stock_value": 8750000,
  "total_skus": 156,
  "out_of_stock": 3,
  "low_stock_count": 12,
  "low_stock_items": [
    {
      "id": "uuid",
      "product_id": "uuid",
      "quantity": 2,
      "reserved_quantity": 0,
      "low_stock_threshold": 10,
      "product": {
        "id": "uuid",
        "name": "Fil à coudre noir",
        "sku": "FL-001"
      }
    }
  ],
  "recent_movements": [
    { "type": "in",         "count": 45, "total_qty": 520 },
    { "type": "out",        "count": 38, "total_qty": 410 },
    { "type": "adjustment", "count": 5,  "total_qty": 30  }
  ]
}
```

**Notes :**
- `stock_value` : `SUM(quantity × COALESCE(cost_amount, price_amount))` — coût d'achat si renseigné, sinon prix de vente.
- `low_stock_items` : max 10 items, triés par ratio `quantity / threshold` croissant.
- `recent_movements` : 30 derniers jours, groupés par type (`in`, `out`, `adjustment`, `return`).

---

## Erreurs communes

| Code | Cause |
|------|-------|
| 401 | Token manquant ou invalide |
