# Plan d'implémentation — Nexora ERP

> Document vivant — mis à jour à chaque session.  
> Dernière révision : **2026-05-31 (Sprint 7A)**  
> Stratégie : backend + frontend **en parallèle** dans chaque session.

---

## État actuel par couche

### Backend (Laravel 13)

| Module | Statut | Tests | Remarques |
|--------|--------|-------|-----------|
| Infrastructure | ✅ Livré | — | Docker, CI/CD, modular system |
| Auth | ✅ Livré | 30 | Sanctum, Spatie teams, multitenant |
| Catalog | ✅ Livré | 36 | Products, Categories, Variants, SKU, QR/Barcode, Labels |
| Inventory | ✅ Livré | 25 | Stock, StockMovement, Redis anti-oversell, scan-to-action |
| Orders | ✅ Livré | 26 | Order lifecycle, stock reservation, anti-oversell, 6 endpoints |
| Customers | ✅ Livré | 25 | CRUD + search + typeahead, orders relation, tenant isolation |
| Payments | ✅ Livré | 23 | record/void/balance/isFullyPaid, split payments, tenant isolation |
| Delivery | ✅ Livré | 26 | pending→dispatched→delivered→failed, order relation, tenant isolation |
| **Suppliers** | ✅ Livré | 14 | CRUD, code auto, findOrCreateByName, tenant isolation |
| **ImportExport** | ✅ Livré | 21 | Upload→analyze→mapping→approve→execute pipeline, Horizon jobs, Excel/PDF export |
| **Reports** | ✅ Livré | 22 | Dashboard KPIs, sales by period, stock value, top products, payment breakdown |
| **Billing** | ✅ Livré | 6 | Plan model (starter/pro/enterprise), Subscription lifecycle (trialing/active/suspended/cancelled/pending_approval), SubscriptionService |
| **Platform** | ✅ Livré | 18 | ErpModule registry, TenantModule activation, AuditLog, ModuleRegistryService, AuditService, Admin back-office API (5 controllers) |
| Sync | 💤 Différé | — | Phase 2 |

**Backend MVP : 100% complet. Tests : 293 passent.**

---

### Frontend (Vue 3 + Vite + TypeScript + PrimeVue 4)

| Couche | Statut | Remarques |
|--------|--------|-----------|
| Stack / config | ✅ Livré | package.json `nexora-erp-frontend`, vite, tsconfig, vitest |
| **Design system** | ✅ Livré | CSS custom properties, tokens couleurs, composants utilitaires |
| **NexoraLogo** | ✅ Livré | Composant SVG réutilisable, 3 variantes (light/dark/color) |
| Foundation (API client, router, stores) | ✅ Livré | Axios interceptors, Pinia auth, guards |
| **AppLayout** | ✅ Livré | Sidebar responsive, hamburger mobile, sidebar overlay, SVG nav |
| **AuthLayout** | ✅ Livré | Logo Nexora, fond gradient, footer marque |
| Auth UI (login, register) | ✅ Livré | LoginView, RegisterView (4 champs, jauge force MDP) |
| **Landing page** | ✅ Livré | Hero, features, how-it-works, modules, FAQ accordéon, footer |
| **Onboarding wizard** | ✅ Livré | 5 étapes, card-choices, auto-suggestion modules, transitions |
| **Dashboard** | ✅ Livré | KPIs réels (API), revenue bar chart SVG, commandes récentes, top produits |
| **Settings** | ✅ Livré | 5 onglets (entreprise, équipe, abonnement, intégrations, notifs) |
| Orders UI | ✅ Livré | OrderListView, OrderCreateView, OrderDetailView |
| Catalog UI | ✅ Livré | ProductListView, ProductFormView, CategoryListView |
| Customers UI | ✅ Livré | CustomerListView (avatars, modal inline), CustomerDetailView |
| **Inventory UI** | ✅ Livré | StockListView (move modal), StockAlertsView (cards + progress bar), MovementHistoryView (timeline) |
| **Payments UI** | ✅ Livré | PaymentListView (global ledger, void), OrderDetailView enrichi (balance panel + modal) |
| **Delivery UI** | ✅ Livré | DeliveryListView (dispatch/deliver/fail actions), OrderDetailView panel livraison |
| **Suppliers UI** | ✅ Livré | SupplierListView (CRUD modal, code badge, pagination) |
| **Import/Export UI** | ✅ Livré | ImportWizardView (5 étapes, polling), ImportHistoryView (filtres, modal détail) |
| **Reports UI** | ✅ Livré | Dashboard KPIs réels, SalesReportView (chart + top produits + méthodes), StockReportView (valeur + alertes) |
| **Admin back-office** | ✅ Livré | AdminLayout (dark sidebar), AdminDashboardView (KPIs + recent), TenantListView (search/filter/suspend), ModuleListView (toggle visibility/status), PlanListView, AuditLogView |

**Frontend MVP : 100% complet.**

---

### Mobile Flutter (POS)

| Couche | Statut |
|--------|--------|
| Projet Flutter | ⏳ Phase 3 (mois 5-7) |
| Foundation + offline | ⏳ |
| POS caisse | ⏳ |
| Inventory scan | ⏳ |

---

### Documentation

| Section | Statut |
|---------|--------|
| Architecture, guides dev | ✅ |
| Auth, Catalog, Inventory (tech + user) | ✅ |
| Orders (tech + API + user) | ✅ |
| Suppliers (tech + API + user) | ✅ |
| Import/Export (tech + API + user) | ✅ |
| Reports (tech + API + user) | ✅ |
| Customers, Payments, Delivery | ⏳ À compléter |

---

## Design system — Nexora ERP

### Identité visuelle

| Token | Valeur | Usage |
|-------|--------|-------|
| `--brand-primary` | `#10b981` | Vert émeraude — CTA primaire |
| `--brand-primary-dark` | `#059669` | Hover boutons primaires |
| `--brand-secondary` | `#3b82f6` | Bleu — liens, boutons secondaires |
| `--sidebar-bg` | `#1e293b` | Slate foncé — sidebar app |
| `--color-error` | `#ef4444` | Erreurs, alertes stock |
| `--color-warning` | `#f59e0b` | Avertissements |
| `--radius-lg` | `10px` | Cards, panels |
| `--sidebar-width` | `248px` | Largeur sidebar desktop |
| `--topbar-height` | `60px` | Hauteur barre haute |

### Composants globaux (`src/assets/main.css`)

- `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-blue`, `.btn-danger`, `.btn-ghost`
- `.btn-sm`, `.btn-lg`, `.btn-xl`
- `.badge-*` (success/warning/error/gray/blue)
- `.alert`, `.alert-error`, `.alert-success`
- `.data-table` avec hover rows
- `.form-group`, `.form-input`, `.form-label`, `.form-error`
- `.card`, `.page-header`
- `.spinner-sm`, `.spinner-white`
- `.empty-state`, `.loading-center`
- `.hide-mobile`, `.show-mobile-only`

### Responsive breakpoints

| Breakpoint | Valeur | Impact |
|-----------|--------|--------|
| Mobile | `≤ 768px` | Sidebar fixe + hamburger, grids 1 col, textes réduits |
| Tablet | `≤ 1024px` | Grids 2 cols |
| Desktop | `> 1024px` | Layout standard 3 cols |

---

## Architecture frontend

```
src/
├── assets/
│   └── main.css                 ← Design system complet (CSS custom props)
├── layouts/
│   ├── AppLayout.vue            ← Shell app (sidebar + topbar responsive)
│   └── AuthLayout.vue           ← Centré, NexoraLogo, carte
├── pages/
│   └── LandingView.vue          ← Landing page publique (autonome, sans shell)
├── shared/
│   └── components/
│       └── NexoraLogo.vue       ← Logo SVG réutilisable
├── modules/
│   ├── auth/views/
│   │   ├── LoginView.vue
│   │   └── RegisterView.vue     ← 4 champs + jauge force mot de passe
│   ├── onboarding/views/
│   │   └── OnboardingView.vue   ← Wizard 5 étapes, card-choices
│   ├── dashboard/views/
│   │   └── DashboardView.vue    ← KPIs réels + bar chart SVG + top produits
│   ├── settings/views/
│   │   └── SettingsView.vue     ← 5 onglets (entreprise, équipe, facturation...)
│   ├── catalog/views/           ← ProductListView, ProductFormView, CategoryListView
│   ├── inventory/views/         ← StockListView, StockAlertsView, MovementHistoryView
│   ├── orders/views/            ← OrderListView, OrderCreateView, OrderDetailView
│   ├── customers/views/         ← CustomerListView, CustomerDetailView
│   ├── suppliers/views/         ← SupplierListView (CRUD modal)
│   ├── import-export/views/     ← ImportWizardView (5 étapes), ImportHistoryView
│   └── reports/views/           ← SalesReportView, StockReportView
└── router/
    ├── index.ts                 ← Routes modulaires, lazy loading
    └── guards.ts                ← Auth guard, tenant guard
```

### Système de layouts (App.vue)

```
meta.layout = undefined  →  <RouterView />  (page gère son propre shell)
meta.layout = 'auth'     →  <AuthLayout>    (login, register)
meta.layout = 'app'      →  <AppLayout>     (toutes les vues authentifiées)
meta.layout = 'admin'    →  <AdminLayout>   (back-office super-admin uniquement)
```

Pages sans layout (`meta.public: true` sans `meta.layout`) : landing, onboarding.

Routes `/admin/*` nécessitent `meta.requiresSuperAdmin: true` — guard redirige vers `/dashboard` si non super-admin.

---

## Stratégie d'implémentation

### Règle principale

**Chaque session = 1 module backend + vues frontend du même domaine + tests cross-couche + docs.**

Ne jamais finir tout le backend avant de commencer le frontend.

### Dépendances

```
Auth → tout le reste (obligatoire en premier)
Catalog → Inventory (stock lié aux produits)
Catalog → Orders (lignes de commande)
Inventory → Orders (réservation stock)
Orders → Payments (une commande est payée)
Orders → Delivery (une commande est livrée)
Customers → Orders (optionnel en Phase 1)
Catalog + Orders + Payments + Inventory → Reports (agrégation cross-module)
```

---

## Roadmap détaillée

---

### Phase 1 — MVP Backend + Frontend admin
**Objectif : application web complète et fonctionnelle**  
**Durée estimée totale : mois 3 → mois 7**

---

#### Sprint 1 — Foundation Frontend + Orders backend ✅
**Statut : LIVRÉ**

- Backend Orders : migrations, Order/OrderLine models, OrderService (create/confirm/fulfill/cancel), OrderController (6 endpoints), tests (8 unit + 12 intégration + 4 modular cross-module)
- Frontend foundation : Axios client, Pinia stores, router avec guards
- Frontend auth : LoginView, RegisterView, AppLayout, AuthLayout
- Frontend orders : OrderListView, OrderCreateView, OrderDetailView
- Design system Nexora ERP : CSS custom properties, composants utilitaires
- Pages publiques : LandingView (hero + features + FAQ), OnboardingView (wizard 5 étapes)
- Dashboard : DashboardView avec SVG icons, CSS tokens, actions rapides
- Settings : SettingsView (5 onglets)

---

#### Sprint 2 — Customers backend + Catalog UI ✅
**Statut : LIVRÉ**

**Backend — Customers**

| Livrable | Description |
|----------|-------------|
| Migration `customers` | name, phone, email, address JSON, notes, tenant_id |
| `Customer` model | |
| `CustomerService` | CRUD + search par nom/téléphone |
| `CustomerController` | CRUD + search + liste commandes |
| Lier Orders à Customers | `customer_id` FK sur `orders` |
| Tests | Unit + Integration |

**Frontend — Catalog UI**

| Livrable | Description |
|----------|-------------|
| `ProductListView.vue` | Table paginée + filtre statut/catégorie + recherche |
| `ProductFormView.vue` | Création/édition produit, prix, statut |
| `VariantPanel.vue` | Gestion variantes (ajout, suppression) |
| `CategoryListView.vue` | Arbre hiérarchique des catégories |
| `LabelPrint.vue` | Sélecteur format + copies → ouvre HTML dans onglet |
| `productService.ts` | Appels API Catalog |

---

#### Sprint 3 — Payments backend + Inventory UI ✅
**Statut : LIVRÉ**

**Backend — Payments**

| Livrable | Description |
|----------|-------------|
| Migration `payments` | order_id, amount, currency, method, reference, paid_at |
| `Payment` model | méthodes : cash, mobile_money (Orange/Wave/MTN), card, transfer |
| `PaymentService` | record() · balance() · isFullyPaid() |
| Intégration Orders | markPaid() quand fully paid |
| Tests | Unit + Integration |

**Frontend — Inventory UI**

| Livrable | Description |
|----------|-------------|
| `StockListView.vue` | Table produits + badges vert/orange/rouge |
| `StockAlertsView.vue` | Bandeau + page alertes stock bas |
| `MoveStockForm.vue` | Formulaires entrée/sortie/ajustement |
| `BarcodeScanner.vue` | Input texte (douchette USB → keydown) → résolution SKU |
| `MovementHistoryView.vue` | Timeline mouvements par produit |
| `inventoryService.ts` | Appels API Inventory |

---

#### Sprint 4 — Delivery backend + Payments UI + Delivery UI ✅
**Statut : LIVRÉ**

**Backend — Delivery**

| Livrable | Description |
|----------|-------------|
| Migration `deliveries` | order_id, status, address, carrier, notes, dispatched_at, delivered_at |
| `Delivery` model | statuts : pending → dispatched → delivered → failed |
| `DeliveryService` | dispatch(), confirmDelivery(), fail() |
| Tests | Unit + Integration |

**Frontend — Customers + Payments + Delivery UI**

| Livrable | Description |
|----------|-------------|
| `CustomerListView.vue` | CRUD + historique commandes |
| `CustomerDetailView.vue` | Fiche client, commandes liées |
| `PaymentListView.vue` | Paiements globaux, void |
| `DeliveryListView.vue` | dispatch/deliver/fail actions |

---

#### Sprint 5 — Suppliers + Import/Export module ✅
**Statut : LIVRÉ**

**Backend — Suppliers**

| Livrable | Description |
|----------|-------------|
| Migration `suppliers` | name, code, email, phone, contact, payment_terms, notes, status |
| `Supplier` model | code auto-généré, findOrCreateByName |
| `SupplierService` | CRUD + search + findOrCreateByName |
| `SupplierController` | 6 endpoints |
| Tests | 7 unit + 7 integration |

**Backend — Import/Export**

| Livrable | Description |
|----------|-------------|
| Migration `import_sessions` | status machine, column_mapping, stats |
| Migration `import_rows` | raw_data, mapped_data, errors, status, action |
| `ImportSession` / `ImportRow` models | Status constants + helpers |
| `ColumnMapper` | Auto-mapping FR/EN aliases par entité |
| `ProductImportParser`, `CustomerImportParser`, `SupplierImportParser` | Validation, doublons, mode (create/update/simulate) |
| `ImportService` | upload→analyze→mapping→approve→execute (sync ≤200 rows / async > 200) |
| `TemplateService` | Téléchargement Excel template stylisé par entité |
| `ExcelExporter` | Export Excel stylisé par entité |
| `PdfExporter` | Export PDF + rapport d'import (dompdf) |
| `AnalyzeImportJob`, `ExecuteImportJob` | Horizon jobs sur queue `imports` |
| `ImportExportController` | 10 endpoints |
| Tests | 5 mapper + 9 parser + 8 API + 8 module |

**Frontend — Suppliers + Import/Export**

| Livrable | Description |
|----------|-------------|
| `SupplierListView.vue` | Table paginée, modal CRUD inline |
| `ImportWizardView.vue` | Wizard 5 étapes, polling async (2s) |
| `ImportHistoryView.vue` | Historique filtrable, modal détail, actions rapides |
| `importExportService.ts` | 10 appels API |
| `supplierService.ts` | 6 appels API |

---

#### Sprint 7A — SaaS billing, module registry, admin back-office ✅
**Statut : LIVRÉ**

**Backend — Billing module**

| Livrable | Description |
|----------|-------------|
| `Plan` model + migration | starter (gratuit 14j), pro (15 000 XOF/mois), enterprise (custom) |
| `Subscription` model + migration | Lifecycle : trialing → active → suspended → cancelled → pending_approval |
| `SubscriptionService` | createStarter, current, changePlan, suspend, reactivate |
| `BillingServiceProvider` | Auto-charge migrations et routes du module Billing |

**Backend — Platform module**

| Livrable | Description |
|----------|-------------|
| `ErpModule` + migration | 10 modules ERP configurables en DB (code, status, is_core, route_prefix, color) |
| `TenantModule` + migration | Activation par tenant (active/inactive/suspended/trial) |
| `AuditLog` + migration | Log immuable (pas d'updated_at), toutes actions sensibles |
| `plan_modules` pivot | Quels modules sont inclus dans quel plan |
| `ModuleRegistryService` | listForTenant, activeCodes, tenantHasModule, activate, deactivate, activatePlanModules |
| `AuditService` | logFromRequest, logCreated/Updated/Deleted, logLogin/Logout, logModuleActivated, logPlanChanged |
| `RequireAdmin` middleware | Vérifie `is_super_admin`, retourne 403 sinon |
| `AdminDashboardController` | Stats globales (tenants, users, plans, abonnements par statut, logs récents) |
| `AdminTenantController` | Liste/détail/suspend/réactiver/changePlan tenants |
| `AdminModuleController` | Liste modules + stats activations, activer/désactiver pour un tenant |
| `AdminPlanController` | Liste plans, audit log paginé |
| `ModulesController` | `GET /api/me/modules` — retourne modules actifs du tenant courant |
| `PlatformServiceProvider` | Auto-charge migrations + routes Platform |

**Backend — Registration fix (P0)**

| Livrable | Description |
|----------|-------------|
| `RegisterRequest` | Accepte `company_name` au lieu de `tenant_id` |
| `AuthController::register()` | Transaction atomique : provision tenant → créer user → assignRole('admin') → createStarter subscription |
| `Tenant` model | Ajoute `subscription_status` dans `$fillable`, relation `users()` HasMany |

**Backend — Seeders**

| Livrable | Description |
|----------|-------------|
| `RolesAndPermissionsSeeder` | 5 rôles (super-admin/admin/manager/member/viewer), 65 permissions par module et action |
| `PlansSeeder` | starter, pro, enterprise avec prix et features |
| `ErpModulesSeeder` | 10 modules avec icônes SVG, couleurs, catégories |
| `PlanModulesSeeder` | Matrice plan ↔ modules inclus |

**Frontend — Admin back-office**

| Livrable | Description |
|----------|-------------|
| `AdminLayout.vue` | Dark sidebar (#0f172a), topbar sticky, nav 5 entrées, badge "Super Admin" |
| `AdminDashboardView.vue` | KPI grid, abonnements par statut, répartition plans, derniers tenants, activité récente |
| `TenantListView.vue` | Table paginée avec filtres search/status/plan, actions suspend/réactiver |
| `ModuleListView.vue` | Grid de cards modules, toggle visibility, sélecteur statut, compteur activations |
| `PlanListView.vue` | Cards plans avec prix, limites, features |
| `AuditLogView.vue` | Table paginée des actions sensibles |
| `adminService.ts` | API complète admin (dashboard, tenants CRUD, modules, plans, audit logs) |

**Frontend — Registration fix (P0)**

| Livrable | Description |
|----------|-------------|
| `authService.ts` | Ajout `register(payload: RegisterPayload)` |
| `auth/types.ts` | Ajout `RegisterPayload`, `ErpModule`, `ModulesResponse`, `subscription_status` dans `Tenant` |
| `RegisterView.vue` | Appelle vraiment l'API (était `TODO + fake delay`) |
| `auth store` | Ajout `setToken()` + `setUser()` pour flow registration |
| `router/guards.ts` | Vérifie `requiresSuperAdmin`, redirige non-admins |
| `/admin/*` routes | 5 routes admin avec `meta.requiresSuperAdmin: true` + layout `admin` |

**Tests (293/293)**

| Suite | Tests | Description |
|-------|-------|-------------|
| `SubscriptionServiceTest` | 6 | createStarter, trialing status, module activation, current, suspend, changePlan |
| `ModuleRegistryServiceTest` | 9 | listForTenant, activeCodes, tenantHasModule (core/active/trial), activate, deactivate (core protection), activatePlanModules |
| `RegistrationTest` | 5 | Full flow, company_name requis, email unique, password fort, login post-register |
| `AdminApiTest` | 9 | Access control (403/401), dashboard, list/show tenants, search, suspend, modules, plans, audit |

---

#### Sprint 6 — Reports + Dashboard réel ✅
**Statut : LIVRÉ**

**Backend — Reports**

| Livrable | Description |
|----------|-------------|
| `ReportService` | dashboard(), sales(period), stock() |
| `GET /api/reports/dashboard` | KPIs jour, chart 7j, commandes récentes, top 5 produits |
| `GET /api/reports/sales?period=7d\|30d\|90d\|1y` | CA par jour, top 10 produits, répartition par méthode |
| `GET /api/reports/stock` | Valeur stock, SKUs, ruptures, alertes, mouvements 30j |
| Tests | 9 unit + 13 integration |

**Frontend — Dashboard + Reports**

| Livrable | Description |
|----------|-------------|
| `DashboardView.vue` (refait) | KPIs réels, bar chart SVG 7j, commandes récentes, top produits |
| `SalesReportView.vue` | 4 KPIs, chart CA sélecteur période, top produits table, méthodes barres |
| `StockReportView.vue` | 4 KPIs, alertes stock avec barre progression, mouvements 30j |
| `reportService.ts` | 3 appels API + helpers formatMoney / shortDate |

---

### Phase 2 — Mobile POS (Flutter)
**Objectif : caisse offline-first sur tablette/téléphone**  
**Démarre : mois 5, parallèle au Sprint 4-5 frontend**

| Sprint | Livrable |
|--------|----------|
| F-1 | Projet Flutter, auth, Drift SQLite, SyncEngine base |
| F-2 | Écran caisse, panier, scan caméra, scan Bluetooth |
| F-3 | Paiement (cash, Mobile Money), ticket thermique |
| F-4 | Scan réception livraison, impression étiquettes WebView |
| F-5 | Mode hors ligne complet, tests Flutter |

---

### Phase 3 — Connecteurs + API publique
**Mois 7-12**

| Livrable | Description |
|----------|-------------|
| Shopify connector | Sync commandes, produits, stock |
| WooCommerce connector | Sync bidirectionnel |
| Mobile Money API | webhooks sortants |
| API publique v1 | Docs OpenAPI, rate limiting, webhooks |
| Multi-dépôt | Warehouses, transferts inter-dépôts |

---

## Tests — stratégie par niveau

| Niveau | Outil | Portée | Objectif couverture |
|--------|-------|--------|---------------------|
| Unit | PHPUnit 12 | Services isolés (mocks) | 80%+ services |
| Integration | PHPUnit 12 + SQLite | Routes HTTP + DB | Tous les endpoints |
| Modular | PHPUnit 12 | Flux cross-modules | Scénarios métier complets |
| E2E backend | — | Différé Phase 2 | — |
| Unit frontend | Vitest | Stores, composables | 70%+ |
| E2E frontend | Playwright | Flux critiques | Login, commande, paiement |
| Mobile | Flutter test | Widgets, unitaires | 70%+ |

**Tests cross-modules requis (backend) :**
- `Order::create()` → Stock réservé
- `Order::fulfill()` → StockMovement créé
- `Order::cancel()` → Réservation libérée
- `Payment::record()` plein → Order status = paid
- Delivery confirmée → Order status = fulfilled

---

## Résumé calendrier

```
Mois 1-3  ✅ Auth + Catalog + Inventory (backend complet)
Mois 4    ✅ Sprint 1: Orders backend + Frontend foundation + Auth UI + Design system
Mois 4    ✅ Sprint 2: Customers backend + Catalog UI + Customers UI
Mois 5    ✅ Sprint 3: Payments backend + Inventory UI (StockList, Alerts, Timeline)
Mois 5    ✅ Sprint 4: Delivery backend + Payments UI + Delivery UI
Mois 6    ✅ Sprint 5: Suppliers + Import/Export module complet
Mois 6    ✅ Sprint 6: Reports + Dashboard réel — 264 tests passent
Mois 6    ✅ Sprint 7A: SaaS billing + module registry + admin back-office — 293 tests passent
Mois 7    🎯 MVP livré — Beta terrain (3-5 boutiques pilotes)
Mois 7-12 🔮 Phase 2: Connecteurs + API publique + Mobile Money
```

---

## Critères MVP (Go/No-Go beta)

- [x] Authentification multitenant fonctionnelle
- [x] Catalogue produits complet avec étiquettes
- [x] Stock suivi en temps réel (entrée/sortie/inventaire)
- [x] Commandes créées et tracées jusqu'à la livraison
- [x] Paiements enregistrés (cash + 1 Mobile Money)
- [x] Dashboard avec CA et stock du jour
- [x] Frontend web utilisable sur desktop + tablette
- [ ] App POS offline basique (vente + scan) — Phase 2
- [x] 200+ tests backend passants (293 actifs)
- [ ] 50+ tests frontend passants — Vitest à compléter
