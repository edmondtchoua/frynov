# Module POS (Point de vente / Caisse)

## Vue d'ensemble

Le module **POS** gère l'encaissement au comptoir via des **sessions de caisse**
(`CashRegisterSession`). Il n'est **pas propriétaire** des commandes, du stock ni des
paiements : il **orchestre** les services existants (`OrderService`, `PaymentService`)
de sorte qu'un seul *checkout* produise une vente entièrement payée et déstockée, puis
la rattache à une session pour le **rapprochement de caisse** en fin de journée.

> 💰 Convention monétaire : tous les montants sont des **entiers en centimes** (×100).
> Voir [`docs/guides/conventions.md`](../guides/conventions.md).

---

## Modèles

### CashRegisterSession

Fichier : `app/Modules/Pos/Models/CashRegisterSession.php`
Table : `cash_register_sessions`

| Colonne              | Type               | Description                                                |
|----------------------|--------------------|------------------------------------------------------------|
| id                   | uuid (PK)          | Identifiant unique                                         |
| tenant_id            | uuid               | Locataire propriétaire (scope auto via `HasTenant`)        |
| warehouse_id         | uuid nullable      | Boutique / point de caisse                                 |
| label                | string nullable    | Libellé, ex. « Caisse 1 »                                  |
| status               | enum               | `open` / `closed`                                          |
| opening_float_cents  | int                | Fond de caisse à l'ouverture                               |
| total_sales_cents    | int                | Cumul des ventes de la session (tous moyens)              |
| cash_sales_cents     | int                | Cumul des ventes **espèces** uniquement                   |
| sales_count          | uint               | Nombre de ventes                                          |
| expected_cash_cents  | int nullable       | À la clôture : `opening_float + cash_sales`               |
| counted_cash_cents   | int nullable       | À la clôture : espèces physiquement comptées               |
| difference_cents     | int nullable       | À la clôture : `counted − expected` (écart signé)         |
| opened_by / closed_by| uuid nullable      | Caissier ayant ouvert / clôturé                           |
| opened_at / closed_at| timestamp          | Horodatage                                                |
| notes                | text nullable      | Commentaire de clôture                                     |

#### Méthodes

```php
$session->isOpen()           // status === 'open'
$session->expectedCashNow()  // opening_float_cents + cash_sales_cents
$session->orders()           // HasMany Order (ventes rattachées)
$session->opener()           // BelongsTo User (opened_by)
```

> La colonne `cash_register_session_id` (nullable) est ajoutée à la table `orders` :
> elle relie une vente POS à sa session (null pour les commandes hors caisse).

---

## Service — `PosService`

Fichier : `app/Modules/Pos/Services/PosService.php`

```php
currentSession(string $tenantId, string $userId): ?CashRegisterSession
openSession(array $data, string $tenantId, string $userId): CashRegisterSession
checkout(CashRegisterSession $s, array $data, string $tenantId, string $userId): array  // ['order', 'payment']
closeSession(CashRegisterSession $s, array $data, string $tenantId, string $userId): CashRegisterSession
```

### Règles métier

- **Une seule session ouverte par caissier** : `openSession` lève `ValidationException`
  si une session est déjà ouverte pour cet utilisateur.
- **`checkout` est atomique** (`DB::transaction`) : create → confirm → fulfill de la
  commande, puis enregistrement du paiement. Toute erreur (ex. stock insuffisant →
  `InsufficientStockException`) **annule l'intégralité** de la vente (aucun mouvement de
  stock fantôme, aucun paiement orphelin).
- **Prix résolus côté serveur** : `OrderService::create` ignore tout prix client et lit
  le prix depuis le catalogue (anti-falsification, OWASP API6).
- **Rapprochement** : `closeSession` calcule `expected = opening_float + cash_sales`,
  stocke le `counted` saisi et l'écart signé `difference = counted − expected`.
- **Idempotence de clôture** : une session déjà `closed` ne peut être re-clôturée (422).

---

## Endpoints API

Préfixe : `/api/pos` · Middleware : `auth:sanctum` + `EnsureUserBelongsToTenant`.

| Méthode | URL                               | Action            | Description                                  |
|---------|-----------------------------------|-------------------|----------------------------------------------|
| GET     | `/api/pos/sessions`               | `index`           | Liste paginée des sessions                   |
| GET     | `/api/pos/sessions/current`       | `current`         | Session ouverte du caissier (ou `null`)      |
| POST    | `/api/pos/sessions`               | `open`            | Ouvrir une session (fond de caisse)          |
| POST    | `/api/pos/sessions/{id}/checkout` | `checkout`        | Encaisser une vente                          |
| POST    | `/api/pos/sessions/{id}/close`    | `close`           | Clôturer + rapprochement                     |

### Exemple — checkout

```http
POST /api/pos/sessions/{id}/checkout
{
  "items": [{ "product_id": "uuid", "variant_id": "uuid|null", "quantity": 2 }],
  "method": "cash",          // cash | mobile_money | card | transfer | cheque
  "customer_id": "uuid|null",
  "reference": "OM-12345"    // optionnel (réf. Mobile Money, etc.)
}
→ 201 { "data": { "order": {…fulfilled}, "payment": {…}, "session": {…tallies} } }
→ 422 { "message": "Stock insuffisant pour finaliser la vente." }
```

---

## Sécurité & permissions

- **Rôles autorisés à opérer la caisse** : `admin`, `manager`, `cashier`
  (vérifié dans `PosController::guard()` → 403 sinon).
- Le rôle **cashier** et les permissions `pos.open` / `pos.close` / `pos.sale` /
  `pos.refund` sont définis dans `RolesAndPermissionsSeeder`.
- **Isolation multitenant** : `CashRegisterSession` utilise `HasTenant` → `TenantScope`
  filtre automatiquement par `tenant_id`. Une session d'un autre locataire renvoie 404.

---

## Tests

Fichier : `app/Modules/Pos/Tests/Integration/PosSessionTest.php` (10 tests)

Couverture : ouverture (+ refus de double ouverture), `current`, checkout
(vente payée + déstockage + rattachement + cumuls), rollback sur stock insuffisant,
ventes non-espèces n'affectant pas l'attendu, clôture (attendu/compté/écart),
refus de re-clôture, RBAC (viewer → 403), isolation multitenant.

Frontend : `frontend/src/modules/pos/__tests__/PosView.spec.ts` (5 tests).

---

## Frontend

- Service : `frontend/src/modules/pos/services/posService.ts`
- Vue caissier (tablette) : `frontend/src/modules/pos/views/PosView.vue`
  — écran d'ouverture, recherche/scan produit, panier, encaissement, clôture avec écart.
- Route : `/pos` (`name: 'pos'`) · entrée menu **Caisse**.

Voir le [guide utilisateur](../user/pos.md).
