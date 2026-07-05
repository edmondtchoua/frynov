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

## Backlog — à traiter (priorisé)

### Haute
| # | Zone | Bug | Fichier |
|---|------|-----|---------|
| M-1 | Billing | **Ledger `tenant_credits` en écriture seule** : les trop-perçus sont crédités mais **jamais réappliqués** (proration/upgrade lit `metadata`, pas le ledger) → argent client perdu. `consume()`/`balance()` appelés par aucun code applicatif. | `TenantCreditService.php:42`, `ManualPaymentService.php:234`, `SubscriptionService.php:59` |
| M-2 | Reports | **Code mort applicatif** : `abcClassification`, `inventoryKpis`, `stockReconciliation` développés+testés mais **sans route ni appel front** → exposer ou retirer. | `ReportService.php:310,370,428` |

### Moyenne
| # | Zone | Bug | Fichier |
|---|------|-----|---------|
| D-3 | Digital | Clés de **pool de licence jamais libérées** à la révocation/retour (RMA) → fuite, épuisement prématuré, fausse alerte `pool_exhausted`. | `DigitalService.php:112,127,147` |
| M-4 | Billing | `RenewalService::isFreePlan` lit les **colonnes legacy**, ignore les prix localisés `PlanPrice` → mauvais classement à l'échéance (facturé à tort / jamais facturé). | `RenewalService.php:185` |
| M-5 | Billing | Conflit **promo apply ↔ approve** : `applyPromo` crée un `PromoUse` immédiat que `validate` voit « déjà utilisé » → paiement légitime routé en `needs_review`. | `BillingController.php:105` ↔ `ManualPaymentService.php:124` |
| C-3 | Orders (front) | **Pagination cassée** : le back renvoie un paginator à plat (`data`, pas `meta`), le front lit `res.meta` → pages 2+ inaccessibles. | `OrderListView.vue:150`, `OrderController.php:36` |
| C-4 | Orders | **Filtres recherche/dates ignorés** côté serveur (le contrôleur ne lit que `status`/`warehouse_id`). | `OrderController.php:27` |
| C-5 | Orders (front) | Actions retour (approve/restock/reject) **sans try/catch** → échecs muets (unhandled rejection). | `ReturnsView.vue:130` |
| C-6 | Orders/Inventory | Ligne suivie par **lot** : dispo contrôlée sur l'agrégat (inclut les lots périmés) mais FEFO exclut les périmés → **vente possible contre stock périmé** + dérive agrégat/lots. | `OrderService.php:252`, `BatchService.php:80` |
| C-7 | Inventory | `quantity_after` **faux (+quantité)** dans l'historique des mouvements en import groupé (double addition). | `StockService.php:196` |

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
