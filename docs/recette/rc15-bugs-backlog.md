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

## Corrigés dans rc.152 (RC-20 — file BASSE)

| # | Zone | Bug | Correctif |
|---|------|-----|-----------|
| C-8 | Orders/Catalog | Journaux d'audit jamais écrits (TypeError avalé). | ✅ Appels `AuditService::log` en arguments nommés (`return.approved`, `product.created`, `product.archived`). +1 assert. |
| C-9/P-3 | Orders/Inventory/Suppliers | Numéros `RET-`/`TRF-`/`SUP-` via `count()+1` (course). | ✅ `SequenceService` partagé (table `sku_sequences` + `FOR UPDATE`, seed de continuité) — même mécanique que `ORD-`. |
| C-10 | Payments (front) | `X-Idempotency-Key` jamais envoyée. | ✅ Clé UUID générée par `paymentService.record` (retry/double clic → même paiement). |
| N-6 | Notifications | Remboursement + re-livraison si l'update post-`deliver` échoue. | ✅ Flag `delivered` : message parti → ni refund, ni retour `pending` (marquage `sent` best-effort). |
| B-7 | Billing | `recordUse` sans verrou → dépassement `max_uses`. | ✅ Promo verrouillée `FOR UPDATE` + limite re-vérifiée dans la transaction (throw si atteinte). +1 test. |
| P-4 | ImportExport | Doublons SKU intra-fichier `VALID` à l'analyse. | ✅ Suivi des SKU du fichier : 2ᵉ occurrence → SKIP + warning (ligne d'origine indiquée). +2 tests. |
| P-5 | Orders/POS | `customer_id` non validé au tenant. | ✅ Vérifié dans `OrderService::create` (couvre aussi le POS) → 422. +1 test. |
| P-6 | Marketplace | Label « Woocommerce ». | ✅ « WooCommerce ». |

### Environnement local (découvertes de la validation preview, rc.152)
- **Le blocage `/api/*` en preview n'était PAS une contrainte sandbox** : un serveur d'un autre projet
  (`C:\Users\pro\Source\PHP\ace\server.php`) squattait le port 8000 → 423 `app_locked` (son middleware)
  et 404. Après libération du port, **toute l'API répond en preview** (login, caisse, rapports…).
- Base MySQL locale : `default_storage_engine=MyISAM` → 78 tables **sans transactions ni verrous**.
  Converties en InnoDB + `engine=InnoDB` forcé dans `config/database.php` (les tests SQLite ne voyaient
  rien ; l'app dépend de `lockForUpdate`).
- `DemoSeeder` : lookup `fiscal_periods` non idempotent sous MySQL (colonnes DATE vs `endOfMonth()`
  23:59:59) → corrigé (`toDateString()`).

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
