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

Dépendance injectée : `StockService` (module Inventory).

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
- **Réserve le stock** de chaque ligne via `StockService::reserve()`
- Lance `OrderStateException` si la commande n'est pas en `draft`
- Lance `InsufficientStockException` si stock insuffisant pour une ligne
- Lance `StockLockException` (503) si verrou Redis non acquis

---

### fulfill()

```php
public function fulfill(Order $order, string $userId): Order
```

- Transition : `confirmed` → `fulfilled`
- **Consomme le stock** réservé : appelle `StockService::moveOut()` + `StockService::release()` pour chaque ligne
- Positionne `fulfilled_at` sur l'heure courante
- Lance `OrderStateException` si la commande n'est pas en `confirmed`

---

### cancel()

```php
public function cancel(Order $order, string $userId): Order
```

- Transition : `draft|confirmed` → `cancelled`
- Si `confirmed` : libère les réservations via `StockService::release()` pour chaque ligne
- Si `draft` : aucun impact sur le stock
- Positionne `cancelled_at` sur l'heure courante

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
| `InsufficientStockException` | 422  | (de Inventory) Stock insuffisant à la confirmation |
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

---

## Conventions

- Numérotation : `ORD-00001`, `ORD-00002`, … (par tenant, séquentiel)
- Montants : toujours en centimes, jamais de décimales
- Snapshots : sku et name figés au moment de la commande
- Toutes les mutations passent par `OrderService` — jamais de mise à jour directe du modèle
