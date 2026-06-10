# Changelog — Frynov ERP

Toutes les évolutions notables. Format inspiré de [Keep a Changelog](https://keepachangelog.com/),
versionnage [SemVer](https://semver.org/).

## [Non publié] — 🏷️ RC-5A : politique produit stock/livraison (socle produits spéciaux) (2026-06-10)

Branche `feature/catalog-product-stock-policy` (release `v1.0.0` → `rc.105`).
Fondation P0 des **produits spéciaux** (services non stockables, digital, sérialisé IMEI/VIN, garanties — RC-5B→E).

### Catalogue — politique serveur explicite (3 axes orthogonaux)
- Nouvelles colonnes `products.stock_tracking` (`none|aggregate|batch|serialized`, défaut `aggregate`) et
  `fulfillment_type` (`none|manual|delivery|download|license|appointment`, défaut `delivery`) — distinctes
  de `product_type` (nature commerciale). Index `(tenant_id, stock_tracking)`.
- **Data-migration** : les services existants → `none`/`manual` (non stockables). Autres produits
  inchangés (`aggregate`/`delivery`).
- **`Product`** : constantes `STOCK_TRACKING_*` / `FULFILLMENT_*`, type `digital` ; hook `booted()` qui
  **dérive** la politique du type à la création (service/digital → `none`) sur **tous** les chemins
  (API, duplication, seeders) ; `isStockable()` **fait autorité** (un service/digital n'est jamais
  stockable, même avec une donnée héritée `aggregate`) ; `isDigital()`, `isSerialized()`.
- **API** `POST/PATCH /catalog/products` accepte `product_type` (+ `digital`), `stock_tracking`,
  `fulfillment_type` (whitelist). `CatalogResource` expose `stock_tracking`, `fulfillment_type`,
  `is_stockable`, `is_serialized`.

### Tests
- **+6 tests** `ProductStockPolicyTest` (défauts par type, non-stockable service/digital, sérialisé
  honoré, autorité du type sur donnée héritée, `none` explicite). Catalog+Orders+Inventory+Reports **261 ✅**.

## [Non publié] — 🗓️ RC-1B : abonnement périodique (changePlan interval → +1 mois / +1 an) (2026-06-10)

Branche `feature/pricing-changeplan-interval` (release `v1.0.0` → `rc.104`).

### Billing — `SubscriptionService::changePlan`
- Nouveau paramètre **`interval`** (`monthly`|`yearly`, défaut mensuel, valeur hors whitelist →
  mensuel) : la **fin de période** est calculée `now()->addMonth()` (mensuel) ou `now()->addYear()`
  (annuel), au lieu d'un `addMonth()` **codé en dur**. La **périodicité est persistée** sur le nouvel
  abonnement (colonne `interval` du socle RC-0).
- **`AdminTenantController::changePlan`** accepte un `interval` optionnel (`in:monthly,yearly`).
- L'approbation d'un paiement manuel reste mensuelle par défaut — la **détection** de la périodicité
  d'après le montant encaissé arrive en RC-1C.

### Tests
- **+3 tests** `SubscriptionServiceTest` : défaut mensuel (+1 mois) ; annuel (+1 an + `interval`
  persisté) ; interval inconnu → repli mensuel. Billing + Platform **99 ✅**.

## [Non publié] — 💳 RC-1A : API pricing publique — périodicité annuelle + économie (2026-06-10)

Branche `feature/pricing-interval-public-api` (release `v1.0.0` → `rc.103`).
Débloque le toggle Mensuel/Annuel de la landing et de l'écran d'upgrade (RC-1D).

### Billing — `GET /api/public/pricing`
- **Whitelist `interval`** : accepte `monthly` **et `yearly`** (avant : `yearly` était silencieusement
  ramené au mensuel) ; toute autre valeur (`weekly`…) retombe sur `monthly`. La réponse expose
  désormais `interval` à la racine.
- **Économie annuelle** : sur `interval=yearly`, chaque prix porte `monthly_equivalent_minor`
  (`round(base/12)`), `savings_amount_minor` (`12×mensuel − annuel`, plancher 0) et `savings_pct`.
  Calculé à partir du **mensuel réel du marché** (pas d'un ratio figé). Plan gratuit → 0 sans
  économie fictive. En mensuel, ces champs sont absents.

### Tests
- `PublicPricingApiTest` : test « unsupported interval » **inversé** (yearly = supporté, renvoie
  l'annuel + l'économie) ; **+2 tests** (plan gratuit annuel à 0 ; périodicité hors whitelist
  `weekly` → mensuel, sans champ d'économie). Billing **46 ✅**.

## [Non publié] — 📦 RC-3A : socle stock multi-entrepôt (warehouse_id dans la clé de résolution) (2026-06-10)

Branche `feature/inventory-stock-foundation` (release `v1.0.0` → `rc.102`).
Prérequis des chantiers **grille de stock par variante** (RC-4) et **produits spéciaux** (RC-5).

### Inventaire — bug fondation corrigé
- **`StockService::findOrCreate`** intègre désormais **`warehouse_id`** dans sa clé de résolution
  (l'index unique DB est `tenant+warehouse+product+variant`). Avant : le param était ignoré → la
  méthode pouvait renvoyer le stock d'un **autre entrepôt** ou créer une ligne **`NULL` orpheline**
  (invisible des listes filtrées par site).
  - `warehouseId` fourni → ligne dans cet entrepôt ; omis → **entrepôt par défaut** du tenant
    (`is_default`, sinon le plus ancien) ; tenant sans entrepôt → ligne `NULL` (compat mono-site).
  - Nouveau `StockService::defaultWarehouseId(tenantId)`.
- **Entrée de stock manuelle** (`POST /inventory/stock/{id}/move-in`) accepte `warehouse_id`
  (vérifié : appartenance tenant + périmètre `WarehouseScope` → 404/403) et `unit_cost_cents`
  (alimente le **CMUP** perpétuel). `MoveStockRequest` étendu.
- **`CatalogController::initialStock`** : `warehouse_id` passé **dans** `findOrCreate` (au lieu d'un
  `update()` post-hoc qui pouvait violer la contrainte unique / déplacer une ligne existante) +
  garde d'appartenance tenant de l'entrepôt.

### Tests
- **+4 tests** `StockWarehouseFoundationTest` : une ligne distincte par entrepôt pour un même SKU
  (idempotent), résolution de l'entrepôt par défaut, repli `NULL` sans entrepôt, CMUP par entrepôt.
- Suites Inventory + Orders + Catalog vertes (228 ✅ / 2 skipped). Aucune régression.

## [Non publié] — 💰 RC-0 : socle Billing périodicité (prix annuels + colonnes abonnement) (2026-06-10)

Branche `feature/billing-rc0-foundation` (release `v1.0.0` → `rc.101`).

### Billing — fondation périodicité (mensuel/annuel)
- **`PlansSeeder`** : ajout des **prix annuels** (`interval='yearly'`, `base_amount_minor` = mensuel ×10,
  `extra_user` ×10) pour **tous les plans × marchés** (la colonne `plan_prices.interval` + l'unique
  `(plan_id, market_code, interval)` existaient déjà). 0 reste 0 (plan gratuit). Idempotent.
- **`subscriptions`** : nouvelles colonnes `interval` (défaut `monthly`), `currency`, `market_code`,
  `amount_paid_minor` → l'abonnement porte sa **périodicité** et la **trace du paiement** qui l'a
  activé (détection périodicité + proration à venir). Modèle `Subscription` (+ constantes `INTERVAL_*`).
- **Socle partagé** par les chantiers Périodicité (RC-1) et Proration (RC-2) — livré une seule fois.

### Plan de build
- Nouveau **`docs/decisions/pricing-catalog-build.md`** : plan vivant des 4 sous-chantiers (pricing
  périodicité/proration + stock variantes + produits spéciaux), défauts produit adoptés, bugs fondation
  débusqués (`StockService::findOrCreate` ignore `warehouse_id` ; l'API pricing force le mensuel), suivi par RC.

### Tests
- **+2 tests** `PlanYearlyPricingTest` (chaque mensuel a son annuel = ×10 ; l'abonnement porte `interval`,
  défaut mensuel). Backend **682** (680 ✅ / 2 skipped). DemoSeeder (full seed) vert.

## [Non publié] — 🛡️ UX-07 : garde anti-perte sur l'onboarding (2026-06-09)

Branche `feature/ux07-onboarding-guard` (release `v1.0.0` → `rc.100`).

### UX (gate UX/UI P1 — UX-07 complété)
- **`OnboardingView`** : câblage de `useUnsavedChanges` (déjà en place sur `ProductFormView` et
  `OrderCreateView`). La garde anti-perte (prompt `beforeunload` + confirmation `onBeforeRouteLeave`)
  s'active **dès que l'utilisateur a commencé l'assistant** (étape > 1, type d'entreprise ou nom saisi)
  et se **lève après un provisioning réussi** (pour ne pas bloquer la redirection finale vers le
  tableau de bord). Message i18n `onboarding.leaveConfirm` (FR+EN).
- **Gate UX/UI P1 — UX-07 : ✅** (les 3 formulaires critiques sont désormais protégés).

### Tests
- Frontend **262** ✅ · `vue-tsc` propre · `npm run i18n:check` vert (parité FR/EN, `OnboardingView` scannée).

## [Non publié] — ✅ Test : suite backend 100 % verte (fix `ImportModuleTest` adresse) (2026-06-09)

Branche `fix/import-address-test` (release `v1.0.0` → `rc.99`).

### Tests
- **`ImportModuleTest::customer_import_stores_…`** : l'assertion attendait l'adresse client importée
  sous forme de **string**, alors que `Customer.address` est une **colonne json `{street,city,zip,country}`**
  (cast `array`) — l'import alimente correctement le champ `street`. Assertion alignée sur le modèle
  canonique (+ test renommé `…_structured_address_…`). **Aucun changement de code de production.**
- Résultat : **backend 680 → 678 ✅ / 2 skipped / 0 échec** (suite entièrement verte).

## [Non publié] — 🐛 Fix : onboarding étape 6 — URL de provisioning (2026-06-09)

Branche `fix/onboarding-provision-url` (release `v1.0.0` → `rc.98`).

### Correctif
- **`OnboardingView`** : l'appel de provisioning ciblait `'/workspace/provision'` (sans préfixe `/api`),
  donc partait vers le serveur Vite (`localhost:5173/workspace/provision`) → **404** et « Une erreur est
  survenue » à l'étape 6. Corrigé en **`'/api/workspace/provision'`** (route backend
  `WorkspaceController::provision`, conforme à toutes les autres requêtes `/api/...`). Bug pré-existant.
- Scan complet du front : **aucune autre requête** ne manquait le préfixe `/api`.

## [Non publié] — 💳 P6 : sélecteur de paiement par marché au checkout commercial (frontend) (2026-06-09)

Branche `feature/p6-commercial-selector` (release `v1.0.0` → `rc.97`).

### UX (paiements) — P6-2 commercial complet (front + back)
- **`OrderDetailView`** — modal « Enregistrer un paiement » : le sélecteur de méthode propose désormais
  les **moyens spécifiques du marché** (Wave/Orange Money/MTN MoMo/M-Pesa/virement/carte…) avec leur
  **mode** (validation manuelle / sur devis), récupérés via `fetchPublicPaymentMethods`. À la validation,
  le moyen est posté en **`provider`** (le backend en dérive la catégorie canonique `Payment.method` —
  rc.96). **Dégradation gracieuse** : si l'API est injoignable, le `<select>` canonique d'origine reste
  affiché et fonctionnel (compat totale). Réutilise l'i18n `billing.payMethods.*` (aucune nouvelle clé).
- `RecordPaymentPayload` : `method` rendu optionnel + champ `provider`.

### Tests
- Frontend **262** ✅ (OrderDetailView spec inclus, repli gracieux) · `vue-tsc` propre · `npm run i18n:check` vert.

### Bilan P6
- **P6-1→P6-4 livrés** : socle marché↔moyens, affichage abonnement + commercial (front+back), infra
  passerelle + webhooks (inerte), adaptateur de référence Flutterwave. **Seule l'activation d'un PSP réel
  reste une décision fondateur** (clé + flag). DoD « chaque devise = un flux ou une mention » satisfait.

## [Non publié] — 💳 P6 : checkout commercial — moyen spécifique par marché (backend) (2026-06-09)

Branche `feature/p6-commercial-provider` (release `v1.0.0` → `rc.96`).

### Paiements — enregistrement d'un moyen spécifique
- **`PaymentMethodCatalog`** : pont `provider` (moyen marché : wave/orange_money/mtn_money/mpesa/
  bank_transfer/card/cash) → **catégorie canonique `Payment.method`** (mobile_money/transfer/card/cash).
- **`POST /api/payments`** accepte désormais un champ optionnel **`provider`** (alternatif à `method`) :
  il dérive la catégorie canonique `Payment.method` **et** trace le provider en `reference` (si vide).
  L'enum `Payment.method` est **inchangé** ; la compat ascendante (`method` direct) est préservée.
  Permet à tout checkout commercial d'offrir un moyen précis par marché sans churn de schéma.

### Périmètre / suite
- Backend prêt. **Reste** : sélecteur de paiement frontend dans `PosView`/commande, consommant
  `/api/public/payment-methods` et postant `provider` (UI dense — incrément ultérieur).

### Tests
- **+5 tests** `PaymentProviderTest` (provider→catégorie + traçage, référence explicite conservée,
  `method` legacy, ni l'un ni l'autre → 422, mapping du catalogue). Backend **680** (677 ✅ / 2 skipped /
  1 échec pré-existant hors périmètre). Payments suite 50 ✅.

## [Non publié] — 💳 P6-4 : adaptateur de référence Flutterwave (inerte) (2026-06-09)

Branche `feature/p6-4-flutterwave-ref` (release `v1.0.0` → `rc.95`).

### Paiements — premier adaptateur PSP (référence, AUCUN appel réseau réel)
- **`FlutterwaveGateway implements PaymentGateway`** (couverture panafricaine : cartes + Mobile Money
  Nigeria/Ghana/Kenya/UEMOA…) : `initiate` (`POST /v3/payments` → intention + lien de redirection),
  `verify` (`/v3/transactions/verify_by_reference` → statut canonique succeeded/failed/pending),
  `refund` (`POST /v3/transactions/{ref}/refund`). Lit `base_url`/`secret_key` depuis `config/billing.php`.
- **Inerte par défaut** : enregistré dans `PaymentGatewayManager`, mais `get('flutterwave')` reste **refusé
  tant que `gateways_enabled=false`** et n'appelle rien tant que la `secret_key` n'est pas renseignée.
- **Activation (décision fondateur)** : renseigner `FLUTTERWAVE_SECRET_KEY` + `PAYMENT_GATEWAYS_ENABLED=true`
  + le secret webhook, puis basculer le `mode` du marché `manual`→`auto`. ⚠️ Vérifier le contrat exact
  contre la doc Flutterwave v3 avant production. Variante **Stripe/Paystack** = même patron.

### Tests
- **+5 tests** `FlutterwaveGatewayTest` (HTTP mocké via `Http::fake`/`fakeSequence`) : résolution par le
  manager (flag on), `initiate` pending+lien, échec prestataire, mapping de statut, remboursement.
  Backend **675** (672 ✅ / 2 skipped / 1 échec pré-existant hors périmètre).

## [Non publié] — 💳 P6-3 : infra passerelle de paiement + webhooks (inerte, post-1.0) (2026-06-09)

Branche `feature/p6-3-gateway-infra` (release `v1.0.0` → `rc.94`).

### Paiements — fondation PSP (AUCUN rail réel actif)
- **`config/billing.php`** : secrets webhooks par prestataire (vide ⇒ refusé) + **feature flag
  `gateways_enabled` (false par défaut)**. Corrige le code mort signalé par le cadrage (le middleware
  `VerifyWebhookSignature` lisait une config inexistante).
- **Abstraction `PaymentGateway`** (`code`/`initiate`/`verify`/`refund`) + **`ManualGateway`** (référence,
  approche A — aucun appel externe) + **`PaymentGatewayManager`** : `manual` toujours dispo ; tout rail
  réel **refusé tant que le flag est false**.
- **Webhook signé** : `PaymentWebhookController` (no-op, accuse réception) + routes
  `POST /api/webhooks/payments/{provider}` **enregistrées uniquement si le flag est actif** (hors auth,
  protégées par `webhook.signature`). Défaut sûr : route absente (404) tant que désactivé.

### P6-4 (intégration PSP réelle) — bloqué décision fondateur
- L'abstraction + l'adaptateur de référence sont en place. Une intégration **live** (Flutterwave/Paystack/
  Stripe…) exige des **décisions fondateur** (PSP cible, marché prioritaire, comptes marchands) et des
  **secrets** non disponibles → **non implémentable en code à ce stade**. Le socle est prêt à l'accueillir.

### Tests
- **+9 tests** : `PaymentGatewayTest` (manuel toujours dispo, rail réel refusé flag off, inconnu rejeté,
  manuel sans appel externe) · `PaymentWebhookInfraTest` (signature valide/invalide/replay/non-configuré,
  route absente quand désactivé). Backend **670** (667 ✅ / 2 skipped / 1 échec pré-existant hors périmètre).
  Payments suite 40 ✅.

## [Non publié] — 💳 P6-2 : affichage des moyens de paiement par marché (checkout abonnement) (2026-06-09)

Branche `feature/p6-2-checkout-display` (release `v1.0.0` → `rc.93`).

### UX (paiements) — moitié visible du DoD
- **`UpgradeView`** : nouvelle section **« Moyens de paiement disponibles »** alimentée par l'API P6-1
  (`fetchPublicPaymentMethods`), affichant chaque moyen du marché avec un **badge de mode** —
  *Validation manuelle* (`manual`) / *Sur devis* (`quote`) / *En ligne* (`auto`, futur). Remplace la
  mention éditoriale en dur par des données structurées (source de vérité = l'API). Dégradation
  gracieuse (section masquée) si l'API est injoignable. Rechargé au changement de marché.
- Service `fetchPublicPaymentMethods` (raw fetch public, même patron que `fetchPublicPricing`).
- i18n `billing.payMethods.*` (FR+EN) : titres, modes, libellés de méthodes (Wave/Orange Money/MTN
  MoMo/M-Pesa/virement/carte/espèces).

### Périmètre / suite
- Couvre le **checkout abonnement** (UpgradeView). Le **checkout commercial** (sélecteurs POS/commande +
  mapping fournisseur→`Payment.method`) reste un incrément ultérieur (touche des enums validés par tests).

### Tests
- **+2 tests** `publicPricingService.spec` (fetch payment-methods, dégradation). Frontend **262** ✅ ·
  `vue-tsc` propre · `npm run i18n:check` vert (parité FR/EN, `UpgradeView` scannée).

## [Non publié] — 💳 P6-1 : socle moyens de paiement par marché (zéro PSP) (2026-06-09)

Branche `feature/p6-1-payment-methods` (release `v1.0.0` → `rc.92`).

### Paiements (P6-1 — premier incrément, NO-GO respecté)
- **Table `market_payment_methods`** (calquée sur `plan_prices`, référence plateforme) : `market_code`,
  `country_code` (override, inutilisé au départ), `currency`, `method`, **`mode` = `auto`/`manual`/`quote`**,
  `is_active`, `display_order`. Modèle `MarketPaymentMethod`.
- **Seeder** des **10 marchés** (mêmes codes/devises que `PublicPricingController::MARKETS` — pas de 4ᵉ
  source de vérité) — **tout en `manual`/`quote`, AUCUN rail PSP réel** (NO-GO commercial respecté).
  Idempotent, branché dans `DatabaseSeeder`.
- **`GET /api/public/payment-methods?market=…|country=…`** : résout le marché comme `/public/pricing`
  (repli `global`) et renvoie les moyens + `has_auto` (faux à ce stade). **Matérialise le DoD** : chaque
  devise renvoie ≥1 moyen (flux manuel OU mention sur devis).
- L'endpoint réutilise `MARKETS`/`resolveMarket` du contrôleur pricing — source de vérité unique.

### Tests
- **+5 tests** `PublicPaymentMethodsTest` (chaque marché ≥1 moyen dans sa devise · `waemu`→wave+orange_money ·
  résolution par pays · repli `global`/USD · **aucun `auto` à ce stade**). Backend **661 tests**
  (658 ✅ / 2 skipped / 1 échec pré-existant hors périmètre). Billing suite 44 ✅.

### Suite
- P6-2 : brancher le checkout déclaratif (sélecteurs depuis l'API, mention manuel/sur-devis) + admin + i18n.

## [Non publié] — 🧬 Catalogue : duplication produit/catégorie — wizard frontend (P1, complet) (2026-06-09)

Branche `feature/catalog-duplication-wizard` (release `v1.0.0` → `rc.90`).

### UX (catalogue) — fonctionnalité P1 désormais complète (backend rc.89 + frontend rc.90)
- **`ProductDuplicationWizard.vue`** (modal `BaseModal`) : récupère l'aperçu serveur (sans persistance) et
  affiche, avant confirmation, le nom de la copie, le nombre de variantes/attributs, et des **tags
  catégorisés** « Régénéré » (SKU, code interne, slug…) / « Vidé » (barcode, GTIN) / « Non copié »
  (stock, mouvements, séries, garanties…). Confirmation → crée la copie en **brouillon**.
- Actions **« Dupliquer »** ajoutées dans `ProductShowPage` (→ redirige vers le nouveau produit) et
  `CategoryListView` (→ recharge la liste). `productService.duplicate*` (produit & catégorie, preview + create).
- Nouveau namespace i18n `catalog.duplicate.*` (FR+EN) — labels d'actions, intro, et map `field.*` des
  codes serveur. Le composant est entièrement bilingue (DoD i18n).

### Tests
- **+3 tests** `productService.spec` (duplicate-preview / duplicate produit, duplicate catégorie).
  Frontend **260** ✅ · `vue-tsc` propre · `npm run i18n:check` vert (parité FR/EN).

### Gate catalogue
- **Duplication produit/catégorie assistée : LIVRÉE** (politique serveur §5bis + endpoints + wizard + 11 tests).

## [Non publié] — 🧬 Catalogue : duplication produit/catégorie — backend (P1) (2026-06-09)

Branche `feature/catalog-duplication-backend` (release `v1.0.0` → `rc.89`).

### Catalogue (gate P1 — fonctionnalité absente jusqu'ici)
- **`ProductDuplicationService`** (politique serveur opposable §5bis) : le backend est source de vérité.
  - **Régénère** `sku` + `internal_barcode` (via `CatalogService`), les `sku` de variantes, le `slug` de catégorie.
  - **Vide** `barcode` + `gtin` ; **force** `status` à `draft` ; nom suffixé « (copie) ».
  - **Copie** la config catalogue (prix, coût, description, type, catégorie, fournisseur) + les **attributs/valeurs**
    (remap `product_id`) + les **variantes** (pivot attribut↔valeur re-lié).
  - **Ne copie JAMAIS** stock, mouvements, lots, séries/IMEI/VIN, garanties, licences. **Transactionnel** (rollback total).
- **Endpoints** (gardes RBAC `manager|admin|products.*`, isolation tenant, quota produits sur la création) :
  `POST /api/catalog/products/{id}/duplicate-preview` · `/duplicate` · `categories/{id}/duplicate-preview` · `/duplicate`.
  Audit : action `product.duplicated` (n'altère pas l'historique source).

### Tests
- **+8 tests** `ProductDuplicationTest` (preview sans persistance, régénération identifiants + status draft, variantes
  SKU régénérés/barcode vidé, attributs/valeurs + pivot re-lié, **non-copie stock/mouvements**, catégorie nœud-seul
  slug régénéré, **RBAC member → 403**, **isolation tenant → 404**). Suite Catalogue **81 ✅** (215 assertions).
  Backend **656 tests** (653 ✅ / 2 skipped / 1 échec pré-existant hors périmètre).

### Suite
- Frontend `ProductDuplicationWizard.vue` + actions « Dupliquer » (`ProductShowPage`/`CategoryListView`) → **rc.90**.

## [Non publié] — 🔒 Sécurité : warehouse-scoping des GET unitaires (Sprint 20) (2026-06-09)

Branche `feature/sec-warehouse-scoping` (release `v1.0.0` → `rc.86`).

### Sécurité (bloquant Go/No-Go levé)
- **Fuite par UUID connu fermée** : les accès à une ressource unique (`GET /api/orders/{id}`,
  `GET /api/payments/{id}`, `GET /api/orders/{orderId}/payments`) étaient **tenant-scopés mais pas
  warehouse-scopés** — un opérateur restreint à l'agence A pouvait ouvrir une commande/paiement de
  l'agence B en devinant/connaissant son UUID. Désormais le scoping par agence (`user_warehouses`)
  s'applique aussi aux ressources unitaires, à l'identique des listes (`index`/`paginate`).
- `OrderService::findById()` et `PaymentService::findOrFail()` acceptent un paramètre
  `?array $warehouseIds` (null = admin/manager non restreints ; `[]` = refus). Les contrôleurs passent
  `WarehouseScope::resolve($user, null)`. Les mutations `confirm`/`fulfill`/`cancel` (déjà gated
  `orders.manage`) sont aussi scopées par défense en profondeur (un rôle custom restreint resterait
  borné à ses agences). Réponse **404** (jamais de fuite d'existence), cohérente avec le reste.

### Tests
- **+4 tests** dans `WarehouseAccessScopingTest` (HTTP) : un opérateur restreint ne peut PAS afficher
  la commande / le paiement / les paiements de commande d'un autre site (404) mais voit les siens
  (200) ; un manager voit tout. Backend **648 tests** (645 ✅ / 2 skipped) après ajout.
- ⚠️ 1 échec **pré-existant et hors périmètre** : `ImportModuleTest::customer_import_stores_address_string_and_notes`
  (normalisation d'adresse client string vs array, issu du travail concurrent `1dd3af3`) — reproduit en
  isolation, sans rapport avec ce correctif. Suivi séparément.

## [Non publié] — 🎉 i18n : module Customers — COUVERTURE 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-customers` (release `v1.0.0` → `rc.85`).

### UX (i18n)
- **Module Customers internationalisé** (verrou de session concurrente levé) : `CustomerListView`
  (liste, recherche, table + `data-label` mobiles, modal créer/éditer, pagination) et
  `CustomerDetailView` (fiche, mode édition + adresse, commandes liées, sidebar stats, actions).
- Nouveau namespace `customers.*` (FR+EN). Mutualise massivement `common.*` (email/phone/notes/
  actions/status/amount/date/pageOf/previous/next/cancel/edit/update/create/saving) et réutilise
  `orders.status.*` pour les statuts de commande. `confirm()` de suppression et erreurs via `t()`.

### 🎉🎉 Jalon — couverture i18n 100 %
- **48/48 vues de module internationalisées FR+EN.** L'`ALLOWLIST` de la garde CI est désormais
  **vide** : l'anti-régression scanne toutes les vues sans exception. Le maintien à 100 % est garanti
  par la Definition of Done (toute vue naît bilingue) + la garde.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **48 ✅ / 0 🟡 / 0 ⬜ (100 %)**.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert (allowlist vide).

## [Non publié] — i18n : Inventaire restant — objectif traduisible atteint (UX-13) (2026-06-09)

Branche `feature/ux-i18n-inventory-rest` (release `v1.0.0` → `rc.84`).

### UX (i18n)
- **Inventaire à 100 %** : `BatchDeliveryView` (réception multi-produits : référence, recherche
  produit, quantités, validation, succès/erreur) et `MovementHistoryView` (carte résumé stock,
  filtre par type, timeline des mouvements, pagination).
- Extension `inventory.*` : `delivery.*` (formulaire de réception, pluriels `{count} article(s)`,
  message de succès `{count} mouvement(s)`) et `history.*` (titre, types `in/out/adjustment/return`,
  motifs `delivery/sale/return/loss/count/manual`, états vides). Réutilise les libellés stock
  existants (`quantity/reserved/available/lowThreshold/lowStock/backToStock`) et `common.*`
  (pageOf/previous/next/saving/delete). Maps `typeLabel`/`reasonLabel` converties via `t()`.

### 🎉 Jalon — objectif i18n traduisible atteint
- **46/48 vues internationalisées FR+EN (96 %).** Les 2 seules vues restantes (`CustomerListView`,
  `CustomerDetailView`) sont **exclues** (module Customers sous session concurrente). L'`ALLOWLIST`
  de la garde CI ne contient plus que ces 2 vues. Clôture à 100 % conditionnée à la levée du verrou.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **46 ✅ / 0 🟡 / 2 ⬜ (exclues)** (96 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Admin secondaire (UX-13) (2026-06-09)

Branche `feature/ux-i18n-admin-secondary` (release `v1.0.0` → `rc.83`).

### UX (i18n)
- **Admin secondaire internationalisé à 100 %** : `AdminDashboardView` (KPIs tenants/utilisateurs/
  modules, abonnements par statut, répartition par plan, derniers tenants, activité récente),
  `AuditLogView` (table du journal d'audit + pagination), `ModuleListView` (cartes modules, statuts,
  activations, actions afficher/masquer + sélecteur de statut).
- Extension du namespace `admin.*` : `dash.*` (KPIs, colonnes, libellés), `audit.*` (colonnes, vide),
  `modules.*` (activations, core, afficher/masquer, vide) et `moduleStatus.*` (active/beta/coming_soon/
  maintenance/disabled). Réutilise `admin.tenantStatus`, `billing.subStatus` et `common.*`
  (pageOf/previous/next/date/status/name/loading).
- Pluriels factorisés en `{count} activation(s)` ; statuts d'abonnement/tenant/module rendus via `$t`.
  Ratchet : 3 vues retirées de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **44 ✅ / 0 🟡 / 4 ⬜** (92 %).
  Reste **2 vues traduisibles** (Inventaire : `BatchDeliveryView`, `MovementHistoryView`) + 2 Customers exclues.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : module Billing (UX-13) (2026-06-09)

Branche `feature/ux-i18n-billing` (release `v1.0.0` → `rc.82`).

### UX (i18n)
- **Module Billing internationalisé à 100 %** : `BillingView` (abonnement, prix mensuel/annuel,
  essai/renouvellement, bandeaux essai & suspension, jauges d'usage utilisateurs/produits/commandes,
  table des paiements manuels) et `UpgradeView` (sélecteur de devise, 4 plans
  Découverte/Essentiel/Croissance/Business avec quotas et fonctionnalités, prix localisés, CTA).
- Nouveau namespace `billing.*` (FR+EN) : libellés de plan/quota/prix, maps `subStatus`/`method`/
  `payStatus`, `plan.{code}.{name,desc,f2..f5}`, note de bas de page. Mutualise `common.currencyName`
  et les libellés génériques `common.*` (date/amount/status/notes/loading/retry).
- Données de plans (`name`/`description`/`features`) et maps de statut converties via `t()` pour rester
  réactives au changement de langue. Corrige au passage 2 libellés FR sans accent (`Recommande`,
  `Illimite`) que la garde ne détectait pas. Ratchet : 2 vues retirées de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **41 ✅ / 0 🟡 / 7 ⬜** (85 %).
  Reste **5 vues traduisibles** (Admin secondaire ×3, Inventaire ×2) + 2 Customers exclues.

### Tests
- Frontend **257** inchangé (dont `UpgradeView.spec` — rendu FR identique) · `vue-tsc` propre ·
  `npm run i18n:check` vert.

## [Non publié] — i18n : Onboarding + namespace partagé geo.timezone.* (UX-13) (2026-06-09)

Branche `feature/ux-i18n-onboarding` (release `v1.0.0` → `rc.81`).

### UX (i18n)
- **`OnboardingView`** internationalisé (assistant 6 étapes : type d'entreprise, taille d'équipe,
  modules, besoins opérationnels, infos entreprise, confirmation). Nouveau namespace `onboarding.*`
  (FR+EN) : titres/sous-titres d'étapes, maps `businessType` (8), `teamSize` (4), `module` (7),
  `needs` (5), récap « Ce qui sera configuré », écrans provisionnement/erreur/succès, boutons de
  navigation et messages d'erreur.
- **Namespace partagé `geo.timezone.*`** (15 fuseaux, FR+EN) ajouté à `geo.*`. La vue **réutilise**
  `geo.country` (29 pays) et `common.currencyName` (17 devises) déjà mutualisés en rc.80 — zéro
  duplication des données pays/devise/fuseau.
- Les tableaux de données (`businessTypes`/`teamSizes`/`allModules`) convertis en `computed` via `t()`
  pour rester réactifs au changement de langue ; selects pays/devise/fuseau liés à `geo.*`/`common.*`.
  Paramètre local `t` (dans `selectedTypeName`) renommé `bt` pour ne plus masquer la fonction i18n `t`.
- Ratchet : `OnboardingView` retirée de l'`ALLOWLIST` de la garde.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **39 ✅ / 0 🟡 / 9 ⬜** (81 %).
  Reste **7 vues traduisibles** (Billing ×2, Admin secondaire ×3, Inventaire ×2) + 2 Customers exclues.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Paramètres + namespace partagé geo.* (UX-13) (2026-06-09)

Branche `feature/ux-i18n-settings` (release `v1.0.0` → `rc.80`).

### UX (i18n)
- **`SettingsView`** internationalisé (gros écran : 6 onglets — Entreprise, Équipe, Rôles, Abonnement,
  Intégrations, Notifications — + 4 modals : invitation, accès entrepôts, accès temporaire, mise à
  niveau/preuve de paiement). Nouveau namespace `settings.*` (FR+EN) avec sous-objets company/team/
  billing/invite/warehouse/temp/upgrade + maps de rôles, statuts d'abonnement et durées de session.
- **Namespace partagé `geo.*`** (pays par code ISO, 30 entrées) + **`common.currencyName` étendu**
  (+10 devises) — réutilisables par `OnboardingView` (rc.81).
- Constantes/maps FR (tabs, roleLabel, subStatusLabel) converties via `t()`. Ratchet : vue retirée de
  l'`ALLOWLIST`. La garde a débusqué 2 placeholders accentués oubliés (corrigés).

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **38 ✅ / 0 🟡 / 10 ⬜** (79 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Marketplace (UX-13) (2026-06-09)

Branche `feature/ux-i18n-marketplace` (release `v1.0.0` → `rc.79`).

### UX (i18n)
- **`MarketplaceListingsView`** internationalisé : en-tête, bannière d'alertes, onglets
  (connexions/alertes), table (plateforme, statut sync, fermeture auto), liste d'alertes,
  **modal créer/éditer** (plateforme, ID externe, URL, seuil, switches auto-close/reopen/price-sync)
  + confirmation de suppression & erreurs. Nouveau namespace `marketplace.*` (FR+EN, statuts de sync) ;
  ajout `common.yes`/`common.no` ; réutilise `common.*`, `reports.viewAlerts`, `catalog.productForm.optional`.
- Ratchet : vue retirée de l'`ALLOWLIST` (la garde a confirmé en débusquant un reliquat oublié).

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **37 ✅ / 0 🟡 / 11 ⬜** (77 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Import/Export — Assistant — module 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-import-wizard` (release `v1.0.0` → `rc.78`). **Module Import/Export entièrement internationalisé (2/2 vues).**

### UX (i18n)
- **`ImportWizardView`** internationalisé (assistant 5 étapes) : choix type & mode, téléchargement
  modèle (colonnes par entité), upload fichier, **mapping des colonnes** (champs ERP par entité),
  **prévisualisation/approbation** (stats, filtres de lignes, table, résumé, résultat) + erreurs.
  Étend `importExport.wizard.*` (steps, entityType, modeOpt, templateCols, fields, rowFilter, action,
  rowStatus) ; consts FR `entityTypes`/`importModes`/`templateColumns`/`availableFields`/`rowFilters`
  converties en `computed` via `t()`.
- Ratchet : vue retirée de l'`ALLOWLIST` → **module Import/Export complet**.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **36 ✅ / 0 🟡 / 12 ⬜** (75 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Import/Export — Historique (UX-13) (2026-06-09)

Branche `feature/ux-i18n-import-history` (release `v1.0.0` → `rc.77`).

### UX (i18n)
- **`ImportHistoryView`** internationalisé : en-tête (compteur, nouvel import, modèles/export par
  entité), filtres (type/statut), table, pagination, **modal de détail** (stats, résumé, méta) +
  confirmation d'annulation & erreurs. Nouveau namespace `importExport.*` (FR+EN) avec maps
  partagées **entity / mode / modeShort / status** (remplacent les constantes FR `ENTITY_LABELS`/
  `MODE_LABELS`/`STATUS_LABELS` via helpers `t()`) + `history.*`.
- Ratchet : vue retirée de l'`ALLOWLIST`. (`ImportWizardView` → prochaine RC, réutilisera les maps.)

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **35 ✅ / 0 🟡 / 13 ⬜** (73 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Point de vente (POS) (UX-13) (2026-06-09)

Branche `feature/ux-i18n-pos` (release `v1.0.0` → `rc.76`).

### UX (i18n)
- **`PosView`** internationalisé : ouverture de session (fond de caisse), terminal (recherche/scan,
  panier, moyen de paiement, encaissement), sélecteur de variante, **clôture & rapprochement**
  (espèces attendues/comptées, écart) + toasts & erreurs. Nouveau namespace `pos.*` (FR+EN) ;
  réutilise `common.total`/`cancel`/`loading`, `payments.method`/`methodLabel`.
- Ratchet : vue retirée de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **34 ✅ / 0 🟡 / 14 ⬜** (71 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Ventes Création de commande — module 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-orders-create` (release `v1.0.0` → `rc.75`). **Module Ventes entièrement internationalisé (4/4 vues).**

### UX (i18n)
- **`OrderCreateView`** internationalisé : sélecteur client (autocomplétion), lignes d'articles
  (picker produit/variante, prix, quantité, totaux), note, total & actions + validations & erreur de
  création. Namespace `orders.create.*` ; réutilise `orders.new`/`colItems`, `common.*`,
  `catalog.productForm.optional`.
- Ratchet : vue retirée de l'`ALLOWLIST` → **module Ventes (Liste/Création/Fiche/Retours) complet**.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **33 ✅ / 0 🟡 / 15 ⬜** (69 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Ventes Fiche commande & Retours (UX-13) (2026-06-09)

Branche `feature/ux-i18n-orders-detail-returns` (release `v1.0.0` → `rc.74`).

### UX (i18n)
- **`OrderDetailView`** internationalisé : en-tête (statut), table des lignes, méta (dates), panneau
  **Paiements** (solde, liste, modal d'enregistrement) + panneau **Livraison** + actions
  (confirmer/livrer/annuler) & erreurs. Namespace `orders.detail.*` ; réutilise `orders.status`,
  `payments.method`/`colMethod`/`colReference`, `deliveries.status`, `common.*`.
- **`ReturnsView`** internationalisé : en-tête, filtre statut, table (motif/résolution/statut), actions
  (approuver/remettre en stock/refuser) + modal de refus. Namespace `orders.returns.*` (+ libellés
  reason/resolution/status).
- **`common.total`** ajouté (mutualisé). Ratchet : 2 vues retirées de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **32 ✅ / 0 🟡 / 16 ⬜** (67 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Catalogue Fiche produit & Étiquettes — module 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-catalog-show-label` (release `v1.0.0` → `rc.73`). **Module Catalogue entièrement internationalisé (7/7 vues).**

### UX (i18n)
- **`ProductShowPage`** internationalisé (vue dense ~1360 lignes) : en-tête (type/statut/actions), 4 onglets
  (Vue d'ensemble, Variantes, Stock, Prix), cartes identification/prix, résumé & détail stock, table des
  variantes & mouvements, **2 drawers** (entrée stock, ajustement) + validations & erreurs. Namespace
  `catalog.productShow.*` ; réutilise `common.*`, `inventory.*`, `catalog.*`. Paramètres locaux `t`
  (typeLabel/mvtTypeLabel) renommés `type` pour ne plus masquer la fonction i18n.
- **`LabelPrintView`** internationalisé : sélection produits, configuration (format, options, copies),
  résumé & impression. Namespace `catalog.labelPrint.*`.
- **Ratchet garde** : les 2 vues retirées de l'`ALLOWLIST`. La garde a confirmé l'absence de texte FR
  accentué résiduel sur ces 2 vues denses.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **30 ✅ / 0 🟡 / 18 ⬜** (62 %). Catalogue 100 %.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Catalogue Attributs & Déclinaisons (UX-13) (2026-06-09)

Branche `feature/ux-i18n-catalog-attrs-variants` (release `v1.0.0` → `rc.72`).

### UX (i18n)
- **`AttributesView`** internationalisé : en-tête, état vide, cartes produit (compteur de déclinaisons,
  voir la fiche), aide « aucun attribut ». Namespace `catalog.attributes.*`.
- **`VariantsView`** internationalisé : en-tête (sous-titre paramétré), filtres (recherche, statut),
  état vide, table (colonnes, voir), pagination (`common.pageOf`). Namespace `catalog.variants.*` ;
  réutilise `common.*` + `catalog.colPrice/colCategory/status/noResults`. Libellé partagé
  `catalog.viewProductsList`.
- **Ratchet garde** : les 2 vues retirées de l'`ALLOWLIST` du gate i18n (désormais protégées).

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **28 ✅ / 0 🟡 / 20 ⬜** (58 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : garde CI dure (parité + texte FR en dur) (UX-13) (2026-06-09)

Branche `feature/ux-i18n-guard` (release `v1.0.0` → `rc.71`). **La Definition of Done i18n est désormais opposable automatiquement.**

### Gouvernance i18n (garde automatisée)
- **`npm run i18n:check`** + spec `src/i18n/__tests__/i18n-coverage.guard.spec.ts` — **gate CI dur**
  (ajouté à `ci-feature.yml` job *quality* et `ci-develop.yml` *Build Frontend*, + exécuté dans la
  suite vitest). Contrôles : **(a)** parité des clés `messages.fr`/`messages.en` ; **(b)** détection de
  **texte FR en dur** (caractère accenté) dans les `<template>` des vues de module hors **allowlist
  ratchet** (22 vues non traduites, à réduire) ; **(c)** anti-bitrot de l'allowlist.
- `messages` est désormais **exporté** depuis `i18n/index.ts` (pour la garde).
- **Reliquat débusqué immédiatement** par la garde : `WarehouseView` (libellés de devises en dur) →
  corrigé via le nouveau **`common.currencyName.*`** mutualisé (`WarehouseView` + `ProductFormView`,
  `catalog.productForm.currencyName` supprimé — déduplication).

### Tests
- Frontend **257** (254 + 3 specs de garde) · `vue-tsc` propre.

## [Non publié] — i18n : pagination unifiée + 11 vues partielles soldées (UX-13) (2026-06-09)

Branche `feature/ux-i18n-pagination` (release `v1.0.0` → `rc.70`). **Les 26 vues câblées i18n sont désormais toutes complètes** (0 reliquat connu).

### UX (i18n)
- **`common.pageOf`** (`Page {current} / {total}`) + **`common.deleteFailed`** introduits ; `admin.pageOf`
  promu vers `common` (doublon supprimé, `ManualPaymentView` migrée).
- **Pagination unifiée** via `common.pageOf` sur 9 vues : Promotion, Tenant, Supplier, Payment, Order,
  Delivery, Stock, Produits (l'audit avait manqué Produits/Stock — d'où le besoin de la garde CI).
- **`DeliveryListView`** : « ← Précédent / Suivant → » → `common.previous`/`next`.
- **`CountryRuleListView`** : `'Suppression impossible.'` → `common.deleteFailed` ; **`StockListView`** :
  `'Une erreur est survenue.'` → `common.genericError`.
- **`StockTransferView`** : confirmation d'expédition traduite (`inventory.shipTransfer*`/`ship`) ;
  paramètre local `t` renommé `tr` pour ne plus masquer la fonction i18n.
- **`StockReportView`** : « (top 10) » → `reports.top10`.

### Gouvernance i18n
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) mis à jour : **26 ✅ / 0 🟡 / 22 ⬜**.
  Prochaine brique : garde automatisée (gate CI dur, ratchet avec allowlist).

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : formulaire produit + tracker de couverture (UX-13) (2026-06-09)

Branche `feature/ux-i18n-catalog-productform` (release `v1.0.0` → `rc.69`).

### UX (i18n)
- **`ProductFormView`** internationalisé (plus grosse vue de l'app) : infos générales (SKU/code-barres/
  GTIN + aides), prix de base (devise, marge, stock initial), **builder d'axes de variation** (axes,
  valeurs, aperçu combinaisons, génération), table des déclinaisons, colonne latérale (statut,
  catégorie, étiquettes, expédition), **modal de désactivation de variante** (transfert/sortie/
  conservation) + validations & erreurs. Nouveau namespace `catalog.productForm.*` (FR+EN), incluant
  noms d'axes, libellés de devises et hints de statut. Notes d'inventaire **persistées** laissées en
  FR (source de vérité, exclues de l'i18n UI).

### Gouvernance i18n
- **Tracker vivant `docs/recette/i18n-coverage.md`** — décompte **exhaustif des 48 vues** (✅ complet /
  🟡 partiel / ⬜ à faire), issu d'un audit multi-agents. État réel : **15 ✅ / 11 🟡 / 22 ⬜**.
- **Definition of Done i18n** formalisée : toute vue créée/modifiée doit livrer FR+EN (parité de clés,
  zéro chaîne en dur) dans le même changement. Référencée depuis `i18n.md` et l'état-des-lieux.
- Découverte : motif **pagination « Page X / Y »** codé en dur dans 6 vues → quick win `common.pageOf`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Admin Plans & Paiements manuels (UX-13) (2026-06-09)

Branche `feature/ux-i18n-admin3` (release `v1.0.0` → `rc.68`). **Fin de l'internationalisation du back-office Admin.**

### UX (i18n)
- **`PlanListView`** internationalisé : cartes plan (visibilité Public/Privé, prix `/ mois`/Gratuit,
  limites usagers/produits/commandes/essai), **modal d'édition des limites** (libellés Nom, utilisateurs
  inclus, jours d'essai, produits, commandes, clients, boutiques, entrepôts, imports, appels API,
  stockage ; cases Actif/Public) + erreur d'enregistrement. Étend `admin.*` ; réutilise `common.*`.
- **`ManualPaymentView`** internationalisé : onglets de statut (En attente/Approuvés/Rejetés/Tous +
  badge), table (colonnes, méthode dont Virement, statuts), pagination, actions Approuver/Rejeter,
  **modal de rejet** (motif) + confirmation d'approbation & messages d'erreur. Étend `admin.*` ;
  réutilise `common.*` (amount, date, status, actions, view, previous/next, cancel, genericError).

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Admin Règles pays (UX-13) (2026-06-08)

Branche `feature/ux-i18n-countryrule` (release `v1.0.0` → `rc.67`).

### UX (i18n)
- **`CountryRuleListView`** internationalisé : en-tête, états (chargement/erreur/vide), table
  (colonnes, statuts Bloqué/Actif/Inactif, approbation, plans), modal créer/éditer (code pays,
  devise, fuseau, plans autorisés, cases) + erreurs & confirmation de suppression. Étend `admin.*`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Admin Promotions (UX-13) (2026-06-08)

Branche `feature/ux-i18n-admin2` (release `v1.0.0` → `rc.66`).

### UX (i18n)
- **`PromotionListView`** internationalisé : toolbar (compteur), table (colonnes, type de remise,
  statut, actions), pagination, et **modal créer/éditer** (code, description, type/valeur de remise,
  plans applicables, validité, utilisations max, activation) + validations & confirmation de
  suppression. Étend `admin.*` ; réutilise `common.*`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : fiche tenant (back-office Admin) (UX-13) (2026-06-08)

Branche `feature/ux-i18n-tenantdetail` (release `v1.0.0` → `rc.65`).

### UX (i18n)
- **`TenantDetailView`** internationalisé : en-tête (statut via `statusLabel`, suspendre/réactiver),
  cartes **Informations** + **Abonnement** (changement de plan), **Utilisateurs**, **Modules ERP**
  (activer/désactiver, Core). Étend `admin.*` ; réutilise `common.*` (name, email, status, createdAt,
  active/inactive, loading).

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : back-office Admin — liste des tenants (UX-13) (2026-06-08)

Branche `feature/ux-i18n-tenants` (release `v1.0.0` → `rc.64`).

### UX (i18n)
- **`TenantListView`** (back-office super-admin) internationalisé : filtres (recherche, statut, plan),
  table (colonnes, statut traduit via `statusLabel`), actions (Détails/Suspendre/Réactiver),
  pagination, confirmation de suspension. Nouveau namespace `admin.*` ; réutilise `common.*`
  (allStatuses, status, createdAt, actions, previous/next).
- *(`TenantDetailView` — 434 lignes — prévu à l'itération suivante.)*

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : profil utilisateur traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-profile` (release `v1.0.0` → `rc.63`).

### UX (i18n)
- **`ProfileView`** internationalisé : carte identité (rôles, super-admin), **infos personnelles**,
  **changement de mot de passe** (indicateur de force ×4), **sessions actives** (révocation +
  confirmation). Nouveau namespace `profile.*` (rôles + niveaux de force imbriqués) ; réutilise
  `common.*` et `auth.emailLabel`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Dashboard traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-dashboard` (release `v1.0.0` → `rc.62`).

### UX (i18n)
- **`DashboardView`** internationalisé : sous-titre, **cartes KPI** (×4 + tendance « % vs hier »),
  graphiques (CA, commandes récentes, top produits), bannière d'essai, **section modules** (badges
  Actif/Bientôt/Inactif), **actions rapides**. Nouveau namespace `dashboard.*` ; réutilise
  `orders.status` (libellés de commande), `inventory.units`, `common.*`, `nav.dashboard`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : module Auth traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-auth` (release `v1.0.0` → `rc.61`).

### UX (i18n)
- **`LoginView`** (connexion, mot de passe oublié, message d'expiration de session, erreurs
  401/403/réseau) et **`RegisterView`** (création d'espace, force du mot de passe ×4, CGU,
  validations) internationalisés.
- Nouveau namespace **`auth.*`** (FR + EN) : libellés, placeholders, messages d'erreur, niveaux de
  force (`strength`).

### Tests
- Frontend **254** (+1 : namespace `auth` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — i18n : périodes fiscales — module Inventaire 100 % traduit (UX-13) (2026-06-08)

Branche `feature/ux-i18n-fiscal` (release `v1.0.0` → `rc.60`).

### UX (i18n)
- **`FiscalPeriodView` (Périodes fiscales)** internationalisé : en-tête, cartes (types ×3, statuts ×3,
  intégrité, actions), volet **création** et volet **verrouillage irréversible** (avertissement +
  raison). `typeLabel`/`statusLabel` via `t()` (`fiscalType` / `fiscalStatus`).
- **🎯 Module Inventaire 100 % traduit** (6 vues : Entrepôts, Stock, Alertes, Ajustements, Transferts,
  Périodes — namespace `inventory.*`).

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : transferts inter-entrepôts traduits FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-transfer` (release `v1.0.0` → `rc.59`).

### UX (i18n)
- **`StockTransferView` (Transferts inter-entrepôts)** internationalisé : en-tête + compteur, filtre
  de statut, table (colonnes, statuts ×8, actions Expédier/Réceptionner/Résoudre), et **3 volets**
  (création : entrepôts/notes/lignes ; réception : quantités reçues ; résolution de litige :
  résolution + raison). `statusLabel` via `t()` (`transferStatus`).
- Étend `inventory.*` (transferts) ; réutilise `common.*` (status, actions, notes, quantity,
  allStatuses, cancel, saving, confirm).

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : ajustements de stock traduits FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-stockadj` (release `v1.0.0` → `rc.58`).

### UX (i18n)
- **`StockAdjustmentView` (Ajustements de stock)** internationalisé : en-tête, file d'attente +
  historique (colonnes, statuts), volets **création** (article, quantité, motif, note) et **rejet**
  (motif). Libellés de **motif (8)** et de **statut (4)** via `t()` (`adjReason` / `adjStatus`) ;
  erreurs runtime traduites.
- Étend `inventory.*` (ajustements) ; `REASON_LABELS` du service n'est plus consommé par la vue.

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : alertes de stock traduites FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-stock2` (release `v1.0.0` → `rc.57`).

### UX (i18n)
- **`StockAlertsView` (Alertes stock bas)** internationalisé : en-tête + compteur, bannière
  d'urgence, cartes d'alerte (badges, disponible vs seuil, barre de progression « % du seuil » /
  « Rupture »), et **volet de réapprovisionnement** (infos stock, quantité + « unités », raisons
  fournisseur, référence, note, pied). Erreur runtime via `common.genericError`.
- Étend `inventory.*` (alertes : titre, seuils, réappro, `restockReason`).

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : liste Stock traduite FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-stocklist` (release `v1.0.0` → `rc.56`).

### UX (i18n)
- **`StockListView` (Stock)** internationalisé : en-tête + compteur, actions (réception, alertes),
  filtres (recherche / entrepôt / catégorie / stock bas), barre KPI entrepôt, table (colonnes,
  badges Stock bas/OK), actions de ligne (entrée/sortie/ajuster/historique), pagination, et **volet
  entrée/sortie/ajustement** (titre dynamique, infos stock, quantité + suffixe « unités », indices
  Après/Ajustement, raisons, référence, note, pied). Titre du volet via `t()`.
- Étend le namespace **`inventory.*`** (clés `kpi`, `modalTitle`, `reasonOpt`) ; réutilise `common.*`
  (product, quantity, status, actions, note, previous/next, allWarehouses).

### Tests
- Frontend **253** (assertions `inventory` enrichies : stock + raison + interpolation) · `vue-tsc`
  propre · `StockListView.spec` verte (FR inchangé).

## [Non publié] — i18n : module Entrepôts traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-inventory` (release `v1.0.0` → `rc.55`).

### UX (i18n)
- **`WarehouseView` (Entrepôts & Boutiques)** internationalisé : titre + compteur, bouton, état vide,
  cartes (badges type/défaut/actif/en ligne, actions), et **volet créer/éditer** (libellés,
  placeholders, types, devise, bascules, pied) ; messages runtime via `t()` (badge de type,
  validation « nom et code requis », erreur d'enregistrement).
- Nouveau namespace **`inventory.*`** (FR + EN) avec clés imbriquées `typeOption`/`typeBadge` ;
  réutilise `common.*` (name, phone, active/inactive, edit, cancel, saving, update, create).

### Tests
- Frontend **253** (+1 : namespace `inventory` dans `i18n.spec` — titre + clé imbriquée FR/EN) ·
  `vue-tsc` propre · `WarehouseView.spec` verte (FR inchangé).

## [Non publié] — Refonte UI : reliquat (Marketplace + alert() → toasts) + docs (2026-06-08)

Branche `feature/ux-reliquat-docs` (release `v1.0.0` → `rc.54`).

### UX — fin de la refonte
- **`MarketplaceListingsView`** : sous-titre = « plateforme · ID externe » à l'édition (dernier volet
  sans contexte d'en-tête).
- **`alert()` natifs supprimés** (7 occurrences / 6 vues) → **toasts** non bloquants via `pushToast`
  (`useNotifications`, sévérité `error`) : `SupplierListView` (suppression échouée), `ProductShowPage`
  (impression), `SettingsView` (×2 : rôle / activation membre), `LabelPrintView` (génération),
  `ImportWizardView` & `ImportHistoryView` (annulation). `pushToast` est désormais exporté directement.
- **🎯 Refonte « Side-Drawer » 100 % terminée** : volets latéraux (rc.46), confirmations centrées
  (rc.47-48), polish par module (rc.49-53), reliquat (rc.54). **Plus aucun `confirm()`/`alert()`
  bloquant** côté vues.

### Docs
- **`go-no-go-v1.0.0.md` actualisé** (gelé rc.1 → **rc.54**) : compteurs de tests, section UX (design
  system, Side-Drawer, i18n, confirmations/toasts), zones, checklist. + `etat-des-lieux`,
  `ux-design-system`, CHANGELOG.

### Tests
- Frontend **252** inchangé · `vue-tsc` propre · `pushToast` déjà couvert (`useNotifications.spec`).

## [Non publié] — Refonte UI : polish des volets — Inventaire (Transfert & Période) (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-inventory` (release `v1.0.0` → `rc.53`).

### UX — polish volets (Phase 3)
- **`StockTransferView`** (réception, résolution de litige) et **`FiscalPeriodView`** (verrouillage) :
  numéro de transfert / nom de période déplacés du **titre** vers le **sous-titre** (titre = action
  seule), pour s'aligner sur le pattern « titre = action, sous-titre = entité » des autres volets.
- *(Volets de création « Nouveau transfert / Nouvelle période » : pas de sous-titre — aucune entité
  existante. `StockAdjustmentView` : création OK ; rejet laissé tel quel — pas de champ article fiable
  pour un sous-titre.)*

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · specs vertes.

> **Phase 3 quasi terminée** — modules phares polis (Stock, Paiements, Admin, Fournisseurs, Entrepôts,
> Livraisons, Inventaire) ; POS déjà conforme. Reliquat (Marketplace, Import, page Produit) déjà
> conforme via la fondation rc.46 ou hors périmètre « volet ».

## [Non publié] — Refonte UI : polish des volets — Entrepôts & Livraisons (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-pos` (release `v1.0.0` → `rc.52`).

### UX — polish volets (Phase 3)
- **`WarehouseView`** : titre « Modifier l'emplacement » (au lieu de « Modifier » nu) + **sous-titre**
  = nom de l'entrepôt à l'édition.
- **`DeliveryListView`** : **sous-titre** = référence commande sur le volet « Signaler un échec »
  (`failModal.orderRef` capturé à l'ouverture).
- *(`PosView` déjà conforme : devise en suffixe via `.pos-amount-input` / `.pos-currency` sur le fond
  de caisse et la clôture — pattern précurseur de `.input-affix` ; aucun changement nécessaire.)*

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · specs vertes.

## [Non publié] — Refonte UI : polish des volets — Admin & Fournisseurs (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-admin` (release `v1.0.0` → `rc.51`).

### UX — polish volets (Phase 3)
- **`PromotionListView`** : champ **valeur de remise** avec suffixe **`%`** dynamique (`.input-affix`,
  affiché quand le type = pourcentage) ; **sous-titre** = code promo à l'édition.
- **`SupplierListView`** : **sous-titre** = nom du fournisseur à l'édition (contexte d'en-tête).
- *(`PlanListView` édite des limites — pas de prix dans le modal — et `CountryRuleListView` des
  codes/devise ISO : aucun champ montant, donc pas d'affixe ; leurs titres portent déjà le contexte.)*

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · specs vertes.

## [Non publié] — Refonte UI : polish des volets — module Paiements (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-payments` (release `v1.0.0` → `rc.50`).

### UX — polish volets (Phase 3, module Paiements)
- **`OrderDetailView` (enregistrer un paiement)** : contexte « Commande N° · Reste … » remonté en
  **sous-titre** d'en-tête ; **devise de la commande affichée en suffixe** du champ montant via
  `.input-affix` (remplace la boîte devise en lecture seule séparée) — calqué sur la capture de
  référence (montant … XAF).
- **`ManualPaymentView` (rejet)** : nom du tenant remonté en **sous-titre** d'en-tête.
- **`PaymentListView`** : champ montant + sélecteur de devise désormais **accolés** (nouveau pattern
  réutilisable **`.input-group`**), lus comme un seul contrôle.

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · `OrderDetailView`/`PaymentListView`
  specs vertes (titres/flux inchangés).

## [Non publié] — Refonte UI : polish des volets — module Stock (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-stock` (release `v1.0.0` → `rc.49`).

### UX — polish volets (Phase 3, module Stock)
- **Sous-titre contextuel dans l'en-tête** : `StockListView` (entrée/sortie/ajustement) et
  `StockAlertsView` (réapprovisionnement) affichent désormais « produit · SKU » via la prop
  `subtitle` de `BaseModal` (au lieu d'un `<p>` en haut du corps) — calqué sur la capture de référence.
- **Suffixe d'unité dans le champ** : nouveau pattern réutilisable **`.input-affix`** (suffixe
  collé à droite, à l'intérieur de l'input) ; appliqué aux champs quantité (« unités »). Prêt pour
  les suffixes **devise (XAF)** des modules monétaires à venir.
- Bloc info-stock conservé en **contexte grisé** (`--gray-50`).

### Tests
- Frontend **252** inchangé (polish visuel ; `StockListView.spec` toujours verte — le titre du
  volet est inchangé, le sous-titre reste dans le dialogue) · `vue-tsc` propre.

## [Non publié] — Refonte UI : confirmations centrées — vague 2 (fin) (2026-06-08)

Branche `feature/ux-confirm-wave2` (release `v1.0.0` → `rc.48`).

### UX — confirmations (suite)
- Migration `confirm()` natif → `useConfirm()` **terminée** sur les vues. Vague 2 (10 fichiers,
  11 points) : `MarketplaceListingsView` (suppression connexion), `ImportWizardView` +
  `ImportHistoryView` (annulation import), `TenantListView` + `TenantDetailView` (suspension),
  `ManualPaymentView` (approbation), `SettingsView` (activation/désactivation membre),
  `StockTransferView` (expédition), `ReturnsView` (approbation + remise en stock), `ProfileView`
  (révocation de session). Boutons `danger` pour les actions destructives, primaires pour les
  validations positives.
- **Plus aucun `confirm()` bloquant côté vues** (hors `CustomerDetailView` — module Clients en
  session concurrente, `orderService.confirm()` = méthode API, `useUnsavedChanges` = garde de
  navigation hors composant).

### Tests
- Frontend **252** inchangé (migration mécanique ; primitif déjà couvert rc.47) · `vue-tsc` propre ·
  aucune spec impactée (les flux `confirm()` de la vague 2 ne sont pas exercés en test).

## [Non publié] — Refonte UI : confirmations centrées (ConfirmDialog / useConfirm) — vague 1 (2026-06-08)

Branche `feature/ux-confirm-dialog` (release `v1.0.0` → `rc.47`).

### UX — confirmations
- Nouveau **`ConfirmDialog.vue`** (boîte **centrée** via `BaseModal variant="center"`) + composable
  **`useConfirm()`** : `await confirm({ title, message, danger?, … }) → Promise<boolean>`. Host monté
  une seule fois dans `App.vue` ; toute fermeture (croix/Échap/clic-extérieur) vaut annulation ;
  bouton **rouge** en mode `danger`. Remplace les `window.confirm()` natifs par une boîte cohérente
  avec le design system (mêmes boutons, angles, polices).
- **Vague 1** — migrés : `CategoryListView`, `SupplierListView` (suppression), `PaymentListView`
  (annulation), `OrderDetailView` (annulation de paiement), `CountryRuleListView`, `PromotionListView`
  (suppression), `RolesPanel` (suppression de rôle). *(Les `confirm()` des services/composables —
  `orderService`, `useUnsavedChanges` — restent inchangés : hors composant.)*

### Correctif i18n
- `common.update` / `common.description` **manquaient en anglais** (introduits FR-only en rc.45) →
  l'UI EN affichait le fallback français. Ajoutés ; nouvelle clé `common.confirm` (FR/EN).

### Tests
- Frontend **252** (+4 : `ConfirmDialog.spec` — ouverture/validation/annulation/libellé défaut ;
  `PaymentListView` & `OrderDetailView` spec migrées vers un mock `useConfirm` ; garde-fou i18n EN)
  · `vue-tsc` propre.

## [Non publié] — Refonte UI : fondation Side-Drawer (BaseModal) (2026-06-08)

Branche `feature/ux-drawer-foundation` (release `v1.0.0` → `rc.46`).

### UX — refonte « volet latéral »
- **`BaseModal` devient un Side-Drawer par défaut** (`variant="drawer"`) : volet plein écran
  (100vh) sur le flanc **droit**, largeur fixe selon `size` (**sm 400 / md 460 / lg 520 px**),
  voile sombre, fond blanc, slide-in (respecte `prefers-reduced-motion`), corps défilant et
  **pied collé en bas**. Les **~25 vues** consommatrices basculent en volet **sans changement de
  code** (API rétro-compatible).
- Nouveau **`variant="center"`** (boîte centrée arrondie) réservé aux confirmations critiques
  (socle de la Phase 2 `ConfirmDialog`/`useConfirm`).
- En-tête enrichi : prop **`subtitle`** + slot **`#subtitle`** (contexte type « produit · SKU »),
  croix de fermeture plus fine et grise.

### Correctif (bug latent)
- `.modal-overlay` / `.modal` n'étaient **définis nulle part** → les volets rendaient sans voile
  ni positionnement. Le chrome est désormais centralisé dans `main.css` (source unique) ;
  `.modal-backdrop` / `.modal-box` morts supprimés.

### Tests
- Frontend **248** (+4 : variant drawer par défaut, variant center + taille, sous-titre présent/absent
  dans `ui.spec`) · `vue-tsc` propre · aucune régression sur les vues à modale.

## [Non publié] — i18n : liste Catégories traduite FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-categories` (release `v1.0.0` → `rc.45`).

### UX
- **Liste Catégories (`CategoryListView`)** internationalisée : titre + compteur, bouton « Nouvelle
  catégorie », état vide (+ aide), colonnes (Nom/Parent/Ordre/Statut/Actions), badge Active/Inactive,
  actions de ligne, et **modale créer/éditer** (titre, libellés, placeholders, bascule de statut, pied
  Annuler/Créer/Mettre à jour) ; messages runtime (validation « nom requis », confirmation de
  suppression interpolée) via `t()`.
- Enrichit le namespace **`catalog.*`** (sous-ensemble catégories) + ajoute `common.description` /
  `common.update` mutualisés.

### Tests
- Frontend **244** (+1 : sous-namespace catégories dans `i18n.spec` — titre + confirmation interpolée
  FR/EN) · `vue-tsc` propre.

## [Non publié] — i18n : liste Produits traduite FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-products` (release `v1.0.0` → `rc.44`).

### UX
- **Liste Produits (`ProductListView`)** internationalisée : titre, filtres (recherche/statut/catégorie),
  barre de sélection (impression lots), colonnes, statuts, badges de variantes, actions de ligne,
  pagination, états vide/recherche. Nouveau namespace `catalog.*` (`status`) + `common.*` réutilisés.
- **Correctif tests** : sous jsdom la locale par défaut serait `en` (navigator.language) → `test-setup.ts`
  force désormais `fr` avant chaque test (les specs anglaises basculent puis restaurent).

### Tests
- Frontend **243** (+1 : namespace `catalog` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal TERMINÉE (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave17` (release `v1.0.0` → `rc.43`).

### UX
- **Dernière vague (UX-03, 17ᵉ)** — `SettingsView` : **4 modales** (invitation d'un membre, accès
  entrepôts, accès temporaire auto-expirant, demande de mise à niveau / preuve de paiement) migrées
  vers `<BaseModal>` ; chrome `.modal-*` dupliqué retiré (corps via `.settings-modal-body`).
- **🎯 Migration `BaseModal` 100 % terminée** : **33 modales / 24 vues**, **plus aucune modale
  ad-hoc** dans le code (`grep modal-overlay|modal-backdrop` = 0). Chrome unifié (overlay,
  focus-trap, Échap, clic-extérieur, en-tête/fermeture) sur toute l'application.

### Tests
- `v-focus-trap` enregistré globalement dans `test-setup.ts` (plus de warning, specs allégées).
  Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 16ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave16` (release `v1.0.0` → `rc.42`).

### UX
- **Modales unifiées (UX-03, 16ᵉ vague)** — `PosView` (**2 modales** : choix de déclinaison +
  clôture de caisse) migrées vers `<BaseModal>` ; chrome `.pos-modal-*` retiré (les contrôles tactiles
  `pos-btn`/`pos-input` et les `data-test` sont conservés). **29 modales / 23 vues migrées au total**
  (ne reste que `SettingsView`). 
### Tests
- `PosView.spec` adapté (teleport stub + `v-focus-trap`). Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 15ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave15` (release `v1.0.0` → `rc.41`).

### UX
- **Modale unifiée (UX-03, 15ᵉ vague)** — `ProductFormView` (gestion du stock à la désactivation
  d'une variante : transférer / sortir / conserver) migrée vers `<BaseModal>` ; styles `.deact-*`
  de chrome retirés. **27 modales / 22 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 14ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave14` (release `v1.0.0` → `rc.40`).

### UX
- **Modale unifiée (UX-03, 14ᵉ vague)** — `StockListView` (entrée / sortie / ajustement de stock)
  migrée vers `<BaseModal>` ; sous-titre (produit · SKU) déplacé dans le corps. **26 modales /
  21 vues migrées au total.**

### Tests
- `StockListView.spec` adapté (BaseModal `role="dialog"` + `v-focus-trap`). Frontend **242** au vert ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 13ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave13` (release `v1.0.0` → `rc.39`).

### UX
- **Modale unifiée (UX-03, 13ᵉ vague)** — `OrderDetailView` (enregistrer un paiement sur une
  commande) migrée vers `<BaseModal>` ; sous-titre (n° commande + reste à payer) déplacé dans le
  corps. **25 modales / 20 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 12ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave12` (release `v1.0.0` → `rc.38`).

### UX
- **Modale unifiée (UX-03, 12ᵉ vague)** — `MarketplaceListingsView` (connexion/édition d'une listing
  marketplace) migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés (corps via
  `.mp-modal-body`). **24 modales / 19 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 11ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave11` (release `v1.0.0` → `rc.37`).

### UX
- **Modale unifiée (UX-03, 11ᵉ vague)** — `PromotionListView` (création/édition de promotion, admin)
  migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés (corps via `.promo-modal-body`).
  **23 modales / 18 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 10ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-importhistory` (release `v1.0.0` → `rc.36`).

### UX
- **Modale unifiée (UX-03, 10ᵉ vague)** — `ImportHistoryView` (détail d'une session d'import)
  migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés (corps via `.import-detail-body`,
  sous-titre déplacé dans le corps). **22 modales / 17 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 9ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave9` (release `v1.0.0` → `rc.35`).

### UX
- **Modale unifiée (UX-03, 9ᵉ vague)** — `RolesPanel` (création/édition de rôle personnalisé,
  Paramètres) migrée vers `<BaseModal>` ; chrome/`v-focus-trap` manuels remplacés, styles `.modal-*`
  dupliqués retirés (corps via `.roles-modal-body`). **21 modales / 16 vues migrées au total.**

### Tests
- `RolesPanel.spec` adapté (teleport stub + `v-focus-trap`). Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — i18n : liste Commandes traduite FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-orders-list` (release `v1.0.0` → `rc.34`).

### UX
- **Liste Commandes (`OrderListView`)** internationalisée : titre, onglets de statut, filtres
  (recherche/dates/entrepôt), colonnes (+ `data-label` cartes mobiles), statuts, pagination, états
  vide/erreur. Nouveau namespace `orders.*` (`tab`/`status`) + `common.view` mutualisé. Bascule
  FR ↔ EN en direct. (Reste `OrderCreateView`/`OrderDetailView` pour compléter le module.)

### Tests
- Frontend **242** (+1 : namespace `orders` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 8ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave8` (release `v1.0.0` → `rc.33`).

### UX
- **Modales unifiées (UX-03, 8ᵉ vague)** — `StockTransferView` (**3 modales** : création de transfert,
  réception, résolution de litige) migrées vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés.
  **20 modales / 15 vues migrées au total.**

### Tests
- Frontend **241** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 7ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave7` (release `v1.0.0` → `rc.32`).

### UX
- **Modale unifiée (UX-03, 7ᵉ vague)** — `ManualPaymentView` (rejet de paiement manuel, admin)
  migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés. **17 modales / 14 vues migrées.**

### Tests
- Frontend **241** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 6ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave6` (release `v1.0.0` → `rc.31`).

### UX
- **Modales unifiées (UX-03, 6ᵉ vague)** — `FiscalPeriodView` (**2 modales** : création + verrouillage
  irréversible) et `SupplierDetailView` (confirmation de suppression) migrées vers `<BaseModal>`
  (styles `.modal-*` dupliqués retirés). Le module Fournisseurs est désormais **100 % BaseModal**.
  **16 modales / 13 vues migrées au total.**

### Tests
- `SupplierDetailView.spec` adapté (teleport stub + `v-focus-trap`) pour la modale BaseModal.
  Frontend **241** au vert · `vue-tsc` propre.

## [Non publié] — i18n : module Rapports traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-reports` (release `v1.0.0` → `rc.30`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Rapports (`SalesReportView` + `StockReportView`)** — 4ᵉ module entièrement
  internationalisé : titres, KPI, périodes (court + long), graphiques, tableaux, listes de moyens
  de paiement / mouvements, messages d'erreur. Nouveau namespace `reports.*` (avec sous-tables
  `period`/`periodLong`/`movement`) ; réutilise `payments.method.*` et `common.*` (allWarehouses,
  product, quantity). Bascule FR ↔ EN en direct.

### Tests
- Frontend **241** (+1 : namespace `reports` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 5ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave5` (release `v1.0.0` → `rc.29`).

### UX
- **Modales unifiées (UX-03, 5ᵉ vague)** — `PlanListView` (édition des limites de plan) et
  `StockAlertsView` (réapprovisionnement) migrées vers `<BaseModal>` (toutes deux utilisaient les
  classes globales `.modal-*`, rien à nettoyer ; sous-titre de StockAlerts déplacé dans le corps).
  **13 modales / 11 vues migrées au total.**

### Tests
- Frontend **240** au vert · `vue-tsc` propre (contrat `BaseModal` couvert par 4 specs existantes).

## [Non publié] — i18n : module Paiements traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-payments` (release `v1.0.0` → `rc.28`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Paiements (`PaymentListView`)** — 3ᵉ module entièrement internationalisé : toutes les
  chaînes via `$t` / `t()` (filtres, moyens de paiement, colonnes — y compris les `data-label`
  des cartes mobiles, total, pagination, modale, message de confirmation d'annulation). Nouveau
  namespace `payments.*` (dont `payments.method.*`) + libellés `common.*` mutualisés (date, amount,
  note, previous, next). Bascule FR ↔ EN en direct.

### Tests
- Frontend **240** (+1 : re-rendu EN dans `PaymentListView.spec.ts`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 4ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave4` (release `v1.0.0` → `rc.27`).

### UX
- **Modales unifiées (UX-03, 4ᵉ vague)** — `ReturnsView` (refus de retour) et `CountryRuleListView`
  (création/édition de règle pays) migrées vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés
  (ReturnsView). **11 modales / 9 vues migrées au total.**

### Tests
- Frontend **239** au vert · `vue-tsc` propre (contrat `BaseModal` couvert par 4 specs existantes).

## [Non publié] — i18n + BaseModal : module Livraisons (UX-13 + UX-03) (2026-06-07)

Branche `feature/ux-i18n-deliveries` (release `v1.0.0` → `rc.26`).
Voir `docs/modules/i18n.md`, `docs/modules/ux-design-system.md`.

### UX
- **Module Livraisons finalisé (`DeliveryListView`)** — 2ᵉ module entièrement internationalisé :
  toutes les chaînes via `$t`/`t()` (statuts, colonnes, actions, modales), nouveau namespace
  `deliveries.*` + `common.allStatuses` mutualisé ; bascule FR ↔ EN en direct.
- Ses **2 modales** (nouvelle livraison + signalement d'échec) migrées vers `<BaseModal>`
  (focus-trap, Échap, clic-extérieur). Total cumulé : **9 modales / 7 vues** sur `<BaseModal>`.

### Tests
- Frontend **239** (+3 : `DeliveryListView.spec.ts` — liste + `role="dialog"` `aria-modal` +
  re-rendu EN au changement de langue) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 3ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave3` (release `v1.0.0` → `rc.25`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03, 3ᵉ vague)** — `WarehouseView` (création/édition d'emplacement) et
  `StockAdjustmentView` (**2 modales** : nouvelle demande + rejet) migrées de leur modale ad-hoc
  vers `<BaseModal>` (overlay, `Teleport`, focus-trap, Échap, clic-extérieur, en-tête/fermeture
  cohérents ; styles `.modal-*` dupliqués supprimés). **7 modales / 6 vues migrées au total.**

### Tests
- Frontend **236** (+2 : `WarehouseView.spec.ts` — liste + ouverture d'un `role="dialog"`
  `aria-modal`) · `vue-tsc` propre.

## [Non publié] — i18n : module Fournisseurs traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-suppliers` (release `v1.0.0` → `rc.24`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Fournisseurs entièrement internationalisé (UX-13)** — `SupplierListView` et
  `SupplierDetailView` : toutes les chaînes visibles passent par `$t` (template) / `t()` (script :
  `confirm`/`alert`, fallback d'erreur). Nouveau namespace `suppliers.*` + libellés génériques
  mutualisés sous `common.*` (name, email, phone, status, actions, notes, active/inactive,
  createdAt/updatedAt, saving, retry, genericError) pour réemploi par les prochains modules.
  Le sélecteur de langue bascule l'UI Fournisseurs en direct (FR ↔ EN).

### Tests
- `i18n` enregistré globalement dans `test-setup.ts` (`$t` disponible à chaque montage) ; specs
  changeant la locale la restaurent (isolation).
- Frontend **234** (+ namespace `suppliers` dans `i18n.spec`, re-rendu EN dans
  `SupplierListView.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 2ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave2` (release `v1.0.0` → `rc.23`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03, 2ᵉ vague)** — `SupplierListView` (création/édition fournisseur) et
  `CategoryListView` (création/édition catégorie) migrées de leur modale ad-hoc vers `<BaseModal>`
  (overlay, `Teleport`, focus-trap, Échap, clic-extérieur, en-tête/fermeture cohérents ; styles
  `.modal-*` dupliqués supprimés). Pour le formulaire fournisseur (vrai `<form>`), le `<form id>`
  reste dans le slot par défaut et le bouton du `#footer` y est lié par l'attribut `form="…"`
  (validation native + Entrée préservées). 4 modales migrées au total (2 vagues).

### Tests
- Frontend **230** (+2 : `SupplierListView.spec.ts` — liste + ouverture d'un `role="dialog"`
  `aria-modal` avec bouton `form="supplier-form"`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : fondation i18n FR + EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-foundation` (release `v1.0.0` → `rc.22`).
Voir `docs/modules/i18n.md`.

### UX
- **Fondation i18n (UX-13)** — noyau d'internationalisation **léger et sans dépendance**
  (`src/i18n/index.ts`) qui imite l'API `t()` de vue-i18n (migration future facilitée) :
  langues **FR + EN**, clés en chemin pointé + interpolation `{param}`, fallback FR puis clé brute.
  `$t` exposé globalement (typé pour `vue-tsc`), composable `useI18n()`. **Sélecteur de langue**
  (`LanguageSwitcher`, barre supérieure) avec **persistance** (`localStorage` — non sensible,
  contrairement au token d'auth) et mise à jour de `<html lang>`. Pilote : `NotFoundView` migrée.
  La migration des chaînes du reste de l'app est incrémentale (français = source de vérité).

### Tests
- Frontend **227** (+6 : `i18n.spec.ts` — traduction/interpolation/fallback/persistance +
  `LanguageSwitcher`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : composant Icon (UX-11) (2026-06-07)

Branche `feature/ux-icon-component` (release `v1.0.0` → `rc.21`).
Voir `docs/modules/ux-design-system.md` (§ Composants).

### UX
- **Composant `Icon` (UX-11)** — `shared/ui/Icon.vue` centralise les petites icônes ligne
  redessinées à la main un peu partout : `<Icon name="plus" :size="14" />`. Registre **statique**
  de primitives SVG (whitelist, **pas de `v-html`**), grille 16×16, `currentColor` ; décoratif par
  défaut (`aria-hidden`), `title` → `role="img"`. Noms : plus, search, view, edit, close, trash,
  check, download, filter, chevron-left/right. Adopté sur **Produits** (bouton + recherche) et
  **Paiements** ; industrialisation incrémentale du reste des SVG inline.

### Tests
- Frontend **221** (+5 : `Icon.spec.ts` — primitives par nom, a11y décoratif/`title`, taille) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : filtres persistés dans l'URL (UX-12) (2026-06-07)

Branche `feature/ux-url-filters` (release `v1.0.0` → `rc.20`).
Voir `docs/modules/ux-design-system.md` (§ Filtres de liste persistés).

### UX
- **Filtres dans l'URL (UX-12)** — nouveau composable `useUrlFilters` : un objet de filtres
  réactif est synchronisé avec la query string. Les filtres **survivent au rafraîchissement et
  au bouton Précédent**, et une liste filtrée devient **partageable par URL**. Valeurs vides /
  par défaut omises (URL propre), types coercés, `router.replace` (pas de pollution d'historique).
  Adopté sur **Produits** et **Paiements** ; adoption incrémentale du reste.

### Tests
- Frontend **216** (+3 : `useUrlFilters.spec.ts` — hydratation, miroir, clés inconnues) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 1ʳᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-migration` (release `v1.0.0` → `rc.19`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03)** — `CustomerListView` (création/édition client) et `PaymentListView`
  (enregistrement de paiement) migrées de leur modale ad-hoc vers le composant partagé
  `<BaseModal>` : overlay, `Teleport`, **focus-trap**, Échap, clic-extérieur, en-tête et bouton
  de fermeture cohérents. Chrome local et styles `.modal-*` dupliqués supprimés. La fermeture est
  liée via `@update:model-value` pour préserver les effets de bord (réinitialisation du formulaire).
  Reste l'adoption incrémentale (~22 vues).

### Tests
- Frontend **213** (+1 : `PaymentListView.spec.ts` ouvre un `role="dialog"` `aria-modal`) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : cartes mobiles (UX-06) (2026-06-07)

Branche `feature/ux-mobile-cards` (release `v1.0.0` → `rc.18`).
Voir `docs/modules/ux-design-system.md` (§ Tableaux responsives).

### UX
- **Cartes empilées sur mobile (UX-06)** — nouveau pattern CSS-only `.data-table--cards` :
  sous **640px**, chaque ligne de tableau devient une **carte** ; chaque cellule porte
  `data-label="<colonne>"` (libellé affiché via `::before`), l'identité prend `.cell-primary`
  (titre pleine largeur) et les actions `.cell-actions`. Comme une carte a de la place
  verticale, les colonnes masquées par `.hide-mobile` y **réapparaissent** en lignes libellées.
  Le défilement horizontal reste le comportement par défaut. Adopté sur **Commandes, Clients,
  Paiements** ; adoption incrémentale du reste des listes (contrat documenté + testé).

### Tests
- Frontend **212** (+1 : contrat « card-stacking » dans `PaymentListView.spec.ts`) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : feedback action (403) + page 404 (2026-06-07)

Branche `feature/ux-action-feedback` (release `v1.0.0` → `rc.17`).
Voir `docs/modules/ux-design-system.md`, `docs/recette/etat-des-lieux-v1.0.0.md`.

### UX
- **403 jamais silencieux (UX-10)** — le client API émet `api:forbidden` (message du backend) ;
  `useNotifications` l'écoute (une fois, au montage) et le remonte en **toast d'erreur** via la
  nouvelle fonction `pushToast(message, severity = 'error')`. Une action refusée par
  rôle / permission / module est désormais visible (auparavant : échec muet). Les toasts client
  portent `type: 'client'` (libellé « Accès refusé »).
- **Page 404 design-system (UX-14)** — `NotFoundView` (catch-all) reconstruite sur `StateBlock`
  (`empty`) + `BaseButton` (retour tableau de bord), cohérente avec `/unavailable` (402).

### Tests
- Frontend **211** (+4 : `composables/__tests__/useNotifications.spec.ts`) · `vue-tsc` propre ·
  `npm audit` 0 vulnérabilité.

## [Non publié] — Remédiation audit sécurité (2026-06-07)

Implémente l'intégralité des gates de l'audit sécurité (branche
`feature/security-audit-remediation`). Voir `docs/security/security-remediation-tests.md`,
`docs/modules/rbac.md`.

### Sécurité
- **Gating module fail-closed** — `module:<code>` étendu à **tous** les modules métier
  (catalog, inventory, orders, customers, payments + delivery/suppliers/import_export/
  reports) ; un tenant sans le module (ou sans aucune ligne `tenant_modules`) est **refusé**
  (un menu masqué n'est pas un contrôle d'accès). `dashboard` (cœur) reste actif.
- **Permissions métier sur les créations** — `POST` clients/paiements/commandes gardés par
  `role_or_permission:manager|admin|<module>.create` → un `viewer` ne peut plus créer.
- **Hiérarchie des rôles** — autorité centrale `RoleHierarchy` : un `manager` ne peut plus
  inviter, attribuer ni accorder temporairement le rôle `manager` (anti-escalade latérale).
- **Isolation multitenant** — le `parent_id` d'une catégorie doit appartenir au tenant courant.
- **Preuves de paiement privées** — stockées sur le disque privé (plus le disque public) ;
  payload tenant sans `proof_url` ; téléchargement admin via **URL signée courte**.
- **Chaîne d'audit vérifiable** — empreinte d'intégrité calculée via un payload canonique
  **partagé** entre création et vérification (`created_at` épinglé, `ts` unix s) →
  `verify-chain` valide réellement une chaîne propre.
- **Frontend** — token Bearer **en mémoire seule** (plus de `localStorage`/`sessionStorage`,
  legacy purgé) ; `v-html` sur les SVG de modules remplacé par un composant `ModuleIcon`
  (whitelist statique). Dépendances : `composer audit` / `npm audit` = 0 vulnérabilité.

### Tests
- Backend **638** (636 passés, 2 skipped) — `SecurityRemediationTest` (16) + `ModuleGatingTest`
  durci verts. Frontend **191** (gates `frontendSecurity` + `auth` verts) · `vue-tsc` propre.

### En attente
- Audit UX/UI (`docs/ux-ui/audit-ux-ui-approfondi.md`, P0/P1) et cahiers catalogue
  (produits spéciaux + duplication) — sessions dédiées, arbitrage produit requis.

## [1.0.0-rc.4] — 2026-06-06 (RBAC Phase B2 — rôles custom + permissions fines)

Achève le programme RBAC **A + B2 + C**. La Phase **B2** ajoute des rôles configurables
par tenant **et** applique réellement les permissions fines sur les écritures sensibles.

### Ajouté
- **RBAC B2.2 — application des permissions** : 15 groupes de routes d'écriture sensibles
  migrés de `role:manager|admin` → `role_or_permission:manager|admin|<perm-granulaire>`
  (Catalog, Customers, Suppliers, Inventory ×6, Orders ×2, Marketplace, Delivery,
  Import/Export, Reports) + void paiement gardé dans le contrôleur via `payments.delete`.
  Les permissions choisies sont **exclusives à admin/manager** → le comportement des rôles
  de base est **strictement préservé** (un `member` reste bloqué) ; un **rôle custom**
  porteur de la permission **passe la garde**.
- **3 permissions de gestion** ajoutées au seeder (admin/manager, accordables) pour les
  routes de cycle de vie sans permission CRUD dédiée : `orders.manage`, `delivery.manage`,
  `marketplace.manage`.
- **RBAC B2.3 — UI Paramètres → Rôles** (admin) : `RolesPanel.vue` + `roleService` —
  lister les rôles (base en lecture seule + custom éditables), créer/éditer un rôle custom
  (permissions `grantable` **regroupées par module**), supprimer. Les sélecteurs de rôle
  (onglet Équipe + invitation) incluent désormais les **rôles personnalisés**.

### Sécurité
- `role_or_permission` s'appuie sur `Gate::before` (Spatie) qui **avale**
  `PermissionDoesNotExist` → une route peut référencer une permission non seedée sans
  jamais transformer un `403` en `500` (robustesse des ~600 tests existants).

### Docs
- `docs/modules/rbac.md` (architecture 4 couches + **table route→permission**),
  `docs/user/roles.md` (guide admin), audit `docs/recette/rbac-acl-audit-v1.0.0.md`
  (B2 → **livré**), `docs/plan.md`.

### Reste avant GO ferme v1.0.0
- Recette finale + décision P6 (approche A recommandée) + CI install propre.
- En perspective : invitations email + login 2FA email.

### Tests
- Backend **622** (620 passés, 2 skipped) — dont `PermissionEnforcementTest` (9).
- Frontend **189** (+`roleService` ×5, `RolesPanel` ×3) · `vue-tsc` propre.

## [1.0.0-rc.3] — 2026-06-06 (RBAC durci)

Incrément depuis rc.1 (rc.2 = UI gaps ; rc.3 = RBAC A+C). Programme RBAC **A+B2+C**
décidé ; **A et C livrés**, **B2 (rôles custom + permissions fines) en session dédiée**.

### Ajouté
- **RBAC Phase A — gating module dynamique** : middleware `module:<code>` (data-driven) ;
  retirer un module à un tenant **bloque réellement ses routes backend** pour TOUS les
  utilisateurs (admins inclus), plus seulement les menus. Fail-open pour tenants non
  provisionnés. Modules gatés : `reports`, `suppliers`, `import_export`, `delivery`.
- **RBAC Phase C — accès temporaires auto-expirants** : `temporary_access_grants` +
  commande planifiée `access:revoke-expired` (chaque minute → expiration **sans action
  manuelle**) + UI « Accès temp. » (Paramètres → Équipe). `admin` non grantable temporairement.
- **UI gaps comblés (rc.2)** : Ajustements de stock (Stock → Ajustements) + édition des
  limites de plan (admin).

### Audits
- `docs/recette/rbac-acl-audit-v1.0.0.md` (5 exigences ↔ état + plan A/B2/C) ;
  `docs/recette/route-audit-v1.0.0.md` (réconciliation routes front↔back, 0 lien cassé).

### Reste avant GO ferme v1.0.0
- **RBAC Phase B2** : rôles custom par tenant (bornés par le plan) + migration
  `role:`→`permission:` des routes sensibles (session dédiée — blast-radius).
- Recette finale + décision P6 (approche A recommandée) + CI install propre.
- En perspective : invitations email + login 2FA email.

### Tests
- Backend **607** (605 passés, 2 skipped) · frontend **181** · `vue-tsc` propre.

## [1.0.0-rc.1] — 2026-06-06 (candidat production)

Release candidate figée depuis `develop` après v0.8.0. Décision : voir
[Go/No-Go v1.0.0](docs/recette/go-no-go-v1.0.0.md) (**GO conditionnel**).

### Ajouté
- **Landing & Upgrade localisés (P4/P5)** — la page tarifs publique **et** l'écran
  d'upgrade connecté consomment `GET /api/public/pricing` (source backend unique) ;
  plus aucun prix contractuel codé en dur côté frontend ; sélecteur pays/devise.
- **Multi-sites (Sprint 20)** — filtre par entrepôt sur Commandes, Paiements et
  Rapports (ventes/stock) ; **scoping d'accès par agence** (`user_warehouses`) :
  un membre non-manager assigné à des sites ne voit que leurs données (isolation
  testée end-to-end) ; assignation via Paramètres → Équipe (modale « Sites »).
- **Admin Règles pays (Sprint 21)** — CRUD super-admin des `CountryRule`
  (devise/fuseau/approbation/blocage/plans par pays).

### Modifié
- Convention de filtre entrepôt des services de liste passée en tableau
  (`?array $warehouseIds`) pour porter à la fois le filtre UI et la restriction d'accès.

### Supprimé
- Code mort : `CreateDeliveryRequest` / `UpdateDeliveryRequest` (règles vides,
  validation faite inline dans le contrôleur) ; artefacts `video_frames/*.png`.

### Tests
- Backend **597** (595 passés, 2 skipped MySQL-only), frontend **175**, `vue-tsc` propre,
  `vite build` OK. +`WarehouseScopeTest`, `WarehouseAccessScopingTest` (isolation HTTP),
  `AdminCountryRuleTest`, services pricing/reports/warehouses.

### À acter avant GO ferme
- Décision P6 (paiements locaux — approche A « manuel » recommandée, déjà fonctionnelle).
- Zones d'ombre : scoping d'accès limité aux **listes** (GET ressource-unique tenant-scopé).
- Branche par défaut GitHub `master` → `main`.

## [0.8.0] — 2026-06-05 (MVP consolidé, pré-1.0)

Première release consolidée depuis `v0.7.0` : MVP complet, audité et durci pour
de premiers clients réels.

### Ajouté
- **POS Web MVP** — module Caisse : sessions de caisse (ouverture/clôture avec
  rapprochement d'écart), encaissement (panier, scan, déclinaisons), atomique.
- **Pricing localisé** — `plan_prices` (10 marchés) + `plan_limits` ; API publique
  `GET /api/public/pricing` ; limites de plan éditables (super-admin).
- **Géolocalisation RGPD-safe** — `GET /api/public/geo` (headers edge/CDN, aucun
  appel tiers), repli locale navigateur.
- Couverture de tests : backend **570** (0 incomplete), frontend **154**
  (couverture 38.7 %), dont smoke tests admin back-office et tests sécurité
  (anti-escalade quotas, enforcement des sièges).
- **Démo exhaustive** — `DemoSeeder` couvre désormais TOUS les modules MVP
  (déclinaisons, mouvements de stock, entrepôts, POS, retours, marketplace,
  promotions, paiement manuel, périodes fiscales, imports, ajustements,
  transferts inter-entrepôts), idempotent et vérifié par `DemoSeederTest`.

### Corrigé
- **Sièges** : les plans payants ne plafonnent plus les utilisateurs
  (`max_users = null`) — fin de l'impasse « Business bloqué à 10 ».
- **Landing** : scroll restauré (verrou de viewport scopé aux shells app/admin).
- **Harnais de test** : `phpunit.xml memory_limit` (la suite OOM-ait à 128 Mo) ;
  `/** @test */` → `#[Test]` (PHPUnit 12) ; suffixes testsuite.

### Sécurité
- Sync (Phase 3) masqué derrière `FEATURE_SYNC` (off par défaut).
- Re-vérification approfondie : isolation multitenant, RBAC, intégrité — RAS.

### Nettoyage
- 32 fichiers stubs générés morts purgés (dont migrations parasites
  `paymentss`/`customerss`).

---

## [0.7.0] — Sprint 7A
- Billing SaaS, Admin back-office, Security, Marketplace (340 tests).

## [0.1.0-alpha.1]
- Bootstrap initial (Laravel 11 + système modulaire).
