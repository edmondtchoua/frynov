# Module Warranties (Garanties)

> **RC-5D — produits spéciaux.** Politiques de garantie réutilisables + contrats générés
> automatiquement à la vente, rattachés au client, à la ligne de commande et — pour les produits
> sérialisés — à l'**unité vendue** (IMEI/VIN). Base du SAV (réclamations) à venir.

## Vue d'ensemble

Une **politique** (`warranty_policies`) décrit une couverture réutilisable (durée en mois + texte).
On l'**attache à un produit** (`products.warranty_policy_id`). À la **livraison** d'une commande
(`OrderService::fulfill`), un **contrat** (`warranty_contracts`) est émis automatiquement pour chaque
ligne dont le produit porte une politique active. La période est **figée à l'émission** :
`starts_at` (date de vente) + `duration_months` → `ends_at`.

---

## Modèles

### WarrantyPolicy — `app/Modules/Warranties/Models/WarrantyPolicy.php`

| Colonne          | Type            | Description                                   |
|------------------|-----------------|-----------------------------------------------|
| id               | uuid (PK)       | Identifiant                                   |
| tenant_id        | uuid            | Locataire propriétaire                        |
| name             | string 120      | Libellé (ex. « Garantie constructeur 12 mois ») |
| duration_months  | unsigned small  | Durée de couverture, en **mois** (unité V1)   |
| coverage         | text nullable   | Couverture / exclusions (texte libre)         |
| is_active        | bool            | Politique sélectionnable                      |

### WarrantyContract — `app/Modules/Warranties/Models/WarrantyContract.php`

| Colonne            | Type           | Description                                        |
|--------------------|----------------|----------------------------------------------------|
| id                 | uuid (PK)      | Identifiant                                        |
| tenant_id          | uuid           | Locataire                                          |
| warranty_policy_id | uuid           | Politique source                                   |
| product_id / variant_id | uuid (/null) | Produit / variante vendu                         |
| inventory_unit_id  | uuid nullable  | Unité sérialisée vendue (si applicable)            |
| order_id / order_line_id | uuid     | Commande / ligne d'origine                         |
| customer_id        | uuid nullable  | Client bénéficiaire                                |
| serial_value       | string nullable| **Snapshot** IMEI/VIN au moment de la vente        |
| starts_at / ends_at| timestamp      | Période figée (vente → vente + durée)              |
| status             | string 16      | `active` / `expired` / `void`                      |

`isActive()` = `status === active` **et** `ends_at` dans le futur.

---

## WarrantyService — `app/Modules/Warranties/Services/WarrantyService.php`

### issueForOrder(Order $order, ?string $userId): array

Appelé par `OrderService::fulfill` (dans la transaction). Pour chaque ligne dont le produit a une
politique **active** :

- **produit sérialisé** → un contrat **par unité vendue** (rattaché à `inventory_unit_id` +
  `serial_value`), et la période est **horodatée sur l'unité** (`warranty_started_at`/`warranty_ends_at`) ;
- **sinon** → **un contrat pour la ligne** (`inventory_unit_id` null).

**Idempotent** : une ligne déjà couverte (contrat existant) n'est jamais réémise.

### forOrder(string $tenantId, string $orderId): Collection

Contrats rattachés à une commande (traçabilité, alimente `GET /api/warranties/orders/{id}`).

---

## Endpoints — `routes/api.php` (prefix `api/warranties`, middleware `auth:sanctum` + `tenant`)

| Méthode + URL | Rôle | Description |
|---|---|---|
| `GET /policies` | tous | Liste des politiques (`?active_only=1`) |
| `POST /policies` | manager/admin | Crée une politique |
| `POST /products/{productId}/policy` | manager/admin | Attache (ou détache si null) une politique à un produit |
| `GET /orders/{orderId}` | tous | Contrats générés pour une commande (scopé tenant → 404 cross-tenant) |
| `GET /contracts/{id}/claims` | tous | Réclamations SAV d'un contrat (RC-5F) |
| `GET /orders/{orderId}/claims` | tous | Réclamations SAV rattachées à une commande (RC-5F) |
| `POST /contracts/{id}/claims` | manager/admin | Ouvre une réclamation SAV (RC-5F) |
| `POST /claims/{id}/transition` | manager/admin | Fait avancer une réclamation (RC-5F) |

> Pas de gate `module:` dédié : la garantie est transverse aux produits spéciaux.

---

## SAV — réclamations (RC-5F)

`WarrantyClaimService` gère les **réclamations** (`warranty_claims`) rattachées à un contrat.

**Ouverture gardée par la période** (`open()`) :
- contrat **`void`** → refus (`OutOfWarrantyException`, 422), même avec override ;
- contrat **expiré** (`ends_at` passé ou statut `expired`) → refus **sauf `override=true`**, qui ouvre la
  réclamation en la marquant `out_of_warranty=true` et **trace un audit** (`warranty.claim.out_of_period_override`) ;
- la réclamation reprend `customer_id` et `inventory_unit_id` du contrat (traçabilité vente/SAV).

**Cycle de vie** (`transition()`) : `open → in_repair → resolved | replaced | rejected`. Les trois
derniers sont **terminaux** (toute transition depuis un terminal → `ClaimStateException`, 422). Le passage
à un statut terminal pose `resolution` (défaut : `repair`/`replacement`/`rejected`), `resolved_at`, `resolved_by`.

Motifs (`reason`) : `defect`, `breakage`, `malfunction`, `other`.

---

## Intégration commande

`OrderService::fulfill` génère les contrats **après** la vente (stock sorti, unités marquées vendues,
`fulfilled_at` posé) afin que `starts_at` = date de vente et que les unités sérialisées soient déjà
rattachées à la ligne. Dépendance injectée : `WarrantyService` (via `OrdersServiceProvider`).

---

## Tests

### `WarrantyTest.php` — politiques + contrats (RC-5D)

7 tests :
- fulfill d'une commande sérialisée → un contrat par unité, rattaché au client, `ends_at` = vente + durée, période horodatée sur l'unité ;
- produit non sérialisé avec politique → un contrat par ligne ;
- produit sans politique → aucun contrat ;
- idempotence (réémission sans doublon) ;
- API : création de politique + rattachement produit ;
- API : liste des contrats d'une commande ;
- isolation multi-tenant (404).

### `WarrantyClaimTest.php` — SAV (RC-5F)

8 tests : ouverture sur contrat actif, parcours réparation → résolution, blocage d'un terminal,
contrat expiré refusé sans override / accepté avec override (tracé hors garantie), contrat `void` refusé,
liste par commande, motif invalide (422), isolation multi-tenant (404).

---

## Limites V1 / suite

- Durée exprimée en **mois** uniquement.
- Produit non sérialisé : **un contrat par ligne** (pas par exemplaire).
- SAV livré (RC-5F) ; à venir : `void` **automatique** sur retour/annulation, extensions de garantie,
  pièces/coûts de réparation, UI de gestion des politiques.
