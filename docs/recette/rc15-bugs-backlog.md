# Backlog de bugs — audit fonctionnel (rc.147)

> Audit READ-ONLY par 4 revues parallèles (commerce, monétisation, plateforme, couverture) sur
> `feature/rc15-qa-pos`, 2026-07-05. **~24 bugs consignés.** Les correctifs prioritaires (HAUTE/MAJEUR
> clairs et à faible risque) sont traités dans rc.147 ; le reste est priorisé ci-dessous pour les
> incréments suivants. Statut : ✅ corrigé rc.147 · ⬜ backlog.

## Contrainte d'environnement (rappel)
Le sandbox bloque **toute la couche HTTP `/api/*`** : écritures → `423 Locked` (proxy), lectures → `404`.
Le test « preview de tous les modules authentifiés » **n'est pas exécutable ici** (impossible de se
connecter). La couverture est assurée par les **265 specs front** (vues montées avec API mockée) + la
suite backend (~1011 tests) + cet audit statique.

## Corrigés dans rc.147

| # | Sévérité | Zone | Bug | Fichier |
|---|----------|------|-----|---------|
| P-2 | **MAJEUR** | Customers/Suppliers | Doublon email/code → **HTTP 500** au lieu de 422 (pas de règle `unique` applicative) | `CustomerController`, `SupplierController` |
| P-1 | **MAJEUR** | Marketplace | **Réouverture auto jamais déclenchée** (listener excluait `closed`, `reopen` jamais dispatché) → `is_auto_reopen_enabled` inerte | `DispatchMarketplaceSync.php:31` |
| C-1 | **HAUTE** | Inventory | `warehouse_id` **ignoré** au move-out/adjust → décrément du **mauvais entrepôt** (multi-site) | `InventoryController.php:152,177` + `AdjustStockRequest` |
| C-2 | **HAUTE** | Inventory | Write-off de litige de transfert = **double décrément** du stock source (les unités ont déjà quitté au ship) | `StockTransferService.php:200` |

## Corrigés dans rc.149 (RC-17)

| # | Sévérité | Zone | Bug | Correctif |
|---|----------|------|-----|-----------|
| M-1 | **HAUTE** | Billing | Ledger `tenant_credits` en écriture seule : trop-perçus jamais réappliqués → argent client perdu. | ✅ `approve()` applique le solde du ledger comme acompte virtuel s'il **solde** la cible (même règle que la proration), puis le **consomme** (ligne négative, référence = paiement, trace metadata). `previewProration` déduit le ledger de l'assiette (fin du double comptage). +4 tests. |
| M-2 | **HAUTE** | Reports | Code mort : `abcClassification`, `inventoryKpis`, `stockReconciliation` sans route ni front. | ✅ Routes `GET /api/reports/abc|inventory-kpis|reconciliation` + onglet **Analyse d'inventaire** (`/reports/insights`, vue + service + i18n FR/EN). +3 tests API, +3 specs front. |

## Corrigés dans rc.150 (RC-18 — file MOYENNE)

| # | Zone | Bug | Correctif |
|---|------|-----|-----------|
| D-3 | Digital | Clés de pool jamais libérées à la révocation/retour → fuite, épuisement prématuré. | ✅ `releasePoolKeys()` appelé par les 3 chemins de révocation : la clé redevient `available` (réassignable FIFO). +1 test. |
| M-4 | Billing | `isFreePlan` lisait les colonnes legacy, ignorait `PlanPrice` → jamais facturé / facturé à tort. | ✅ Prix **localisé** du marché de l'abonnement d'abord (repli legacy). +1 test. |
| M-5 | Billing | `applyPromo` consommait l'usage → `approve` voyait « déjà utilisé » → needs_review à tort. | ✅ `applyPromo` = validation seule ; l'usage n'est consommé qu'à l'**activation**. +1 test. |
| C-3 | Orders (front) | Paginator à plat ↔ front lit `res.meta` → pages 2+ inaccessibles. | ✅ Normalisation `{data, meta}` dans `orderService.list` + reset page 1 au changement de filtre. |
| C-4 | Orders | Filtres `search`/`from_date`/`to_date` ignorés serveur-side. | ✅ `paginate()` filtre numéro/nom client (LIKE échappé) + bornes de dates. +1 test API. |
| C-5 | Orders (front) | Actions retours sans try/catch → échecs muets. | ✅ try/catch + bandeau d'erreur (message serveur si présent), i18n FR/EN. |
| C-6 | Orders/Inventory | Vente possible contre stock **périmé** (dispo agrégée vs FEFO). | ✅ Au confirm d'une ligne lot : vendable = agrégat − lots périmés actifs → 422 clair. Drift historique sans lot toujours toléré. +1 test. |
| C-7 | Inventory | `quantity_after` faux (+qté) en import groupé. | ✅ « Après » figé avant l'update (plus de double addition). +1 test. |

## Backlog — à traiter (priorisé)

### Basse
| # | Zone | Bug | Fichier |
|---|------|-----|---------|
| C-8 | Orders/Catalog | **Journaux d'audit jamais écrits** : signature `AuditService::log` erronée → TypeError avalé (`return.approved`, `product.created`, `product.archived`). | `OrderReturnService.php:113`, `CatalogService.php:122,161` |
| C-9 | Orders/Inventory | Numéros `RET-`/`TRF-` via `count()+1` (course sur l'unicité). | `OrderReturnService.php:50`, `StockTransferService.php:44` |
| P-3 | Suppliers | `SupplierService::nextCode` via `count()+1` (course). | `SupplierService.php:78` |
| C-10 | Payments (front) | Idempotence paiement (`X-Idempotency-Key`) **jamais envoyée** par le front. | `paymentService.ts:21` |
| N-6 | Notifications | Remboursement de crédit si l'`update` post-`deliver` échoue (message déjà envoyé) — fenêtre étroite. | `NotificationService.php:158` |
| B-7 | Billing | `PromotionService::recordUse` sans verrou → dépassement possible de `max_uses` en concurrence. | `PromotionService.php:56` |
| P-4 | ImportExport | Doublons de SKU **intra-fichier** marqués `VALID` à l'analyse (échouent seulement à l'exécution). | `ProductImportParser.php:139` |
| P-5 | Orders/POS | `customer_id` **non validé au tenant** (référence inter-tenant possible). | `OrderService.php:92`, `PosController.php:89` |
| P-6 | Marketplace | Label plateforme **WooCommerce** manquant (fallback « Woocommerce »). | `MarketplaceListingController.php:99` |

### Hypothèses (à arbitrer produit)
| # | Zone | Constat | Fichier |
|---|------|---------|---------|
| C-11 | Orders/Payments | Un opérateur **restreint** (`user_warehouses`) ne verrait **aucune commande/paiement** standard (créés à `warehouse_id = NULL`, exclus par `WarehouseScope`). | `OrderService::create`, `PaymentService::record` |
| C-12 | Inventory | Ajustement différé applique un **comptage absolu** figé à la demande → écrase les mouvements intermédiaires si le stock a bougé avant l'approbation. | `StockAdjustmentService.php:151` |

## Trous de couverture de test (les plus risqués)
1. **POS** : encaissement **multi-paiement/split**, **remboursement/retour caisse**, **remise** — inexistants dans le code, donc non testés. *(→ chantier « caisse approfondie » : POS Desktop + mobile.)*
2. **Onboarding** end-to-end (0 spec front sur `OnboardingView`).
3. **Marketplace** adapters + jobs de sync (6 tests couvrent seulement le listing ; 0 spec front).
4. Modules front **0 spec** : `marketplace`, `onboarding`.

*Skips justifiés : 2 tests CHECK MySQL-only (`StockIntegrityTest`).*
