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

### `specialProducts(string $tenantId, ?array $warehouseIds): array`  *(RC-5G)*

Reporting **produits spéciaux** (audit §8.6) — lit Catalog, Inventory (`inventory_units`), Warranties et
Digital :
```php
[
  'aggregate_stock_value'  => int,   // produits aggregate|batch (le sérialisé est valorisé par unité)
  'serialized'             => ['in_stock' => int, 'reserved' => int, 'sold' => int, 'in_stock_value' => int],
  'non_stockable_excluded' => int,   // services/digital (stock_tracking=none) écartés
  'warranties'             => ['active_contracts' => int, 'open_claims' => int],
  'digital'                => ['active_entitlements' => int],
  'total_inventory_value'  => int,   // aggregate + unités sérialisées en stock
]
```

Évite le **double-comptage** : les produits sérialisés ne sont **pas** valorisés via leur miroir agrégé
mais par unité `in_stock`. Les services/digital sont **exclus** de la valorisation.

### `abcClassification(string $tenantId, int $days = 90): array` *(RC-17 M-2)*

Classification **ABC (Pareto 80/15/5)** du CA par produit sur la période : part de CA, part cumulée,
classe `A` (≤ 80 %), `B` (≤ 95 %), `C` (reste). Retourne `period_days`, `total_revenue`,
`summary` (par classe : nb produits, CA, part) et `items` (triés par CA décroissant).

### `inventoryKpis(string $tenantId, int $days = 90): array` *(RC-17 M-2)*

KPIs d'inventaire : **DSI** (jours de stock = valeur stock / COGS × jours), **taux de rotation**
(annualisé), **fill rate** (commandes honorées / confirmées+honorées), **taux de stock mort**
(valeur des stocks sans mouvement depuis 180 j / valeur totale).

### `stockReconciliation(string $tenantId): array` *(RC-17 M-2)*

Réconciliation : valorisation ERP **par catégorie** (`sku_count`, quantités, `erp_value_cents`,
CMUP moyen) + synthèse des mouvements du dernier mois (`reason` × `type`).

> Ces trois méthodes existaient (développées + testées) mais **sans route ni vue** (code mort
> applicatif consigné en M-2, rc.147). Exposées en RC-17 : routes ci-dessous + onglet front
> **Analyse d'inventaire** (`/reports/insights`, `InventoryInsightsView.vue`).

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
GET /api/reports/special-products → ReportController@specialProducts   (RC-5G)
GET /api/reports/abc?days=30|90|180|365      → ReportController@abc            (RC-17)
GET /api/reports/inventory-kpis?days=…       → ReportController@inventoryKpis  (RC-17)
GET /api/reports/reconciliation              → ReportController@reconciliation (RC-17)
```

Toutes les routes nécessitent `auth:sanctum`. Le `tenant_id` est extrait de `$request->user()->tenant_id`.
Les périodes hors liste blanche (30/90/180/365) retombent sur 90 jours.

## Tests

**Unit (9 tests) :** Structure dashboard, zéros, revenu du jour, isolation tenant, comptage produits actifs, périodes de ventes, tailles de chart, totaux agrégés, structure stock.

**Integration (13 tests) :** Auth guard (3 routes), structure dashboard, zéros, revenu aujourd'hui, chart 7 points, isolation tenant, période 7d, période 30d, période invalide fallback, totaux 2 paiements, répartition by_method, structure stock, valeur stock = qty × cost.
