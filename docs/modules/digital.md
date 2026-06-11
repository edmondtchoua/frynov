# Module Digital (Produits digitaux)

> **RC-5E — produits spéciaux.** Vente de produits **immatériels** (téléchargement / licence), sans
> stock, avec **droits d'accès** (entitlements) accordés au client à la vente. L'accès au contenu passe
> par un **jeton opaque** vérifié serveur — jamais par un chemin de fichier exposé.

## Prérequis levé — commande sans stock

`OrderService` ne réserve/sort plus de stock pour les lignes **`stock_tracking=none`** (services **et**
digital). Avant RC-5E, confirmer une commande de service/digital échouait (`InsufficientStockException`
sur un stock à 0). Désormais `confirm`/`fulfill`/`cancel` **sautent** les opérations de stock pour ces
lignes (`Product::isStockable()` fait autorité).

---

## Modèle — DigitalEntitlement (`digital_entitlements`)

| Colonne          | Type           | Description                                          |
|------------------|----------------|------------------------------------------------------|
| id               | uuid (PK)      | Identifiant                                          |
| tenant_id        | uuid           | Locataire                                            |
| product_id / variant_id | uuid (/null) | Produit digital vendu                            |
| order_id / order_line_id | uuid      | Commande / ligne d'origine                          |
| customer_id      | uuid nullable  | Client bénéficiaire                                  |
| fulfillment_type | string 16      | `download` \| `license` (snapshot)                   |
| access_token     | uuid **unique**| Jeton opaque d'accès (jamais un chemin de fichier)   |
| license_key      | string nullable| Clé générée si `license` (format `XXXX-XXXX-XXXX-XXXX`) |
| status           | string 16      | `active` \| `revoked` \| `expired`                   |
| granted_at / expires_at / revoked_at | timestamp | Cycle de vie (`expires_at` null = perpétuel) |

`isAccessible()` = `status === active` **et** non expiré.

---

## DigitalService — `app/Modules/Digital/Services/DigitalService.php`

- **`issueForOrder(Order, ?userId)`** — appelé par `OrderService::fulfill` : pour chaque ligne dont le
  produit se livre en `download`/`license`, **accorde un droit d'accès** (jeton + clé si licence).
  Idempotent (une ligne déjà dotée n'est pas réémise).
- **`revoke(entitlement, userId)`** — `status → revoked` (`revoked_at`). L'accès est immédiatement refusé.
- **`findByToken(tenantId, token)`** — résout un accès par son jeton (scopé tenant).
- **`forOrder(tenantId, orderId)`** — entitlements rattachés à une commande.

---

## Endpoints — `routes/api.php` (prefix `api/digital`, middleware `auth:sanctum` + `tenant`)

| Méthode + URL | Rôle | Description |
|---|---|---|
| `GET /orders/{orderId}/entitlements` | tous | Accès générés pour une commande — **sans secret** (ni jeton ni clé) |
| `GET /access/{token}` | tous | Vérifie l'accès ; **révèle le secret** (clé/lien) si actif, sinon **403** ; **404** si jeton inconnu pour le tenant |
| `POST /entitlements/{id}/revoke` | manager/admin | Révoque un accès |

> Sécurité : le secret (jeton/clé) n'apparaît **que** dans la réponse de `GET /access/{token}` pour un
> accès actif. Les listes scopées commande ne l'exposent jamais. Le jeton est isolé par tenant (404 sinon).

---

## Intégration commande

`OrderService::fulfill` appelle `DigitalService::issueForOrder` **après** `WarrantyService` (dans la
transaction, `fulfilled_at` posé). Dépendance injectée via `OrdersServiceProvider`.

---

## Tests — `app/Modules/Digital/Tests/Integration/DigitalEntitlementTest.php`

7 tests :
- produit digital **commandable sans stock** + entitlement actif (download, sans clé) ;
- produit **licence** → clé générée ;
- **service** commandable sans stock, **sans** entitlement (régression stock none) ;
- endpoint d'accès : actif → 200 + secret, après révocation → 403 ;
- isolation du jeton par tenant (404) ;
- liste commande **sans** secret ;
- produit physique → aucun entitlement.

---

## Limites V1 / suite

- **Pas de stockage de fichier réel** : `GET /access/{token}` renvoie le droit + la clé ; le **lien de
  téléchargement signé** (stockage privé, expiration) sera branché ultérieurement.
- Un entitlement **par ligne** (pas par exemplaire) ; pool de clés de licence non géré.
- Accès en **contexte tenant** (opérateur). Le portail **client** (lien signé, auth client) viendra ensuite.
- À venir : journalisation fine des téléchargements, expiration automatique, `digital_assets`.
