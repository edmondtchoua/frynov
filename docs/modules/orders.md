# Module Orders

## Vue d'ensemble

Le module Orders gère le cycle de vie des commandes client dans l'ERP. Il s'intègre directement avec le module Inventory pour réserver et consommer le stock au fil du cycle de vie de la commande.

---

## Modèles

### Order

Fichier : `app/Modules/Orders/Models/Order.php`

| Colonne        | Type              | Description                                          |
|----------------|-------------------|------------------------------------------------------|
| id             | uuid (PK)         | Identifiant unique                                   |
| tenant_id      | uuid              | Locataire propriétaire                               |
| customer_id    | uuid nullable     | Client lié (module Customers, phase 2)               |
| number         | string 20         | Numéro séquentiel ex : `ORD-00001`                  |
| status         | enum              | `draft` / `confirmed` / `fulfilled` / `cancelled`   |
| total_amount   | unsigned int      | Montant total en centimes (XOF)                      |
| currency       | char 3            | `XOF` par défaut                                     |
| note           | text nullable     | Commentaire libre                                    |
| performed_by   | uuid nullable     | Utilisateur qui a fait la dernière action            |
| fulfilled_at   | timestamp nullable| Date/heure de livraison                              |
| cancelled_at   | timestamp nullable| Date/heure d'annulation                              |

**Contrainte** : `(tenant_id, number)` est unique.

#### Méthodes

```php
$order->isDraft()     // status === 'draft'
$order->isConfirmed() // status === 'confirmed'
$order->isFulfilled() // status === 'fulfilled'
$order->isCancelled() // status === 'cancelled'

$order->canBeConfirmed()  // isDraft()
$order->canBeFulfilled()  // isConfirmed()
$order->canBeCancelled()  // isDraft() || isConfirmed()
```

#### Relations

```php
$order->lines // HasMany OrderLine
```

---

### OrderLine

Fichier : `app/Modules/Orders/Models/OrderLine.php`

| Colonne          | Type           | Description                                        |
|------------------|----------------|----------------------------------------------------|
| id               | uuid (PK)      | Identifiant unique                                 |
| order_id         | uuid (FK)      | Commande parente (cascade delete)                  |
| tenant_id        | uuid           | Index pour isolation multi-tenant                  |
| product_id       | uuid           | Produit commandé                                   |
| variant_id       | uuid nullable  | Variante si applicable                             |
| sku              | string 100     | **Snapshot** du SKU au moment de la commande       |
| name             | string 200     | **Snapshot** du nom au moment de la commande       |
| quantity         | unsigned small | Quantité commandée                                 |
| unit_price_cents | unsigned int   | Prix unitaire en centimes au moment de la commande |

**Important** : les champs `sku` et `name` sont des snapshots immuables — ils ne changent pas si le produit est renommé ultérieurement.

#### Méthodes

```php
$line->lineTotalCents() // quantity * unit_price_cents
```

---

## OrderService

Fichier : `app/Modules/Orders/Services/OrderService.php`

Dépendances injectées : `StockService`, `SerializedAllocationService` (module Inventory), `AuditService`, `WarrantyService` (module Warranties), `DigitalService` (module Digital).

### create()

```php
public function create(array $data, string $tenantId, string $userId): Order
```

- Crée une commande en statut **draft**
- Génère le numéro séquentiel `ORD-XXXXX`
- Résout les prix depuis le catalogue si `unit_price_cents` n'est pas fourni
- **Aucun impact sur le stock** à ce stade

Paramètres `$data` :
```php
[
    'items' => [
        [
            'product_id'       => 'uuid',
            'variant_id'       => 'uuid|null',
            'quantity'         => 3,
            'unit_price_cents' => 25000, // optionnel, utilise le prix catalogue sinon
        ],
    ],
    'customer_id' => 'uuid|null', // optionnel
    'note'        => 'string|null',
]
```

---

### confirm()

```php
public function confirm(Order $order, string $userId): Order
```

- Transition : `draft` → `confirmed`
- **Réserve le stock** de chaque ligne **stockable** via `StockService::reserve()`
- **Produit non stockable (RC-5E)** : `stock_tracking=none` (service/digital) → **aucune réservation**
  (`Product::isStockable()` fait autorité ; sinon `confirm` échouerait sur un stock à 0)
- **Produit sérialisé (RC-5C)** : réserve d'abord des **unités précises** (IMEI/VIN) via
  `SerializedAllocationService::allocate()` — `in_stock → reserved`, rattachées à la ligne ; l'allocation
  unitaire fait autorité (erreur claire avant le contrôle agrégé) et le verrou lecture interdit la
  double-réservation concurrente
- Lance `OrderStateException` si la commande n'est pas en `draft`
- Lance `InsufficientUnitsException` (RC-5C) si pas assez d'unités sérialisées disponibles
- Lance `InsufficientStockException` si stock agrégé insuffisant pour une ligne
- Lance `StockLockException` (503) si verrou Redis non acquis

---

### fulfill()

```php
public function fulfill(Order $order, string $userId): Order
```

- Transition : `confirmed` → `fulfilled`
- **Consomme le stock** réservé : appelle `StockService::moveOut()` + `StockService::release()` pour chaque ligne
- **Produit sérialisé (RC-5C)** : les unités réservées passent `reserved → sold` (`sold_at` horodaté) et
  sont **rattachées au client** (`SerializedAllocationService::markSold()`)
- **Produit non stockable (RC-5E)** : ni libération ni sortie de stock pour `stock_tracking=none`
- **Garantie (RC-5D)** : après la vente, `WarrantyService::issueForOrder()` génère les contrats de
  garantie pour les lignes dont le produit porte une politique (un contrat par unité sérialisée, sinon
  par ligne ; `ends_at` = `fulfilled_at` + durée)
- **Digital (RC-5E)** : `DigitalService::issueForOrder()` accorde les **droits d'accès** (download/license)
  au client pour les lignes digitales
- Positionne `fulfilled_at` sur l'heure courante
- Lance `OrderStateException` si la commande n'est pas en `confirmed`

---

### cancel()

```php
public function cancel(Order $order, string $userId): Order
```

- Transition : `draft|confirmed` → `cancelled`
- Si `confirmed` : libère les réservations via `StockService::release()` pour chaque ligne
- **Produit sérialisé (RC-5C)** : les unités réservées repassent `reserved → in_stock` et perdent leurs
  rattachements commande/client (`SerializedAllocationService::release()`)
- **Produit non stockable (RC-5E)** : rien à libérer (aucune réservation n'avait été posée)
- Si `draft` : aucun impact sur le stock
- Positionne `cancelled_at` sur l'heure courante

---

### Unités sérialisées rattachées (RC-5C)

`SerializedAllocationService` (module Inventory) gère le lien **commande ⇄ unité sérialisée ⇄ client**
pour les produits `stock_tracking=serialized` (téléphones IMEI, véhicules VIN…) :

| Méthode      | Appelée par | Effet sur l'unité (`inventory_units`)                              |
|--------------|-------------|--------------------------------------------------------------------|
| `allocate()` | `confirm`   | `in_stock → reserved` (FIFO date de réception), pose `order_id`/`order_line_id`, verrou lecture anti double-vente |
| `markSold()` | `fulfill`   | `reserved → sold`, `sold_at` + `customer_id`                       |
| `release()`  | `cancel`    | `reserved → in_stock`, efface `order_id`/`order_line_id`/`customer_id` |
| `forOrder()` | `GET /{id}/units` | unités rattachées à la commande (traçabilité vente/SAV)      |

Le stock **agrégé** reste mis à jour en miroir (RC-5B) — les vues de stock existantes restent cohérentes.

---

### findById() / paginate()

```php
public function findById(string $id, string $tenantId): Order           // Lance OrderNotFoundException si absent
public function paginate(string $tenantId, int $perPage, ?string $status): LengthAwarePaginator
```

---

## Exceptions

| Classe                  | Code HTTP | Description                                    |
|-------------------------|-----------|------------------------------------------------|
| `OrderNotFoundException`| 404       | Commande non trouvée pour ce tenant            |
| `OrderStateException`   | 422       | Action invalide pour le statut actuel          |
| `InsufficientUnitsException` | 422 | (de Inventory, RC-5C) Pas assez d'unités sérialisées disponibles |
| `InsufficientStockException` | 422  | (de Inventory) Stock agrégé insuffisant à la confirmation |
| `StockLockException`    | 503       | (de Inventory) Verrou Redis non acquis         |

---

## Flux de statuts

```
draft ──[confirm]──► confirmed ──[fulfill]──► fulfilled
  │                      │
  └──[cancel]──► cancelled ◄──[cancel]──────┘
```

---

## Impact sur le stock

| Action  | `quantity` | `reserved_quantity` |
|---------|-----------|---------------------|
| create  | —         | —                   |
| confirm | —         | +qty par ligne      |
| fulfill | −qty      | −qty (libération)   |
| cancel (draft) | — | —                  |
| cancel (confirmed) | — | −qty (libération) |

> **Produit sérialisé (RC-5C)** : en plus du miroir agrégé ci-dessus, chaque unité change de statut
> (`reserved` au confirm, `sold` au fulfill, retour `in_stock` au cancel) dans `inventory_units`.

---

## Tests

### Unit — `OrderServiceTest`
Fichier : `app/Modules/Orders/Tests/Unit/OrderServiceTest.php`

8 tests couvrant :
- Création draft et numérotation séquentielle
- Confirmation → réservation stock
- Livraison → consommation stock
- Annulation draft et confirmed
- Rejet double-confirm et fulfill sur draft
- Prix unitaire personnalisé

### Integration — `OrderApiTest`
Fichier : `app/Modules/Orders/Tests/Integration/OrderApiTest.php`

12 tests HTTP couvrant tous les endpoints avec authentification Sanctum.

### Modular — `OrderModuleTest`
Fichier : `app/Modules/Orders/Tests/Modular/OrderModuleTest.php`

4 tests de flux croisé Order ↔ Stock :
- Cycle complet (create → confirm → fulfill)
- Annulation confirmed → stock restauré
- Confirm sur stock insuffisant
- Anti-oversell : deux commandes concurrentes

### Integration — `SerializedAllocationTest` (RC-5C)
Fichier : `app/Modules/Orders/Tests/Integration/SerializedAllocationTest.php`

9 tests du lien commande ⇄ unité ⇄ client :
- confirm réserve des unités précises + miroir agrégé
- fulfill marque vendues + rattache le client
- cancel relâche les unités
- confirm échoue et annule tout si unités insuffisantes (atomicité)
- deux commandes ne peuvent réserver la même unité (anti double-vente)
- produit non sérialisé : aucune unité créée
- endpoint confirm → 422 si unités insuffisantes
- `GET /{id}/units` liste les unités rattachées + isolation multi-tenant (404)

---

## Conventions

- Numérotation : `ORD-00001`, `ORD-00002`, … (par tenant, séquentiel)
- Montants : toujours en centimes, jamais de décimales
- Snapshots : sku et name figés au moment de la commande
- Toutes les mutations passent par `OrderService` — jamais de mise à jour directe du modèle
