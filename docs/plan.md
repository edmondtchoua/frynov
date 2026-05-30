# Plan d'implémentation — Nexora ERP

> Document vivant — mis à jour à chaque session.  
> Dernière révision : **2026-05-30**  
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
| **Customers** | 🔨 Prochain | — | |
| Payments | ⏳ Planifié | — | |
| Delivery | ⏳ Planifié | — | |
| Sync | 💤 Différé | — | Phase 2 |

**Backend MVP : 55% complet. Tests : 117 passent.**

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
| **Dashboard** | ✅ Livré | KPI cards avec SVG icons, CSS tokens, actions rapides |
| **Settings** | ✅ Livré | 5 onglets (entreprise, équipe, abonnement, intégrations, notifs) |
| Orders UI | ✅ Livré | OrderListView, OrderCreateView, OrderDetailView |
| Catalog UI | 🔨 Prochain | Remplacer stubs ProductListView, etc. |
| Inventory UI | 🔨 Prochain | Remplacer stubs StockListView, etc. |
| Customers UI | ⏳ Planifié | Avec module Customers backend |
| Reports / Dashboard KPIs | ⏳ Planifié | Phase 2 — endpoints backend requis |

**Frontend MVP : 60% complet.**

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
| Customers, Payments, Delivery | ⏳ À écrire avec les modules |

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
│   │   └── DashboardView.vue    ← KPIs + actions rapides
│   ├── settings/views/
│   │   └── SettingsView.vue     ← 5 onglets (entreprise, équipe, facturation...)
│   ├── catalog/views/           ← Stubs (ProductList, ProductForm, CategoryList)
│   ├── inventory/views/         ← Stubs (StockList, StockAlerts, MovementHistory)
│   ├── orders/views/            ← OrderList, OrderCreate, OrderDetail
│   └── customers/views/         ← À créer avec module Customers
└── router/
    ├── index.ts                 ← Routes modulaires, lazy loading
    └── guards.ts                ← Auth guard, tenant guard
```

### Système de layouts (App.vue)

```
meta.layout = undefined  →  <RouterView />  (page gère son propre shell)
meta.layout = 'auth'     →  <AuthLayout>    (login, register)
meta.layout = 'app'      →  <AppLayout>     (toutes les vues authentifiées)
```

Pages sans layout (`meta.public: true` sans `meta.layout`) : landing, onboarding.

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

#### Sprint 2 — Customers backend + Catalog UI
**Durée : 1 semaine**

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
| Tests Vitest | productStore, productService mock |

---

#### Sprint 3 — Payments backend + Inventory UI
**Durée : 1 semaine**

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
| Tests Vitest | inventoryStore, BarcodeScanner input |

---

#### Sprint 4 — Delivery backend + Customers UI
**Durée : 1 semaine**

**Backend — Delivery**

| Livrable | Description |
|----------|-------------|
| Migration `deliveries` | order_id, status, address, carrier?, notes, dispatched_at, delivered_at |
| `Delivery` model | statuts : pending → dispatched → delivered → failed |
| `DeliveryService` | dispatch(), confirmDelivery(), fail() |
| Tests | Unit + Integration |

**Frontend — Customers UI**

| Livrable | Description |
|----------|-------------|
| `CustomerListView.vue` | CRUD + historique commandes |
| `CustomerDetailView.vue` | Fiche client, commandes liées |
| `PaymentRecord.vue` | Form enregistrement paiement (méthode + montant) |
| `customerService.ts` | Appels API Customers |
| Tests Vitest | customerStore, customerService mock |

---

#### Sprint 5 — Dashboard réel + Reports + Polish
**Durée : 1 semaine**

**Backend**

| Livrable | Description |
|----------|-------------|
| `ReportService` | CA par période, top produits, valeur stock, marges |
| Endpoints reports | GET /reports/dashboard · /sales · /stock · /payments |

**Frontend**

| Livrable | Description |
|----------|-------------|
| Dashboard KPIs réels | Connexion aux endpoints reports |
| `SalesReport.vue` | CA par période + chart, top produits |
| `StockReport.vue` | Valeur stock, rotation, ruptures |
| Internationalisation | i18n FR (structure posée) |
| Tests Playwright | Login → créer commande → payer → vérifier stock |

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
Mois 4    🔨 Sprint 2: Customers backend + Catalog UI
Mois 5    ⏳ Sprint 3: Payments backend + Inventory UI + début Flutter
Mois 5    ⏳ Sprint 4: Delivery backend + Customers UI
Mois 6    ⏳ Sprint 5: Reports + Dashboard réel + Polish + Flutter POS
Mois 7    🎯 MVP livré — Beta terrain (3-5 boutiques pilotes)
Mois 7-12 🔮 Phase 2: Connecteurs + API publique + Mobile Money
```

---

## Critères MVP (Go/No-Go beta)

- [ ] Authentification multitenant fonctionnelle
- [ ] Catalogue produits complet avec étiquettes
- [ ] Stock suivi en temps réel (entrée/sortie/inventaire)
- [ ] Commandes créées et tracées jusqu'à la livraison
- [ ] Paiements enregistrés (cash + 1 Mobile Money)
- [ ] Dashboard avec CA et stock du jour
- [ ] Frontend web utilisable sur desktop + tablette
- [ ] App POS offline basique (vente + scan)
- [ ] 200+ tests backend passants
- [ ] 50+ tests frontend passants
