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
- **`revokeForOrderLine(tenantId, orderLineId)`** *(RC-5H)* — révoque les accès actifs d'une ligne
  **retournée** (appelé par `OrderReturnService::restock()`).
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

## Fichiers privés & téléchargement signé (RC-5I)

- **`digital_assets`** : fichier privé rattaché à un produit digital (disque `local`, taille, mime,
  checksum sha256, version). `DigitalAsset::toApiArray()` n'expose **jamais** `path`/`disk`.
- **`DigitalAssetService`** : `attach()` (upload), `forProduct()`, `signedLinksFor(entitlement)` (liens
  `URL::temporarySignedRoute('digital.download', +15 min)` pour les assets actifs d'un entitlement
  accessible), `findActiveAsset()`.
- **`GET /access/{token}`** renvoie `download_urls` (liens signés) ; **`GET /download/{token}/{asset}`**
  (middleware `signed`, hors auth) revérifie l'accessibilité de l'entitlement avant de streamer le fichier
  → la **révocation prime** sur un lien déjà émis.

## Portail client (RC-6C)

Page publique **`/portal`** (front) + endpoints publics throttlés :
- `POST /api/portal/digital/access` `{token}` — détail de l'achat (produit, vendeur, clé, **liens
  signés**) ; 404 jeton inconnu, 403 révoqué/expiré.
- `POST /api/portal/digital/request-links` `{email}` — envoie à ce client, **par le canal de chaque
  tenant concerné**, la liste de ses achats actifs avec liens magiques (`digital.portal_links`).
  **Toujours 200** (anti-énumération d'emails), throttle 5/min.
- `DigitalService::portalLink()` construit le lien magique (`FRONTEND_URL` + `/portal?token=…`) —
  inclus dans l'email de livraison (`{{portal_link}}`).

## Limites V1 / suite

- Un entitlement **par ligne** (pas par exemplaire) ; pool de clés de licence non géré (RC-6E).
- Accès portail par jeton/email — pas de compte client à mot de passe (extension possible).
- À venir : journalisation fine des téléchargements, versions multiples d'asset, antivirus à l'upload.
