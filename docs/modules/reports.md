# Module Reports — Documentation technique

## Vue d'ensemble

Le module Reports agrège les données de tous les autres modules (Orders, Payments, Catalog, Inventory) pour produire des KPIs, des rapports de ventes et des rapports de stock. Il ne possède pas de migrations propres : il lit en lecture seule les tables des autres modules.

## Architecture

```
app/Modules/Reports/
├── Providers/
│   └── ReportsServiceProvider.php
├── Services/
│   └── ReportService.php          ← Toute la logique métier
├── Http/
│   └── Controllers/
│       └── ReportController.php
├── routes/
│   └── api.php
└── Tests/
    ├── Unit/
    │   └── ReportServiceTest.php  (9 tests)
    └── Integration/
        └── ReportApiTest.php      (13 tests)
```

## Service — ReportService

### `dashboard(string $tenantId): array`

Retourne :
```php
[
  'kpis' => [
    'revenue_today'        => int,    // centimes
    'revenue_today_change' => float|null, // % vs hier
    'orders_today'         => int,
    'orders_today_change'  => float|null,
    'active_products'      => int,
    'low_stock_alerts'     => int,
  ],
  'revenue_chart' => [['date' => 'YYYY-MM-DD', 'amount' => int, 'count' => int], ...],  // 7 jours
  'recent_orders' => Collection,   // 5 dernières commandes avec customer:id,name
  'top_products'  => Collection,   // 5 meilleurs produits sur 30 jours
]
```

**Sources :**
- Revenu : table `payments` (sum `amount_cents` where `paid_at = today`)
- Commandes : table `orders` (count, status != cancelled)
- Produits actifs : table `products` (status = 'active')
- Alertes stock : table `stocks` (quantity <= low_stock_threshold AND threshold > 0)

### `sales(string $tenantId, string $period = '7d'): array`

Périodes acceptées : `7d` | `30d` | `90d` | `1y`

Retourne :
```php
[
  'period'        => string,
  'total_revenue' => int,      // somme de tous les points du chart
  'total_orders'  => int,      // somme des count du chart
  'revenue_chart' => [...],    // N points selon la période
  'top_products'  => Collection, // 10 meilleurs produits sur la période
  'by_method'     => Collection, // répartition par méthode de paiement
]
```

**Top products** : JOIN `order_lines` → `orders`, groupé par `product_id`, trié par `SUM(unit_price_cents * quantity)`.

### `stock(string $tenantId): array`

Retourne :
```php
[
  'stock_value'      => int,          // centimes — quantité × COALESCE(cost, price)
  'total_skus'       => int,
  'out_of_stock'     => int,          // quantity <= 0
  'low_stock_count'  => int,          // items dans la liste
  'low_stock_items'  => Collection,   // max 10 items avec product:id,name,sku
  'recent_movements' => Collection,   // derniers 30j groupés par type
]
```

**Valeur stock :** `SUM(quantity * COALESCE(NULLIF(cost_amount, 0), price_amount))` — utilise le coût d'achat si renseigné, sinon le prix de vente.

## Helpers privés

### `revenueByDay(string $tenantId, int $days): array`

Construit un tableau de N points consécutifs (jours). Les jours sans paiement ont `amount=0, count=0`.

Requête : `GROUP BY DATE(paid_at)` → keyed par date → boucle `now()->subDays($i)`.

### `topProducts(string $tenantId, int $days, int $limit): Collection`

JOIN direct `order_lines` → `orders` (pas de `whereHas`) pour éviter les sous-requêtes. Utilise les colonnes snapshot `name` et `sku` de la ligne de commande (valeur au moment de la vente).

## Considérations SQLite (tests)

- `DATE(paid_at)` : supporté nativement par SQLite ✓
- `COALESCE(NULLIF(...))` : supporté ✓
- `CAST(quantity AS REAL)` : supporté ✓
- `ABS()` : supporté ✓

## Routes

```
GET /api/reports/dashboard        → ReportController@dashboard
GET /api/reports/sales?period=*   → ReportController@sales
GET /api/reports/stock            → ReportController@stock
```

Toutes les routes nécessitent `auth:sanctum`. Le `tenant_id` est extrait de `$request->user()->tenant_id`.

## Tests

**Unit (9 tests) :** Structure dashboard, zéros, revenu du jour, isolation tenant, comptage produits actifs, périodes de ventes, tailles de chart, totaux agrégés, structure stock.

**Integration (13 tests) :** Auth guard (3 routes), structure dashboard, zéros, revenu aujourd'hui, chart 7 points, isolation tenant, période 7d, période 30d, période invalide fallback, totaux 2 paiements, répartition by_method, structure stock, valeur stock = qty × cost.
