# Plan d'implémentation — ETech ERP Africa

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
| **Orders** | 🔨 En cours | — | Prochain |
| **Customers** | 🔨 En cours | — | Prochain |
| Payments | ⏳ Planifié | — | |
| Delivery | ⏳ Planifié | — | |
| Sync | 💤 Différé | — | Phase 2 |

**Backend MVP : 40% complet. Tests : 95 passent.**

---

### Frontend (Vue 3 + Vite + TypeScript + PrimeVue)

| Couche | Statut | Remarques |
|--------|--------|-----------|
| Stack / config | ✅ Configuré | package.json, vite, tsconfig, vitest |
| Foundation (API client, router, stores) | 🔨 En cours | |
| Auth UI (login, layout) | 🔨 En cours | |
| Catalog UI | ⏳ Planifié | Après foundation |
| Inventory UI | ⏳ Planifié | |
| Orders UI | ⏳ Planifié | |
| Reports | ⏳ Planifié | |

**Frontend MVP : 5% complet (stack configurée, aucun code applicatif).**

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
| Orders, Customers, Payments, Delivery | ⏳ À écrire avec les modules |

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
**Objectif : application web complète et fonctionnelle pour gérer une boutique**  
**Durée estimée totale : mois 3 → mois 7**

---

#### Sprint 1 — Foundation Frontend + Orders backend
**Durée : 1 semaine**

**Backend — Orders**

| Livrable | Description |
|----------|-------------|
| Migration `orders` | tenant, customer_id?, reference, status, total, currency, note |
| Migration `order_lines` | order, product, variant?, sku snapshot, qty, unit_price, total |
| `Order` model | statuts : pending → paid → fulfilled → cancelled |
| `OrderLine` model | snapshot produit au moment de la commande |
| `OrderService` | create (réserve stock) · fulfill (moveOut) · cancel (release) |
| `OrderController` | CRUD + confirm + cancel + fulfill |
| Tests unit | OrderService : create, fulfill, cancel, oversell check |
| Tests intégration | OrderApiTest : CRUD HTTP complet |
| Tests modular | Order↔Stock : création réserve, annulation libère |

**Frontend — Foundation**

| Livrable | Description |
|----------|-------------|
| `src/api/client.ts` | Axios instance, interceptors (Bearer token, X-Tenant-Slug, 401 redirect) |
| `src/stores/auth.ts` | Pinia : login, logout, user, token (localStorage) |
| `src/stores/tenant.ts` | Tenant courant, slug |
| `src/router/index.ts` | Routes par module, lazy loading |
| `src/router/guards.ts` | Auth guard, tenant guard |
| `src/App.vue` | Root component |
| `src/main.ts` | Bootstrap Vue + Pinia + Router + PrimeVue |
| `src/layouts/AppLayout.vue` | Sidebar nav + topbar + slot content |
| `src/layouts/AuthLayout.vue` | Centré, card login |
| `src/modules/auth/views/LoginView.vue` | Form email/password + gestion erreurs |
| Tests Vitest | authStore : login/logout/token persistance |

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
| `ProductList.vue` | Table paginée + filtre statut/catégorie + recherche |
| `ProductForm.vue` | Création/édition produit, prix, statut |
| `VariantPanel.vue` | Gestion variantes (ajout, suppression) |
| `CategoryTree.vue` | Arbre hiérarchique des catégories |
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
| `Payment` model | méthodes : cash, orange_money, wave, mtn_money, card, transfer |
| `PaymentService` | record() · balance() · isFullyPaid() |
| Intégration Orders | markPaid() quand fully paid |
| Tests | Unit + Integration |

**Frontend — Inventory UI**

| Livrable | Description |
|----------|-------------|
| `StockList.vue` | Table produits + badges vert/orange/rouge |
| `StockAlerts.vue` | Bandeau + page alertes stock bas |
| `MoveStockForm.vue` | Formulaires entrée/sortie/ajustement |
| `BarcodeScanner.vue` | Input texte (douchette USB → keydown) → résolution SKU |
| `MovementHistory.vue` | Timeline mouvements par produit |
| `inventoryService.ts` | Appels API Inventory |
| Tests Vitest | inventoryStore, BarcodeScanner input |

---

#### Sprint 4 — Delivery backend + Orders UI
**Durée : 1 semaine**

**Backend — Delivery**

| Livrable | Description |
|----------|-------------|
| Migration `deliveries` | order_id, status, address, carrier?, notes, dispatched_at, delivered_at |
| `Delivery` model | statuts : pending → dispatched → delivered → failed |
| `DeliveryService` | dispatch(), confirmDelivery(), fail() |
| Tests | Unit + Integration |

**Frontend — Orders + Customers UI**

| Livrable | Description |
|----------|-------------|
| `OrderList.vue` | Filtres statut/date/client |
| `OrderCreate.vue` | Sélection client, ajout produits (search/scan), prix |
| `OrderDetail.vue` | Timeline statut, lignes, total, actions |
| `PaymentRecord.vue` | Form enregistrement paiement (méthode + montant) |
| `CustomerList.vue` | CRUD + historique commandes |
| `CustomerForm.vue` | Fiche client |
| Tests Vitest | orderStore, orderService mock |

---

#### Sprint 5 — Dashboard + Reports + Polish
**Durée : 1 semaine**

**Backend**

| Livrable | Description |
|----------|-------------|
| `ReportService` | CA par période, top produits, valeur stock, marges |
| Endpoints reports | GET /reports/dashboard · /sales · /stock · /payments |

**Frontend**

| Livrable | Description |
|----------|-------------|
| `Dashboard.vue` | KPIs jour : CA, nb commandes, stock bas |
| `SalesReport.vue` | CA par période + chart, top produits |
| `StockReport.vue` | Valeur stock, rotation, ruptures |
| Internationalisation | i18n FR (structure posée, anglais possible plus tard) |
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
| Mobile Money API | Orange Money, Wave, MTN (webhooks) |
| API publique v1 | Docs OpenAPI, rate limiting, webhooks sortants |
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
Mois 4    🔨 Sprint 1: Orders backend + Frontend foundation + Auth UI
Mois 4    🔨 Sprint 2: Customers backend + Catalog UI
Mois 5    ⏳ Sprint 3: Payments backend + Inventory UI + début Flutter
Mois 5    ⏳ Sprint 4: Delivery backend + Orders UI
Mois 6    ⏳ Sprint 5: Reports + Dashboard + Polish + Flutter POS
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
