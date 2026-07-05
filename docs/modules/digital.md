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
| unit_index       | int (déf. 1)   | *(RC-7D)* rang de l'exemplaire (1..N) dans sa ligne  |
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
- **Sécurité upload (RC-9 F-7)** : liste blanche d'extensions (`config/digital.php → upload.
  allowed_extensions` — html/svg/js exclus), taille max configurable, nom de fichier **assaini**, MIME
  dérivé du **contenu** (jamais la valeur client). Le téléchargement force une pièce jointe.
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

## Un accès par exemplaire (RC-7D)

Une ligne de **qty N** accorde désormais **N entitlements** (un `access_token` — et une clé de licence —
distincts par exemplaire), au lieu d'un seul par ligne. `unit_index` (1..N) trace le rang.

- **Émission** (`DigitalService::issueForOrder`) : boucle sur la quantité ; **idempotente par exemplaire**
  (ne crée que les rangs manquants → un `fulfill` rejoué n'ajoute rien).
- **Licences** : chaque exemplaire **consomme une clé du pool** (FIFO), ou en génère une selon la
  politique d'épuisement — inchangée, mais appliquée par unité.
- **Retour au prorata** (`revokeDownToActive`) : au restock, on ne conserve actifs que
  `quantité − cumul retourné` accès (les plus anciens d'abord). Un retour partiel révoque autant
  d'accès que d'exemplaires rendus ; le client garde l'accès des exemplaires qu'il conserve.
  Remplace l'ancien comportement « tout ou rien » porté par la ligne (RC-5H).
- **Front** : le panneau « Accès digital » de la commande affiche le n° d'exemplaire quand la ligne en
  compte plusieurs ; le portail « mes achats » liste un accès (lien magique) par exemplaire.

## Comptes clients du portail (RC-7C)

Troisième mode d'accès, **au choix** du client, en plus du jeton et du lien magique.

- **`portal_accounts`** — compte **global** (hors multi-tenant, rapproché par email) :
  `email` (unique), `password` (hashé), `verification_code` / `verification_expires_at`,
  `verified_at`, `last_login_at`. Le modèle est **`Authenticatable` + `HasApiTokens`** (jeton Sanctum
  propre) et **`isSuperAdmin(): bool => false`** (neutralise `TenantScope` qui l'interroge sur tout
  utilisateur authentifié).
- **Vérification par code obligatoire avant login** : on ne peut pas revendiquer l'email d'autrui.
  À l'inscription, un code à 6 chiffres (validité **30 min**) part **par le canal du/des vendeur(s)**
  connaissant cet email (`portal.verify_code`). Réponse **générique** (anti-énumération).

| Méthode + URL (`api/portal`) | Auth | Throttle | Description |
|---|---|---|---|
| `POST /register` `{email,password}` | — | 3/10 min | Crée/rafraîchit le compte, envoie le code. Réponse générique. |
| `POST /verify` `{email,code}` | — | 5/10 min | Valide le code (et son expiration) → `verified_at`. |
| `POST /login` `{email,password}` | — | 10/min | **Refusé si non vérifié** ; sinon renvoie un **token Sanctum** du `PortalAccount` (jamais un user tenant). |
| `GET /my-purchases` | `auth:sanctum` | — | Achats digitaux **actifs, multi-vendeurs**, avec liens magiques (`product_name`, `seller_name`, `granted_at`, `portal_link`). |

**Front** — bloc « Mon compte » dans `/portal` (`DigitalPortalView.vue`) : onglets *Se connecter /
Créer un compte*, étape de saisie du code, liste des achats. Session persistée (`localStorage`
`portal_token`), via une **instance axios dédiée** (le client partagé redirige vers le login opérateur
sur 401). i18n **FR+EN** (`portal.account.*`).

**Cycle de vie du token (RC-8)** — le token portail est émis avec l'ability `portal` **et une
expiration de 30 j** (plafond global de secours dans `config/sanctum.php`) ; **`POST /api/portal/logout`**
le révoque côté serveur (le `logout()` du front l'appelle avant de purger le `localStorage`).

## Pool de clés éditeur (RC-6E)

- `license_pool_keys` : clés importées (`POST /products/{id}/license-keys`, doublons ignorés, limite
  d'import **par plan** — `config/digital.php`), consommées **FIFO** à la vente (verrou), rattachées à
  l'entitlement. `GET …/license-keys/summary` : disponibilité + politique.
- **Épuisement** : surcharge tenant `settings['license_pool_exhaustion']` → config par plan → défaut
  `generate` (repli génération + alerte `digital.pool_exhausted`) ; `block` →
  `LicensePoolExhaustedException` (422, commande reste confirmée).

## Limites V1 / suite

- Un entitlement **par exemplaire** (RC-7D) — révocation au prorata des retours partiels.
- Accès portail : jeton **ou** lien magique **ou** compte à mot de passe (RC-7C) — les 3 cohabitent.
- À venir : journalisation fine des téléchargements, versions multiples d'asset, antivirus à l'upload.
