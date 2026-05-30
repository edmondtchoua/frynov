# Module Inventory

## Responsabilité

Gère le stock physique des produits : entrées (livraisons), sorties (ventes, pertes), ajustements (inventaires), historique complet des mouvements, alertes de stock bas, et opérations scan-to-action pour les scanners de caisse et appareils Bluetooth.

---

## Architecture de stock

Le module utilise **deux tables dédiées** (pas de colonnes sur `products`) :

```
stocks             → état courant par produit/variante
stock_movements    → audit trail immutable de chaque opération
```

**Design decision :** Le stock est géré dans une table séparée plutôt que comme colonnes sur `products`. Cela :
- Garde le module Catalog pur (référentiel produit, pas de données opérationnelles)
- Permet d'étendre à plusieurs entrepôts (`warehouse_id`) sans toucher au Catalog
- L'Inventory dépend du Catalog, jamais l'inverse

### Logique de suivi par variante

| Cas | Stock row |
|-----|-----------|
| Produit sans variante | `product_id = X, variant_id = NULL` |
| Variante d'un produit | `product_id = X, variant_id = Y` |

Un produit avec variantes n'a **pas** de ligne stock propre — seules les variantes en ont.

---

## Modèles

### `Stock`

```php
// Attributs
$stock->quantity             // quantité physique en stock
$stock->reserved_quantity    // réservé pour commandes en attente
$stock->low_stock_threshold  // seuil d'alerte (défaut: 5)

// Méthodes
$stock->available(): int     // quantity - reserved_quantity (min 0)
$stock->isLowStock(): bool   // quantity <= low_stock_threshold

// Relations
$stock->product()            // BelongsTo Product
$stock->variant()            // BelongsTo ProductVariant (nullable)
$stock->movements()          // HasMany StockMovement (latest first)
```

### `StockMovement`

Enregistrement immutable de chaque opération.

```php
// Types
StockMovement::TYPE_IN         // 'in'
StockMovement::TYPE_OUT        // 'out'
StockMovement::TYPE_ADJUSTMENT // 'adjustment'
StockMovement::TYPE_RETURN     // 'return'

// Raisons
StockMovement::REASON_DELIVERY // entrée livraison fournisseur
StockMovement::REASON_SALE     // sortie vente client
StockMovement::REASON_RETURN   // retour client
StockMovement::REASON_LOSS     // perte / casse
StockMovement::REASON_COUNT    // ajustement inventaire physique
StockMovement::REASON_MANUAL   // ajustement manuel

// Attributs clés
$movement->quantity_before  // snapshot avant opération
$movement->quantity_after   // snapshot après opération
$movement->reference        // ex: "BL-2026-001" (bon de livraison)
$movement->performed_by     // UUID du user ayant effectué l'opération
```

---

## Services

### `StockService`

Toutes les opérations de stock avec mutex Redis anti-oversell.

```php
// Résolution
$stock = $service->findOrCreate(tenantId, productId, variantId?);
$stock = $service->findBySku('VET-0001', tenantId);  // pour scanner POS

// Opérations
$movement = $service->moveIn(stock, quantity, reason, reference?, note?, performedBy?);
$movement = $service->moveOut(stock, quantity, reason, reference?, note?, performedBy?);
  // ↑ throws InsufficientStockException, StockLockException
$movement = $service->adjust(stock, newQuantity, reason, note?, performedBy?);
  // ↑ throws StockLockException

// Réservations (pour le module Orders)
$service->reserve(stock, quantity);   // throws InsufficientStockException
$service->release(stock, quantity);

// Utilitaires
$service->available(stock): int
$service->lowStockItems(tenantId): Collection
```

#### Protection anti-oversell (Redis)

`moveOut`, `adjust` et `reserve` acquièrent un lock Redis avant d'opérer :

```
Lock key : "inventory.stock.{stock_id}"
TTL      : 5 secondes
```

Si le lock ne peut pas être acquis, `StockLockException` est levée → le client doit retenter. Dans un contexte POS, la réponse HTTP sera `503 Service Unavailable` avec suggestion de retry.

### `InventoryService`

Opérations batch (livraisons complètes, inventaires physiques).

```php
// Réception de livraison multi-produits
$movements = $service->receiveDelivery(items, tenantId, performedBy);
// items: [['product_id', 'variant_id'?, 'quantity', 'reference'?], ...]

// Inventaire physique (ajustements batch)
$movements = $service->processCount(items, tenantId, performedBy);
// items: [['product_id', 'variant_id'?, 'counted_quantity', 'note'?], ...]

// Historique paginé
$paginator = $service->movementHistory(stock, perPage?);
```

---

## Exceptions

| Exception | HTTP | Quand |
|-----------|------|-------|
| `InsufficientStockException` | 422 | `moveOut`/`reserve` avec stock insuffisant |
| `StockLockException` | 503 | Lock Redis non acquis (retry) |
| `ProductNotFoundException` | 404 | SKU inconnu lors d'un scan |

---

## Routes API

Voir [api/inventory.md](../api/inventory.md).

---

## Tests

| Fichier | Type | Tests |
|---------|------|-------|
| `Tests/Unit/InventoryServiceTest.php` | Unit | 11 |
| `Tests/Integration/InventoryApiTest.php` | Integration | 12 |
| `Tests/Modular/InventoryModuleTest.php` | Modular | 2 |
| **Total** | | **25** |

```bash
php vendor/bin/phpunit "app/Modules/Inventory/Tests" --no-coverage
```
