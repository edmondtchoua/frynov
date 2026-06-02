# Plan d'implémentation — Frynov ERP

> Document vivant — mis à jour à chaque session.  
> Dernière révision : **2026-06-02 (Sprint 14 livré — Navigation 1 niveau + TabNavs)**  
> Stratégie : backend + frontend **en parallèle** dans chaque session.

---

## État actuel — Vue globale

**Tests backend :** 477 / 477 ✅  
**Branche active :** `feature/sprint-s1s2-security-nav` → mergée dans `develop`  
**Dernière version taguée :** `v0.7.0` (Sprint 7A)

---

## État actuel par couche

### Backend (Laravel 13)

| Module | Statut | Tests | Remarques |
|--------|--------|-------|-----------|
| Infrastructure | ✅ Livré | — | Docker, CI/CD, modular system |
| Auth + Workspace | ✅ Livré | 35 | Sanctum, Spatie teams, multitenant, IDOR guard, role hierarchy, 4 rôles terrain ajoutés |
| Catalog | ✅ Livré | 55 | Products, Categories, Variants, Attributes, SKU auto, code-barres interne, GTIN validation, Labels |
| Inventory | ✅ Livré | 55 | Stock, StockMovement, Redis anti-oversell, scan, Warehouses, Transfers (state machine), Fiscal periods, CMUP async, Snapshots |
| Orders | ✅ Livré | 40 | Order lifecycle (state machine), Returns/RMA (pending→restocked), stock reservation |
| Customers | ✅ Livré | 12 | CRUD + search, orders relation, tenant isolation |
| Payments | ✅ Livré | 16 | record/void/balance, idempotency key, amount cap server-side, role guard on void |
| Delivery | ✅ Livré | 12 | pending→dispatched→delivered→failed, role guard on transitions |
| Suppliers | ✅ Livré | 8 | CRUD, code auto, findOrCreateByName |
| ImportExport | ✅ Livré | 30 | Upload→analyze→mapping→approve→execute pipeline, Excel/PDF export |
| Reports | ✅ Livré | 13 | Dashboard KPIs, sales, stock value, role:manager+ required |
| Billing | ✅ Livré | 10 | Plan, Subscription, Promotion, ManualPayment, QuotaService (5 dimensions), PromotionServiceTest |
| Platform | ✅ Livré | 28 | ErpModule registry, AuditLog HMAC (actor_role), Admin back-office, GET /admin/audit-logs, verify-chain |
| Security | ✅ Livré | 9 | RBAC sur 11 modules, EnsureUserBelongsToTenant alias, CatalogSecurityTest, PaymentSecurityTest |
| Marketplace | ✅ Livré | — | Facebook/WhatsApp/WooCommerce adapters, sync alerts |
| Sync | 💤 Stub | — | Phase 3 — domaine non défini |

**Backend total : 477 tests passent.**

---

### Frontend (Vue 3 + Vite + TypeScript)

| Couche | Statut | Remarques |
|--------|--------|-----------|
| Design system | ✅ | CSS tokens, composants, responsive |
| Auth UI | ✅ | Login, Register, Profile |
| AppLayout | ✅ | Sidebar collapsible + groupes nav (Ventes, Stock, Rapports, Import) |
| Catalogue | ✅ | ProductList + Form + Categories + Variants + Labels + CatalogTabNav |
| Inventory | ✅ | StockList + Alerts + Warehouses + Transfers + FiscalPeriods |
| Ventes | ✅ | OrderList + OrderCreate + OrderDetail + Returns + Payments + Deliveries |
| Customers | ✅ | CustomerList + CustomerDetail |
| Suppliers | ✅ | SupplierList |
| Import/Export | ✅ | ImportWizard + ImportHistory |
| Reports | ✅ | SalesReport + StockReport |
| Dashboard | ✅ | KPIs réels, chart, top produits |
| Settings | ✅ | 5 onglets (Entreprise, Équipe, Facturation, + stubs) |
| Onboarding | ⚠️ Partiel | Wizard câblé POST /api/workspace/provision, étapes needs_ manquantes |
| Admin back-office | ✅ | 7 vues admin (AdminLayout) |
| Notifications | ✅ | NotificationCenter, useNotifications, polling 30s |
| Navigation | ✅ | 42 routes, sidebar groupée, 5 groupes collapsibles |
| Vitest tests | ⚠️ Partiel | ~23 tests (auth, composables) — objectif 50+ |
| **Billing self-service** | ❌ Absent | /billing, /billing/upgrade — backend complet, frontend manquant |
| **usePermission** | ✅ | canManageStock, canManageCatalog, canManageOrders, etc. |

---

### Identifiants produit

| Fonctionnalité | Statut |
|---|---|
| SKU auto-généré (tenant_sequences) | ✅ `PROD-000001`, `VET-000001` |
| Code-barres interne (FRY + 10 chiffres) | ✅ `FRY0000000001` |
| Validation GTIN/EAN/UPC (GS1 check digit) | ✅ |
| Surcharge manuelle (manager/admin) | ✅ Toggle frontend |
| GTIN jamais généré automatiquement | ✅ Règle respectée |
| Audit trail identifiants | ⚠️ Partiel |

---

### Sécurité & RBAC

| Module | EnsureUserBelongsToTenant | Role Guard | Statut |
|---|---|---|---|
| Catalog | ✅ | ✅ manager\|admin sur writes | Complet |
| Inventory | ✅ | ✅ manager\|admin sur mutations | Complet |
| Orders | ✅ | ✅ manager\|admin sur confirm/cancel/returns | Complet |
| Payments | ✅ | ✅ manager\|admin sur void | Complet |
| Customers | ✅ | ✅ manager\|admin sur delete | Complet |
| Delivery | ✅ | ✅ manager\|admin sur dispatch/deliver | Complet |
| Suppliers | ✅ | ✅ manager\|admin sur delete | Complet |
| Reports | ✅ | ✅ manager\|admin | Complet |
| Marketplace | ✅ | ✅ manager\|admin sur writes | Complet |
| ImportExport | ✅ | ✅ manager\|admin sur approve/execute | Complet |
| Workspace provision | ✅ | ✅ manager\|admin | Complet |

### Audit Trail

| Événement | Câblé |
|---|---|
| auth.login / auth.login_failed / auth.logout | ✅ |
| order.created / confirmed / fulfilled / cancelled | ✅ |
| payment.recorded | ✅ |
| workspace.role_changed / user_activated | ✅ |
| module.activated / plan.changed | ✅ |
| security.idor_attempt | ✅ |
| stock.adjusted / stock.moved | ⚠️ Partiel |
| return.approved | ⚠️ |
| promo.applied | ⚠️ |
| product.created (SKU auto) | ⚠️ |

---

## Roadmap détaillée

### ✅ Sprint 1-7A — MVP Foundation (livré)

Tous les modules opérationnels de base : Auth, Catalog, Inventory, Orders, Customers, Payments, Delivery, Suppliers, ImportExport, Reports, Billing, Platform/Admin. **293 tests.**

### ✅ Sprint 8-9 — Sécurité & Marketplace (livré)

TenantScope global, HMAC AuditLog, Marketplace adapters (Facebook/WhatsApp/WooCommerce), Inventory Axes 1-4 (Fiscal periods, Transfers, Snapshots, CMUP async). **340 tests.**

### ✅ Sprint 10 — Retours + UI Axes + Vitest (livré)

Module Returns/RMA, StockTransferView, FiscalPeriodView, ReturnsView, returnService, tests Vitest auth/composables. **351 tests.**

### ✅ Sprint 11 (S1) — Sécurité P0 (livré)

RBAC sur 11 modules (Catalog CRITICAL, Orders, Payments, Customers, Delivery, Reports, Marketplace, Suppliers, ImportExport), EnsureUserBelongsToTenant, idempotency key payments, onboarding provision, 4 rôles opérationnels (agent/cashier/commercial/delivery). **376 tests.**

### ✅ Sprint 12 (S2) — Audit + Quotas (livré)

AuditService câblé sur 8 événements, actor_role, GET /admin/audit-logs, QuotaService, tenant_sequences, Plan quota columns (max_agents/branches/warehouses). **358 tests.**

### ✅ Sprint S1+S2 — Navigation unifiée (livré)

Sidebar refactorisée (groupes Ventes/Stock/Rapports/Import), auth store getters, usePermission composable, onboarding guard, avatar topbar, Settings stubs. **374 tests.**

### ✅ Sprint SKU/Barcode — Identifiants produit (livré)

ProductIdentifierService, tenant_sequences unifiée, GTIN validation GS1, internal_barcode, frontend auto/manuel toggle. **374 tests.**

### ✅ Sprint 13 — Billing + CountryRules + Multi-sites (LIVRÉ)

Billing self-service (BillingView, /billing, /billing/upgrade), CountryRule model, RegistrationRuleService, warehouse_id FK sur Orders/Payments, user_warehouses pivot, AuditService câblé sur stock.adjusted/product.created/return.approved/promo.applied. **477 tests.**

---

## Sprint 14 — Prochaine étape

**Objectif :** Navigation unifiée sidebar 1 niveau + TabNavs

### Backend P0

Aucun (sprint 100% frontend)

### Frontend
- [ ] InventoryTabNav (Stock / Alertes / Entrepôts / Transferts / Périodes fiscales)
- [ ] SalesTabNav (Commandes / Retours / Paiements / Livraisons)
- [ ] ReportsTabNav (Ventes / Stock / Exports)
- [ ] CatalogTabNav Attributs (onglet Attributs dans CatalogTabNav existant)
- [ ] AppLayout sidebar 1 niveau (aplatir groupes collapsibles → liens directs)

### Tests
- [ ] Vitest pour chaque TabNav (InventoryTabNav, SalesTabNav, ReportsTabNav, CatalogTabNav)

---

## Critères MVP (Go/No-Go beta)

- [x] Authentification multitenant fonctionnelle
- [x] Catalogue produits complet avec étiquettes + identifiants
- [x] Stock suivi en temps réel (entrée/sortie/inventaire/transferts)
- [x] Commandes créées et tracées jusqu'à la livraison
- [x] Paiements enregistrés (cash + Mobile Money)
- [x] Retours/SAV gérés
- [x] Dashboard avec CA et stock du jour
- [x] Sécurité RBAC sur tous les modules critiques
- [x] Audit trail sur événements principaux
- [x] Navigation claire et cohérente
- [ ] App POS offline basique — Phase 2
- [x] Tests backend 350+ (477 actifs)
- [ ] 50+ tests frontend Vitest — en cours (~23)
- [x] Billing self-service (upgrade plan) — Sprint 13
- [x] CountryRules (inscription par pays) — Sprint 13
