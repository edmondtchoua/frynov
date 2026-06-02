# Plan d'implémentation — Frynov ERP

> Document vivant — mis à jour à chaque fin de sprint.
> **Dernière révision : 2026-06-02 · Sprint 14 livré**
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
| Tests backend | **477 / 477 ✅** (2 skipped) |
| Branche `develop` | `98afc21` — Sprint 14 livré |
| Dernière tag | `v0.7.0` (Sprint 7A) |
| Dernière PR | #2 `feature/sprint-13` → `main` |

---

## Modules backend — état complet

| Module | Statut | Tests | Dernière évolution |
|---|---|---|---|
| Infrastructure | ✅ | — | Docker, CI/CD, modular system |
| Auth + Workspace | ✅ | 35 | Sanctum, Spatie teams, IDOR guard, 4 rôles terrain, CountryRules, onboarded flag |
| Catalog | ✅ | 69 | Products, Categories, Variants, Attributes, SKU auto (`tenant_sequences`), code-barres interne `FRY*`, GTIN validation GS1, Labels |
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
| Fournisseurs | ✅ | — | SupplierList (detail = placeholder) |
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
| **RBAC sidebar frontend** | ❌ | — | ⚠️ Aucun guard v-if sur les items sidebar |
| **RBAC onglets TabNav** | ❌ | — | ⚠️ Aucun guard sur les onglets frontend |

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
| `stock.moved_out` | ⚠️ Non câblé | Sprint 15 |
| `product.updated/archived` | ⚠️ Non câblé | Sprint 15 |
| `customer.created/updated` | ⚠️ Non câblé | Sprint 15 |

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

---

## Roadmap — prochains sprints

### Sprint 15 — RBAC frontend + Filtres + Complétion (priorité)

**Backend (~4h)**
- Câbler `stock.moved_out`, `product.updated`, `customer.created` dans l'audit trail
- Corriger `suppliers.show` (SupplierDetailView.vue manquant)

**Frontend (~8h)**
- RBAC guards sur les onglets TabNav (prop `allowedTabs` depuis `usePermission`)
- RBAC guards sur les items sidebar (v-if basé sur rôle/modules actifs)
- Filtres `OrderListView` : date, texte, client (manquants depuis l'audit)
- `SupplierDetailView.vue` (vue de détail fournisseur)
- Vitest : compléter à 50+ tests (ReportsTabNav, Settings, Dashboard)

**Tests attendus :** +10 tests backend, +10 Vitest

---

### Sprint 16 — POS Web MVP + Agents terrain (Phase 2)

**Backend**
- Module POS : `CashRegisterSession` model + migration
- Endpoint : `POST /api/pos/sessions` (ouvrir/fermer session caisse)
- Rôle caissier : permissions POS spécifiques

**Frontend**
- `PosView.vue` : panier, scan produit, encaissement
- Interface simplifiée pour caissier sur tablette

---

### Sprint 17 — Multi-sites complet + Agents

**Backend**
- `Branch` model (alias Warehouse avec metadata agence)
- `user_warehouses` — scoping accès par agence
- Filtres rapports par warehouse/branche

**Frontend**
- Filtre entrepôt/branche sur OrderListView, PaymentListView, StockListView
- Page Agences/Branches dans Settings > Entreprise

---

### Sprint 18 — CountryRules UI + Onboarding complet

**Backend**
- Admin UI pour gérer les CountryRules (super-admin)

**Frontend**
- Onboarding : étapes `needs_stock/pos/delivery/ecommerce/offline` + `nb_branches`
- OnboardingView → provision backend câblé complètement
- Redirection `/onboarding` si `tenant.onboarded = false`

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
| ⚠️ RBAC sidebar/onglets frontend | Sprint 15 |
| ⚠️ Filtres commandes (date, texte, client) | Sprint 15 |
| ❌ App POS offline | Phase 2 |
| ✅ 350+ tests backend | 477 tests ✅ |
| ⚠️ 50+ tests Vitest frontend | ~30 actifs |
| ⚠️ Billing self-service complet | Sprint 13 partiel |
| ❌ CountryRules UI admin | Sprint 18 |
