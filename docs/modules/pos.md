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
| expected_cash_cents  | int nullable       | À la clôture : `opening_float + cash_sales + mouvements`  |
| counted_cash_cents   | int nullable       | À la clôture : espèces physiquement comptées               |
| difference_cents     | int nullable       | À la clôture : `counted − expected` (écart signé)         |
| opened_by / closed_by| uuid nullable      | Caissier ayant ouvert / clôturé                           |
| opened_at / closed_at| timestamp          | Horodatage                                                |
| notes                | text nullable      | Commentaire de clôture                                     |

#### Méthodes

```php
$session->isOpen()                 // status === 'open'
$session->netCashMovementsCents()  // Σ pay-ins − Σ pay-outs (RC-16)
$session->expectedCashNow()        // opening_float + cash_sales + netCashMovements
$session->orders()                 // HasMany Order (ventes rattachées)
$session->cashMovements()          // HasMany CashMovement (RC-16)
$session->opener()                 // BelongsTo User (opened_by)
```

> La colonne `cash_register_session_id` (nullable) est ajoutée à la table `orders` :
> elle relie une vente POS à sa session (null pour les commandes hors caisse).

### CashMovement (RC-16 — mouvements de caisse)

Fichier : `app/Modules/Pos/Models/CashMovement.php` · Table : `cash_movements`

Tout événement d'espèces **hors vente** touchant le tiroir pendant une session : un
**pay-in** (`direction = in` : ajout de fond, appoint) ou un **pay-out** (`direction = out` :
retrait, dépense, remboursement espèces). Gardé **hors** de `total_sales_cents` pour que le
rapprochement reste exact : `expected = fond + ventes espèces + pay-ins − pay-outs`.

| Colonne        | Type            | Description                                             |
|----------------|-----------------|---------------------------------------------------------|
| id             | uuid (PK)       | Identifiant                                             |
| tenant_id      | uuid            | Locataire (scope auto)                                  |
| session_id     | uuid            | Session de caisse rattachée                            |
| direction      | enum            | `in` (entrée) / `out` (sortie)                         |
| amount_cents   | int (>0)        | Montant **toujours positif** ; la direction porte le signe |
| reason         | string          | `float_add` · `withdrawal` · `expense` · `refund` · libre |
| note           | text nullable   | Commentaire                                            |
| order_id       | uuid nullable   | Commande liée (leg espèces d'un remboursement)         |
| performed_by   | uuid nullable   | Auteur                                                 |

---

## Service — `PosService`

Fichier : `app/Modules/Pos/Services/PosService.php`

```php
currentSession(string $tenantId, string $userId): ?CashRegisterSession
openSession(array $data, string $tenantId, string $userId): CashRegisterSession
checkout(CashRegisterSession $s, array $data, string $tenantId, string $userId): array       // ['order', 'payments', 'payment']
recordCashMovement(CashRegisterSession $s, array $data, string $tenantId, string $userId): CashMovement  // RC-16
refundSale(CashRegisterSession $s, Order $o, array $lines, string $reason, string $tenantId, string $userId, string $method = 'cash'): array  // ['return', 'movement'] — RC-16
closeSession(CashRegisterSession $s, array $data, string $tenantId, string $userId): CashRegisterSession
```

### Règles métier

- **Une seule session ouverte par caissier** : `openSession` lève `ValidationException`
  si une session est déjà ouverte pour cet utilisateur.
- **`checkout` est atomique** (`DB::transaction`) : create → confirm → fulfill de la
  commande, puis enregistrement du/des paiement(s). Toute erreur (ex. stock insuffisant →
  `InsufficientStockException`) **annule l'intégralité** de la vente (aucun mouvement de
  stock fantôme, aucun paiement orphelin).
- **Paiement mixte (split, RC-16)** : `checkout` accepte soit `method` (paiement unique,
  rétrocompatible), soit `payments: [{method, amount_cents, reference?}]`. Dans ce cas la
  **somme des legs doit égaler exactement le total** (sinon 422 + rollback). Seule la part
  **espèces** alimente `cash_sales_cents` (donc l'attendu de caisse).
- **Mouvements de caisse (RC-16)** : `recordCashMovement` enregistre un pay-in / pay-out.
  Un **pay-out ne peut excéder l'espèces disponible** (`expectedCashNow`) → 422. Répercuté
  immédiatement sur l'attendu.
- **Remboursement au comptoir (RC-16)** : `refundSale` délègue au `OrderReturnService`
  (create → approve → restock : réintègre le stock revendable, **défait** les artefacts
  sérialisés / garanties / accès digitaux). Le **leg espèces** d'un remboursement est un
  pay-out du tiroir ; un remboursement non-espèces (reversal Mobile Money) ne touche pas la caisse.
- **Prix résolus côté serveur** : `OrderService::create` ignore tout prix client et lit
  le prix depuis le catalogue (anti-falsification, OWASP API6).
- **Rapprochement** : `closeSession` calcule `expected = opening_float + cash_sales + mouvements`,
  stocke le `counted` saisi et l'écart signé `difference = counted − expected`.
- **Idempotence de clôture** : une session déjà `closed` ne peut être re-clôturée (422).

---

## Endpoints API

Préfixe : `/api/pos` · Middleware : `auth:sanctum` + `EnsureUserBelongsToTenant`.

| Méthode | URL                               | Action            | Description                                  |
|---------|-----------------------------------|-------------------|----------------------------------------------|
| GET     | `/api/pos/sessions`                     | `index`        | Liste paginée des sessions                   |
| GET     | `/api/pos/sessions/current`             | `current`      | Session ouverte du caissier (ou `null`)      |
| POST    | `/api/pos/sessions`                     | `open`         | Ouvrir une session (fond de caisse)          |
| POST    | `/api/pos/sessions/{id}/checkout`       | `checkout`     | Encaisser une vente (simple ou mixte)        |
| POST    | `/api/pos/sessions/{id}/close`          | `close`        | Clôturer + rapprochement                     |
| GET     | `/api/pos/sessions/{id}/movements`      | `movements`    | Mouvements de caisse de la session (RC-16)   |
| POST    | `/api/pos/sessions/{id}/cash-movement`  | `cashMovement` | Pay-in / pay-out du tiroir (RC-16)           |
| POST    | `/api/pos/sessions/{id}/refund`         | `refund`       | Remboursement au comptoir (RMA + leg caisse) (RC-16) |
| GET     | `/api/pos/orders/{orderId}/receipt`     | `receipt`      | Ticket de caisse structuré (RC-19)           |

### Exemple — checkout (paiement mixte)

```http
POST /api/pos/sessions/{id}/checkout
{
  "items": [{ "product_id": "uuid", "variant_id": "uuid|null", "quantity": 2 }],
  "customer_id": "uuid|null",
  "payments": [                                    // OU "method"/"reference" (paiement unique)
    { "method": "cash",         "amount_cents": 30000 },
    { "method": "mobile_money", "amount_cents": 20000, "reference": "WAVE-42" }
  ]
}
→ 201 { "data": { "order": {…fulfilled}, "payments": [{…},{…}], "payment": {…1er leg}, "session": {…} } }
→ 422 payments: "La somme des paiements (40000) doit égaler le total de la vente (50000)."
→ 422 { "message": "Stock insuffisant pour finaliser la vente." }
```

### Exemple — mouvement de caisse & remboursement

```http
POST /api/pos/sessions/{id}/cash-movement
{ "direction": "out", "amount_cents": 20000, "reason": "withdrawal", "note": "dépôt banque" }
→ 201 { "data": { "movement": {…}, "session": {…expected recalculé} } }
→ 422 amount_cents: "Retrait supérieur au fond de caisse disponible."

POST /api/pos/sessions/{id}/refund
{
  "order_id": "uuid",
  "lines": [{ "order_line_id": "uuid", "quantity": 2, "condition": "resalable" }],
  "reason": "Client insatisfait",
  "refund_method": "cash"                           // défaut cash ; non-espèces = pas de leg tiroir
}
→ 201 { "data": { "return": {number, refund_amount_cents}, "movement": {…out|null}, "session": {…} } }
```

---

### Idempotence du checkout & synchronisation offline (RC-22)

Le POS génère un **id client AVANT la tentative** d'encaissement (`crypto.randomUUID()`), envoyé
en `X-Idempotency-Key`. La file offline (mobile) réutilise ce même id à CHAQUE retry :

- serveur : la clé est stockée sur la commande (`orders.pos_reference`, **unique par tenant**) ;
  une clé déjà vue → la vente existante est renvoyée telle quelle (commande + paiements),
  **même si la session a été clôturée entre-temps** (resync du lendemain) ;
- course entre deux rejeux : la contrainte unique tranche, le perdant renvoie la vente du gagnant ;
- sans clé : comportement historique inchangé (client tiers/API).

**Statuts de synchronisation** (mapping) : côté client la file offline porte l'état
`pending_sync` (vente en file, PAS encore d'effet serveur : stock/caisse intacts) →
`synced` = la commande existe côté serveur (`pos_reference` posé), retirée de la file ;
`failed_sync` = erreur métier au rejeu (ex. 422), la vente reste en file pour arbitrage.
Côté serveur, les états métier restent ceux des commandes (`draft/confirmed/fulfilled/cancelled`),
des retours (`pending/approved/restocked/rejected` ≙ refunded) et des sessions (`open/closed`).

> Lien comptabilité (P2) : chaque vente POS émettra un événement `PosSaleCompleted` consommé par
> l'outbox comptable ; `source_type=Order`, `source_id`, et `pos_reference` garantissent une
> écriture unique par vente. Voir docs/architecture/comptabilite-syscohada.md.

### Ticket de caisse (RC-19)

`ReceiptService::forOrder(Order $order): array` construit le **payload structuré** du ticket :
en-tête boutique (`tenant->name` + `settings.address`/`phone`/`currency`), lignes (nom, SKU, qté,
PU, total), **tous les paiements** (y compris les legs d'un paiement mixte RC-16, avec référence
Mobile Money), totaux, caissier, libellé de session. Le **rendu et l'impression sont côté client** :

- `PosReceipt.vue` — rendu ticket **80 mm** (monospace, préviewé en modal). Le style vit dans
  `receiptPrint.ts` (`RECEIPT_CSS`, source unique aperçu + impression).
- `printHtml()` — impression via **iframe cachée** (`window.print()` sans popup, retirée après).
- Desktop : bouton **Imprimer le ticket** (+ raccourci **F7**, réimpression de la dernière vente).
- Mobile : bouton **Ticket** dans l'en-tête après une vente (réinitialisé à la clôture).

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

Fichier : `app/Modules/Pos/Tests/Integration/PosAdvancedTest.php` (7 tests — RC-16)

Couverture : paiement mixte (chaque leg enregistré, seule la part espèces alimente le
tiroir), split ne sommant pas au total → 422 + rollback, pay-out/pay-in répercutés sur
l'attendu, pay-out > espèces → 422, remboursement espèces (restock + pay-out tiroir),
remboursement non-espèces (tiroir intact), mouvement sur session clôturée → 422.

Frontend : `frontend/src/modules/pos/__tests__/PosView.spec.ts` (5 tests).

---

## Frontend

- Service : `frontend/src/modules/pos/services/posService.ts`
- Vue caissier (tablette) : `frontend/src/modules/pos/views/PosView.vue`
  — écran d'ouverture, recherche/scan produit, panier, encaissement, clôture avec écart.
- Route : `/pos` (`name: 'pos'`) · entrée menu **Caisse**.

Voir le [guide utilisateur](../user/pos.md).
