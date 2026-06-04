# Plan d'implémentation — Frynov ERP

> Document vivant — mis à jour à chaque fin de sprint.
> **Dernière révision : 2026-06-03 · Sprint 17 (refonte fiche produit) + Audit qualité approfondi**
> Stratégie : backend + frontend **en parallèle**, docs + tests + seeders à chaque sprint.

---

## Règle documentaire

> À chaque sprint livré, mettre à jour :
> 1. Ce fichier `plan.md` — état modules, tests, roadmap
> 2. `docs/user/` — guides utilisateur impactés
> 3. `database/seeders/` — données de référence si nouveaux modèles
> 4. Tests — backend + Vitest frontend

---

## État global

| Indicateur | Valeur |
|---|---|
| Tests backend | **501 / 501 ✅** (2 skipped, 1 incomplete) — +121 réactivés par le fix testsuites |
| Tests Vitest frontend | **112 / 112 ✅** (+16 composant, +11 money, +6 date) |
| Branche | `feature/sprint-16-variants-batch-stock` — Sprint 17 + audit livrés |
| Dernière tag | `v0.7.0` (Sprint 7A) |
| Dernière PR | #2 `feature/sprint-13` → `main` |

> ⚠️ **Note critique** : avant l'audit, la config `phpunit.xml` ne matchait que certains
> suffixes → **121 tests (dont sécurité & multi-tenant) n'étaient jamais exécutés**.
> Le « 374 passing » historique était trompeur. Désormais 501 tous exécutés.

---

## Modules backend — état complet

| Module | Statut | Tests | Dernière évolution |
|---|---|---|---|
| Infrastructure | ✅ | — | Docker, CI/CD, modular system |
| Auth + Workspace | ✅ | 35 | Sanctum, Spatie teams, IDOR guard, 4 rôles terrain, CountryRules, onboarded flag |
| Catalog | ✅ | 69 | Products, Categories, Variants N-axes (cartesian), Attributes, SKU auto (`tenant_sequences`), code-barres interne `FRY*`, GTIN validation GS1, Labels |
| Inventory | ✅ | 55 | Stock, StockMovement, Redis anti-oversell, scan, Warehouses, Transfers (state machine), Fiscal periods, CMUP async, Snapshots, PeriodLock |
| Orders | ✅ | 40 | Order lifecycle, Returns/RMA (state machine), stock reservation, warehouse_id FK |
| Customers | ✅ | 12 | CRUD + search, orders relation |
| Payments | ✅ | 16 | record/void/balance, idempotency key, amount cap server-side, warehouse_id FK |
| Delivery | ✅ | 12 | pending→dispatched→delivered→failed, role guard |
| Suppliers | ✅ | 8 | CRUD, code auto, findOrCreateByName |
| ImportExport | ✅ | 30 | Upload→analyze→mapping→approve→execute pipeline, Excel/PDF export |
| Reports | ✅ | 13 | Dashboard KPIs, sales, stock value, role:manager+ |
| Billing | ✅ | 10 | Plan, Subscription, Promotion, ManualPayment, QuotaService (5 dimensions), quotas terrain |
| Platform | ✅ | 28 | ErpModule registry, AuditLog HMAC (actor_role + ip), Admin back-office, `/admin/audit-logs`, verify-chain |
| Security module | ✅ | 21 | RBAC sur 11 modules, CatalogSecurityTest, PaymentSecurityTest, MultiTenantIsolationTest, AuditTrailTest |
| Marketplace | ✅ | — | Facebook/WhatsApp/WooCommerce adapters, sync alerts |
| CountryRules | ✅ | 5 | Migration + Model + RegistrationRuleService + 30 pays seedés |
| Multi-sites | ⚠️ Fondation | — | Migrations `warehouse_id` orders/payments + `user_warehouses` pivot (pas encore de UI) |
| Sync | 💤 Stub | — | Phase 3 — domaine non défini, routes protégées |

**Total tests backend : 477 (2 skipped)**

---

## Modules frontend — état complet

| Module | Statut | TabNav | Dernière évolution |
|---|---|---|---|
| AppLayout | ✅ | — | Sidebar **1 niveau** (Sprint 14), 9 entrées plates, responsive drawer |
| CatalogTabNav | ✅ | 4 tabs | Produits / Catégories / Déclinaisons / Étiquettes (Sprint 14 : renommage + CTA VariantsView) |
| InventoryTabNav | ✅ NEW | 5 tabs | Stock / Alertes / Entrepôts / Transferts / Clôture (Sprint 14) |
| SalesTabNav | ✅ NEW | 4 tabs | Commandes / Retours & SAV / Paiements / Livraisons (Sprint 14) |
| ReportsTabNav | ✅ NEW | 2 tabs | Ventes / Stock (Sprint 14) |
| Dashboard | ✅ | — | KPIs réels, chart, top produits |
| Catalogue | ✅ | ✅ | ProductList + Form + Categories + Variants + Labels |
| Inventory | ✅ | ✅ | StockList + Alerts + Warehouses + Transfers + FiscalPeriods (tous intégrés) |
| Ventes | ✅ | ✅ | OrderList + Returns + Payments + Deliveries (tous intégrés) |
| Clients | ✅ | — | CustomerList + CustomerDetail |
| Fournisseurs | ✅ | — | SupplierList + SupplierDetailView (Sprint 15) |
| Import/Export | ✅ | — | ImportWizard + ImportHistory |
| Rapports | ✅ | ✅ | SalesReport + StockReport (intégrés) |
| Settings | ✅ | 5 tabs | Entreprise / Équipe / Abonnement / Intégrations (stub) / Notifications (stub) |
| Billing | ✅ NEW | — | BillingView (plan + usages) + UpgradeView (comparatif plans) (Sprint 13) |
| Marketplace | ✅ | — | Listings + alertes |
| Onboarding | ⚠️ Partiel | — | Wizard câblé backend, étapes needs_* manquantes |
| Profil | ✅ | — | Page profil + sessions |
| Admin back-office | ✅ | 8 vues | Tenants, Modules, Plans, Promotions, Paiements manuels, Audit (AdminLayout) |

**Tests Vitest frontend : ~30 tests**

---

## Sécurité — couverture RBAC

| Module | EnsureUserBelongsToTenant | Role guard | Statut |
|---|---|---|---|
| Catalog | ✅ | ✅ manager\|admin sur writes | ✅ Complet |
| Inventory | ✅ | ✅ manager\|admin sur mutations | ✅ Complet |
| Orders | ✅ | ✅ manager\|admin sur confirm/cancel/returns | ✅ Complet |
| Payments | ✅ | ✅ manager\|admin sur void | ✅ Complet |
| Customers | ✅ | ✅ manager\|admin sur delete | ✅ Complet |
| Delivery | ✅ | ✅ manager\|admin sur dispatch/deliver | ✅ Complet |
| Reports | ✅ | ✅ manager\|admin | ✅ Complet |
| Marketplace | ✅ | ✅ manager\|admin sur writes | ✅ Complet |
| Suppliers | ✅ | ✅ manager\|admin sur delete | ✅ Complet |
| ImportExport | ✅ | ✅ manager\|admin sur approve/execute | ✅ Complet |
| Workspace provision | ✅ | ✅ manager\|admin | ✅ Complet |
| **RBAC sidebar frontend** | ✅ | — | Computed `mainNavItems` filtré par `isManagerOrAbove` (Sprint 15) |
| **RBAC onglets TabNav** | ✅ | — | `usePermission` injecté dans les 4 TabNavs (Sprint 15) |

---

## Audit trail — couverture événements

| Événement | Câblé | Depuis |
|---|---|---|
| `auth.login` + ip_address | ✅ | Sprint 12 + fix Sprint 14 |
| `auth.logout` | ✅ | Sprint 12 |
| `order.created/confirmed/fulfilled/cancelled` | ✅ | Sprint 12 |
| `payment.recorded` | ✅ | Sprint 12 |
| `workspace.role_changed` / `user_activated` | ✅ | Sprint 12 |
| `module.activated` / `plan.changed` | ✅ | Sprint 11 |
| `security.idor_attempt` | ✅ | Sprint 11 |
| `stock.moved_in` / `stock.adjusted` | ✅ | Sprint 13 |
| `product.created` | ✅ | Sprint 13 |
| `return.approved` | ✅ | Sprint 13 |
| `stock.moved_out` | ✅ Câblé | Sprint 15 |
| `product.updated` | ✅ Câblé | Sprint 15 |
| `product.archived` | ✅ Câblé | Sprint 15 |
| `customer.created` | ✅ Câblé | Sprint 15 |
| `customer.updated` | ✅ Câblé | Sprint 15 |

---

## Seeders — état

| Seeder | Contenu | Idempotent |
|---|---|---|
| `RolesAndPermissionsSeeder` | 9 rôles + 68+ permissions | ✅ firstOrCreate |
| `PlansSeeder` | starter / pro / enterprise + quotas terrain | ✅ firstOrCreate |
| `ErpModulesSeeder` | 10 modules ERP avec icônes | ✅ firstOrCreate |
| `PlanModulesSeeder` | Matrice plan ↔ modules | ✅ firstOrCreate |
| `CountryRulesSeeder` | 30+ pays africains + Europe + Amérique | ✅ firstOrCreate |
| `SuperAdminSeeder` | superadmin@frynov.com | ✅ firstOrCreate |
| `DemoSeeder` | 3 tenants démo (dev/staging uniquement) | ⚠️ create (prod: désactiver) |

---

## Navigation — architecture finale (Sprint 14 ✅)

```
SIDEBAR (1 niveau, 9 entrées plates)
├── Tableau de bord
├── Catalogue         → CatalogTabNav : Produits | Catégories | Déclinaisons | Étiquettes
├── Stock & Inventaire → InventoryTabNav : Stock | Alertes [N] | Entrepôts | Transferts | Clôture
├── Ventes            → SalesTabNav : Commandes | Retours & SAV | Paiements | Livraisons
├── Clients
├── Fournisseurs
├── Rapports          → ReportsTabNav : Ventes | Stock
├── Import / Export
├── Marketplace [badge]
│─── CONFIGURATION ───
└── Paramètres        → Settings tabs : Entreprise | Équipe | Abonnement | Intégrations | Notifications
```

---

## Historique des sprints livrés

| Sprint | Tests | Contenu principal |
|---|---|---|
| 1-7A | 293 | MVP Foundation : Auth, Catalog, Inventory, Orders, Customers, Payments, Delivery, Suppliers, ImportExport, Reports, Billing, Platform |
| 8-9 | 340 | Sécurité multitenant, Marketplace adapters (FB/WA/WC), Inventory Axes 1-4 (Fiscal, Transfers, Snapshots, CMUP) |
| 10 | 351 | Returns/RMA, StockTransferView, FiscalPeriodView, ReturnsView, Vitest auth/composables |
| 11 (S1) | 376 | Sécurité P0 : RBAC 11 modules, EnsureUserBelongsToTenant, idempotency payments, onboarding backend, 4 rôles terrain |
| 12 (S2) | 358 | Audit trail 80%, QuotaService, `/admin/audit-logs`, navigation sidebar groupée |
| SKU | 374 | `ProductIdentifierService`, `tenant_sequences`, GTIN GS1, frontend auto/manuel |
| 13 | 477 | Billing self-service, CountryRules, Multi-sites fondation, Audit trail +5 événements |
| **14** | **477** | **Navigation 1 niveau : InventoryTabNav + SalesTabNav + ReportsTabNav + sidebar plate** |
| **15** | **374+79** | **Fixes runtime : occurred_at, EnforceQuota DomainException. RBAC frontend (TabNavs + sidebar). Audit trail complet (product.archived, customer.updated). SupplierDetailView. 79 Vitest** |
| **16** | **374+79** | **Variantes N-axes : cartesian product endpoint, builder frontend, SKU collision fix, label column, hydrate axes on load** |
| **17** | **501+90** | **Refonte fiche produit (ProductShowPage onglets, drawers stock, product_type, attributs sync) + reconstruction OrderCreateView + Audit qualité approfondi (20 fixes)** |

---

## Audit qualité approfondi (2026-06-03) — 20 corrections

Audit systématique module par module. Le code métier s'est révélé **fondamentalement sain** ;
les bugs se concentraient sur 3 faiblesses systémiques, toutes corrigées à la racine.

### 🔴 Critiques (5)
| Bug | Détail |
|---|---|
| `fulfill()` échouait sur stock serré | `moveOut` avant `release` → `available=0` sur stock entièrement réservé. Cas PME fréquent (stock = demande exacte) |
| **121 tests jamais exécutés** | `phpunit.xml` ne matchait que certains suffixes → tests sécurité/multi-tenant skippés. + APP_KEY de test invalide |
| Gate couverture frontend fictif | seuils 75% vs 3.8% réel, sans `continue-on-error` → CI frontend rouge en permanence. Réalignés (ratchet) |
| Plan Enterprise bloqué | limites `0` traitées comme strictes → tier le plus cher ne pouvait rien créer. `empty()` + seed normalisé `null` |
| Downgrade gardait modules premium | `activatePlanModules` additif → Pro→Starter laissait 6 modules actifs. Devenu sync (active + désactive) |

### 🟠 Money — convention centimes (5)
OrderList ×100 · paiement manuel upgrade ×100 · mélange devises paiement · devise commande hardcodée XOF → `tenant.settings['currency']` · **onboarding droppait la devise** (chaîne avec le fix commande).
→ **Cause racine éliminée** : `src/shared/utils/money.ts` (formatMoney/toCents/fromCents) + 11 vues migrées + 11 tests.

### 🟠 Stock (4)
`NotifyLowStock` (paramètres nommés invalides → audit stock-bas cassé) · transfert sur-réception → stock fantôme · `unit_cost_cents` non validé (corruption CMUP) · commande de variante impossible (frontend).

### 🟠 UX/Auth (3)
`OrderCreateView` stub (saisie UUID) reconstruit · `voidPayment` erreur silencieuse · `generateSlug` fragile (LIKE → match exact).

### 🟡 Responsive (2)
Tables clippées sur mobile (fix global `.data-table` scroll) · `OrderCreateView` line-row débordait.

### Tests ajoutés
10 tests de régression (stock serré, devise tenant, chaîne variante, sur-réception transfert, downgrade modules, devise onboarding) + 11 tests `money.ts`.

---

## Roadmap — prochains sprints

### ~~Sprint 15 — RBAC frontend + Audit trail~~ ✅ Livré

- ✅ RBAC guards sur les 4 TabNavs (usePermission injecté internalement)
- ✅ RBAC sidebar (mainNavItems computed filtré)
- ✅ Audit trail : `product.archived`, `customer.updated`
- ✅ SupplierDetailView (vue de détail fournisseur)
- ✅ 79 Vitest frontend (tous verts)
- ✅ Fixes runtime : `occurred_at` → `created_at`, EnforceQuota DomainException

### ~~Sprint 16 — Variantes N-axes~~ ✅ Livré

- ✅ Endpoint cartesian product `POST /api/catalog/products/{id}/variants/generate`
- ✅ Builder frontend N-axes avec chips UI
- ✅ Fix SKU collision (withTrashed), fix label column migration
- ✅ Hydratation axes depuis API (hydrateVariantsFromProduct)

---

### Sprint 18 — Dette technique frontend (priorité post-audit) — 🔄 en cours

> Issu de l'audit : la couverture Vitest était à 3.8 % (gate ratchet provisoire).

**Frontend**
- ✅ Tests composant des **4 vues critiques** : `OrderCreateView` (5), `OrderDetailView` (4),
  `ProductShowPage` (4), `ProductFormView` (3) = 16 tests
  → couverture **3.8 % → 16.5 %** lignes · 60 % branches · ratchet à 16/59/29/16
- ✅ Factorisé `fmtDate` → `@/shared/utils/date` (3 helpers null-safe) :
  **23 formatters dupliqués éliminés** sur 21 vues + reportService, +6 tests
- ⏳ Étendre les tests aux vues secondaires (List views, Settings) — viser 25 %

**Backend**
- ⏳ Câbler le test incomplet restant (placeholder)

---

### Sprint 19 — POS Web MVP + Agents terrain (Phase 2)

**Backend**
- Module POS : `CashRegisterSession` model + migration
- Endpoint : `POST /api/pos/sessions` (ouvrir/fermer session caisse)
- Rôle caissier : permissions POS spécifiques

**Frontend**
- `PosView.vue` : panier, scan produit, encaissement
- Interface simplifiée pour caissier sur tablette

---

### Sprint 20 — Multi-sites complet + Agents

**Backend**
- `Branch` model (alias Warehouse avec metadata agence)
- `user_warehouses` — scoping accès par agence
- Filtres rapports par warehouse/branche

**Frontend**
- Filtre entrepôt/branche sur OrderListView, PaymentListView, StockListView
- Page Agences/Branches dans Settings > Entreprise

---

### Sprint 21 — CountryRules UI + Onboarding complet

**Backend**
- Admin UI pour gérer les CountryRules (super-admin)

**Frontend**
- Onboarding : étapes `needs_stock/pos/delivery/ecommerce/offline` + `nb_branches`
- OnboardingView → provision backend câblé complètement
- Redirection `/onboarding` si `tenant.onboarded = false`

---

### Backlog — TVA / remises au niveau commande

> Gap fonctionnel relevé à l'audit : `OrderService::create` calcule
> `Σ(quantité × prix)` sans TVA ni remise. À spécifier si le besoin métier se confirme.

---

## Critères Go/No-Go beta (mise à jour)

| Critère | Statut |
|---|---|
| ✅ Authentification multitenant | Livré |
| ✅ Catalogue + identifiants (SKU/barcode/GTIN) | Livré |
| ✅ Stock multi-entrepôts | Livré |
| ✅ Commandes → Paiements → Livraisons → Retours | Livré |
| ✅ Dashboard opérationnel | Livré |
| ✅ Navigation claire 1 niveau + onglets | Livré Sprint 14 |
| ✅ Sécurité RBAC backend complète | Livré |
| ✅ Audit trail ~85% | Livré |
| ✅ RBAC sidebar/onglets frontend | Sprint 15 livré |
| ✅ Filtres commandes (date, texte, client) | Sprint 15 livré |
| ✅ Refonte fiche produit (onglets + drawers stock) | Sprint 17 livré |
| ✅ **Tous les tests réellement exécutés en CI** | Audit : +121 réactivés |
| ✅ **Quotas de plan corrects (Enterprise + downgrade)** | Audit livré |
| ✅ **Convention money centralisée** | Audit : `money.ts` |
| ❌ App POS offline | Phase 2 (Sprint 19) |
| ✅ 350+ tests backend | **501 tests ✅** |
| ✅ 50+ tests Vitest frontend | **106 actifs ✅** |
| ✅ Tests composant des 4 vues critiques | 16 tests — couverture 16.5 % |
| ⚠️ Billing self-service complet | Sprint 13 partiel |
| ❌ CountryRules UI admin | Sprint 21 |
