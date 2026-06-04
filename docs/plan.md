# Plan d'implémentation — Frynov ERP

> Document vivant — mis à jour à chaque fin de sprint.
> **Dernière révision : 2026-06-04 · Replanification pricing localisé + landing géographique**
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
| Tests backend | **550 / 550 ✅** (2 skipped, **0 incomplete**) — +33 Sync + 10 POS (Sprint 19) |
| Tests Vitest frontend | **142 / 142 ✅** (+46 composant, +17 util) — couverture 30.8 % |
| Branche | `feature/sprint-16-variants-batch-stock` — Sprint 17 + audit + Sprint 19 (Sync, POS) livrés |
| Dernière tag | `v0.7.0` (Sprint 7A) |
| Dernière PR | #2 `feature/sprint-13` → `main` |

> ⚠️ **Note critique** : avant l'audit, la config `phpunit.xml` ne matchait que certains
> suffixes → **121 tests (dont sécurité & multi-tenant) n'étaient jamais exécutés**.
> Le « 374 passing » historique était trompeur. Désormais 501 tous exécutés.

---

## Décision produit — Pricing localisé & accès modules (à valider avant implémentation finale)

### Décision stratégique

La stratégie cible est de **ne plus vendre l'accès module par module** sur les plans publics. Les modules métier principaux doivent rester visibles et utilisables, avec une sécurité par rôles/permissions, tandis que la monétisation porte sur les ressources critiques :

- utilisateurs inclus et utilisateurs additionnels ;
- produits / SKU ;
- commandes mensuelles ;
- clients ;
- boutiques / branches ;
- entrepôts ;
- imports mensuels ;
- API / webhooks ;
- marketplace / synchronisations ;
- stockage ;
- niveau de support et accompagnement.

Les modules ou fonctions sensibles peuvent rester limités par **rôle**, **permission**, **quota**, **capacité** ou **option contractuelle**, mais ils ne doivent pas disparaître sans explication commerciale. Si une action dépasse le plan, l'UX doit afficher un message d'upgrade ou de demande d'accès.

### Plans cibles

| Plan | Cible | Inclus | Limites principales | Positionnement |
|---|---|---|---|---|
| **Découverte** | Test, commerçant solo, petite boutique | 1 utilisateur | 100 produits, 50 commandes/mois, 100 clients, 1 boutique, 1 entrepôt | Acquisition gratuite / low-cost |
| **Essentiel** | Boutique active | 2 utilisateurs | 500 produits, 300 commandes/mois, 1 000 clients, 1 boutique, 1 entrepôt | Plan quotidien pour PME locale |
| **Croissance** | PME en expansion | 5 utilisateurs | 5 000 produits, 2 000 commandes/mois, 10 000 clients, 3 boutiques, 3 entrepôts | Automatisation + reporting avancé |
| **Business / Enterprise** | Grossistes, franchises, réseaux multi-sites | 10 utilisateurs ou contrat | Volumes élevés ou illimités selon contrat | API, SLA, onboarding, support dédié |

### Marchés et devises cibles

| Marché | Pays principaux | Devise affichée | Règle |
|---|---|---|---|
| UEMOA | SN, CI, ML, BF, BJ, TG, NE, GW | XOF | Ne jamais libeller seulement “CFA” en base : utiliser XOF. |
| CEMAC | CM, GA, CG, TD, CF, GQ | XAF | Ne jamais mélanger XAF avec XOF. |
| Nigeria | NG | NGN | À brancher après validation paiement local. |
| Ghana | GH | GHS | À brancher après validation paiement local. |
| Kenya | KE | KES | À brancher après validation paiement local. |
| Afrique du Sud | ZA | ZAR | À brancher après validation paiement local. |
| Europe | FR, BE, ES, DE, etc. | EUR | Prix Europe, pas de XOF/XAF par défaut. |
| Canada | CA | CAD | Prix CAD obligatoire par défaut pour visiteurs Canada. |
| USA / Global | US + fallback | USD | Fallback international. |

### Règles de localisation

- La géolocalisation IP sert uniquement à **pré-sélectionner** un marché ; elle ne doit jamais être une barrière de sécurité.
- L'utilisateur doit toujours pouvoir corriger la zone via un sélecteur manuel pays/devise.
- La devise contractuelle doit venir d'un **price book validé côté backend**, pas d'un prix hardcodé durablement dans le frontend.
- La landing peut afficher un fallback statique temporaire, mais la cible release est un endpoint public de pricing.

### Audit de cohérence de la PR pricing/géo précédente

| Décision | Statut | Action |
|---|---|---|
| `PlanPrice` + `PlanLimit` | ✅ À conserver en principe | Garder si les migrations/seeders sont validés et idempotents. |
| Tous les modules sur plans publics | ✅ À conserver comme stratégie produit | À sécuriser avec RBAC, quotas et messages d'upgrade. |
| `QuotaService` lisant `plan_limits` | ✅ À conserver | Tests Billing obligatoires à chaque modification. |
| Prix hardcodés dans Landing/Upgrade | 🟡 À corriger | Sprint P3 : exposer un endpoint public de pricing, puis P4/P5 consomment cet endpoint. |
| Refonte landing + backend pricing dans une seule PR | 🔴 À éviter | Découper en P0→P6. |
| Ajout de dépendance frontend pour corriger les scripts | 🟡 À documenter | Garder seulement si nécessaire au build/type-check CI. |
| Checkout local par devise | 🔵 À documenter avant code | Définir rails de paiement par marché avant implémentation. |

### Risques connus

- Duplication des prix entre seeders backend et frontend.
- Migration de tenants existants (`starter`, `pro`, `enterprise`) vers `essential`/`croissance` sans plan de compatibilité.
- Promotions et abonnements existants dépendant d'anciens `plan_code`.
- Confusion XOF/XAF si “CFA” est utilisé dans les données techniques.
- Landing affichant des prix non contractuels si elle ne consomme pas la source backend.
- Paiements locaux disponibles différemment selon pays et prestataire.


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
| Billing | ✅ / 🟡 Replanification | 10+ | Plan, Subscription, Promotion, ManualPayment, QuotaService. Refonte pricing localisé à découper en sprints P0→P6 avant stabilisation finale. |
| Platform | ✅ | 28 | ErpModule registry, AuditLog HMAC (actor_role + ip), Admin back-office, `/admin/audit-logs`, verify-chain |
| Security module | ✅ | 21 | RBAC sur 11 modules, CatalogSecurityTest, PaymentSecurityTest, MultiTenantIsolationTest, AuditTrailTest |
| Marketplace | ✅ | — | Facebook/WhatsApp/WooCommerce adapters, sync alerts |
| CountryRules | ✅ | 5 | Migration + Model + RegistrationRuleService + 30 pays seedés |
| Multi-sites | ⚠️ Fondation | — | Migrations `warehouse_id` orders/payments + `user_warehouses` pivot (pas encore de UI) |
| Sync | 🧪 Scaffold testé — **masqué (feature flag)** | 33 | Scaffold CRUD (HasTenant, `/api`, `tenant`, `role:manager\|admin`) + 33 tests. **Routes derrière `config('frynov.modules.sync')` = `FEATURE_SYNC` (off par défaut)** → invisible en prod, activé en test. Domaine métier : Phase 3 |

**Total tests backend : 550 (2 skipped, 0 incomplete)**

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
| Billing | ✅ NEW / 🟡 À réaligner | — | BillingView (plan + usages) + UpgradeView. Pricing localisé à brancher sur une source backend unique avant release. |
| Marketplace | ✅ | — | Listings + alertes |
| Onboarding | ✅ Complet | — | Wizard 6 étapes (activité, équipe, besoins, entreprise+devise, provisioning). **Les 5 `needs_*` présents**, `provision()` persiste settings/devise + `onboarded=true`. Guard redirige tout tenant non-onboardé vers `/onboarding` ; register → onboarding |
| Profil | ✅ | — | Page profil + sessions |
| Admin back-office | ✅ | 8 vues | Tenants, Modules, Plans, Promotions, Paiements manuels, Audit (AdminLayout) |

**Tests Vitest frontend : 142 tests** (46 composant + 11 money + 6 date + TabNavs/services) — couverture 30.8 %

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
| `PlansSeeder` | Plans commerciaux + quotas + prix localisés (à stabiliser via P0→P3) | ✅ updateOrCreate attendu |
| `ErpModulesSeeder` | 10 modules ERP avec icônes | ✅ firstOrCreate |
| `PlanModulesSeeder` | Matrice plan ↔ modules ; stratégie cible : modules métier visibles, limites par ressources | ✅ idempotent attendu |
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
- ✅ Tests composant vues secondaires (VariantsView, SupplierDetail, CustomerDetail) — couverture 16.7 → 20.3 %, ratchet 20/58/31
- ✅ Tests composant vues liste/réglages (ProductListView ×4, StockListView ×3, SettingsView ×3) — **cible 25 % dépassée : 20.3 → 28.8 %** lignes · ratchet 28/57/31. Branches 58→57 : les gros fichiers de vue entrent dans le périmètre couvert avec beaucoup de branches non testées (le plancher suit la réalité).
- ✅ Tests composant `PaymentListView` (×4 : montant ÷100, annulation via service, lien commande, état vide) — couverture **28.8 → 29.8 %**, ratchet 29/57/32. **137 tests verts.**

**Backend**
- ✅ Câblé le test incomplet `TenantProvisioningServiceTest` (le seul `incomplete` restant) →
  déplacé en intégration avec 3 cas de bord réels : slug accentué (`Café Délice`→`cafe-delice`),
  fallback `tenant` pour noms sans caractère sluggable, déduplication multi-collision
  (`boutique` → `-1` → `-2`, régression du fix LIKE→exact-match). **0 incomplete.**
- ✅ **Diagnostic systémique** : sous **PHPUnit 12.5**, l'annotation docblock `/** @test */`
  n'est **plus collectée** (retirée). `TenantProvisioningTest` (4 tests réels) tournait à vide →
  converti en attribut `#[Test]` (convention projet), **7 tests ressuscités**. Le générateur
  `MakeModule.php` émet déjà `#[Test]` (cause racine déjà corrigée) ; **10 stubs générés morts**
  (pluriels `Sync*`/`Customers*`/`Payments*` + `CatalogModuleTest`) purgés — ils collectaient 0 test
  et dupliquaient les vraies suites `*ApiTest`/`*SecurityTest` singulières.
- ✅ **Gap Sync résolu** : le module **Sync** a désormais **33 tests réels** (`#[Test]`) — Unit
  (`SyncService` : CRUD, pagination, isolation tenant via `404`), Integration (`/api/syncs` : auth `401`,
  RBAC `manager|admin` vs `viewer` `403`, isolation multitenant `404`/listing scoped), Modular (binding
  `SyncRepositoryInterface`, enregistrement des routes, `TenantScope`). Le scaffold a été aligné sur le
  standard projet : `Sync` utilise `HasTenant` (+ `tenant_id` fillable), routes sous préfixe `/api` avec
  middleware `tenant` (qui pose le team context Spatie indispensable à `role:`). Sans ce câblage,
  `store`/`update`/`destroy` renvoyaient `500`/`403`.

---

### Sprint 19 — POS Web MVP + Agents terrain (Phase 2) — ✅ POS livré

**Backend — ✅ Module POS** (`app/Modules/Pos/`, 10 tests d'intégration)
- ✅ `CashRegisterSession` (model + migration) — `HasTenant`, statuts open/closed,
  fond de caisse, cumuls ventes/espèces, attendu/compté/écart en centimes.
- ✅ `PosService` orchestrant `OrderService` + `PaymentService` : `checkout` atomique
  (create → confirm → fulfill → record), rollback complet si stock insuffisant.
- ✅ Endpoints `/api/pos/sessions` (open/current/checkout/close) — rôles `admin|manager|cashier`.
- ✅ Migration : `cash_register_session_id` (nullable) ajouté à `orders`.
- ✅ Rôle `cashier` + permissions `pos.*` (déjà dans `RolesAndPermissionsSeeder`).

**Frontend — ✅ Caisse** (`frontend/src/modules/pos/`, 5 tests)
- ✅ `PosView.vue` : ouverture de session, recherche/scan produit, panier (déclinaisons),
  encaissement, clôture avec rapprochement d'écart. Convention ÷100 / ×100 respectée.
- ✅ Service `posService`, route `/pos`, entrée menu **Caisse**.

**Docs** : [`docs/modules/pos.md`](modules/pos.md) (technique) + [`docs/user/pos.md`](user/pos.md) (utilisateur).

**Reste Sprint 19** : agents terrain (Phase 2), interface tablette dédiée (hors layout standard).

---

### Audit pré-release — durcissement (2026-06-04) — ✅

Issu de l'audit pré-release global (verdict GO conditionnel). Actions livrées :
- ✅ **Sync masqué** derrière feature flag `FEATURE_SYNC` (off par défaut) — API Phase 3 invisible en prod, 33 tests conservés (cf. `config/frynov.php`).
- ✅ **Onboarding vérifié complet** (les 5 `needs_*` + provisioning + guard de redirection) — l'évaluation « partiel » était obsolète.
- ✅ **Bug landing page corrigé** : le verrou de scroll global (`html/body/#app { overflow:hidden }`) clippait la page d'accueil publique. Verrou désormais scopé à `body.shell-locked` (appliqué seulement aux shells app/admin via `App.vue`). Vérifié : la landing scrolle (scrollHeight 7951px).
- ✅ **Purge code mort** : 32 fichiers stubs générés (pluriels `Payments*`/`Customers*` + `Catalog` model/repo/events/requests), dont 2 migrations parasites créant les tables `paymentss`/`customerss`. `CatalogService`/`CatalogResource` (vrais) conservés.

Reste recommandé (non bloquant) : smoke tests frontend admin back-office.

---

### Roadmap corrigée — Pricing localisé & landing géographique

> Règle : **ne plus livrer backend pricing + landing + upgrade + checkout dans une seule PR**. Chaque sprint ci-dessous doit être mergé séparément, avec tests ciblés et mise à jour documentaire.

#### Sprint P0 — Documentation & décision produit

**Objectif** : valider la stratégie pricing avant nouveau code applicatif.

- Docs : mettre à jour `docs/plan.md`, `docs/user/billing.md`, `docs/user/navigation.md`.
- Produit : valider noms de plans, ressources limitées, marchés, devises, règles XOF/XAF.
- DoD : tableau des plans validé, risques listés, ordre des PRs accepté.
- Tests : contrôle markdown + relecture fichiers docs.

#### Sprint P1 — Backend pricing foundation

**Objectif** : stabiliser la source backend des prix et limites.

- Backend : valider ou corriger `plan_prices`, `plan_limits`, relations `Plan`, seeders idempotents.
- Compatibilité : conserver anciens codes (`starter`, `pro`, `enterprise`) tant qu'une migration commerciale n'est pas validée.
- Tests : `PlanLocalizationTest`, `QuotaServiceTest`, `SubscriptionServiceTest`, tests admin plans.
- DoD : migrations propres, seeders répétables, aucune dépendance frontend.

#### Sprint P2 — Stratégie d'accès modules

**Objectif** : rendre cohérent “modules visibles” + “sécurité réelle”.

- Backend : vérifier `PlanModulesSeeder`, RBAC, guards tenant, quotas.
- Frontend : préparer messages verrouillés/upgrade sans cacher arbitrairement.
- Tests : RBAC backend, tests `ModuleRegistryService`, tests navigation si modifiée.
- DoD : tous les modules publics sont inclus si stratégie validée, mais actions critiques restent protégées.

#### Sprint P3 — API publique de pricing

**Objectif** : éviter les prix hardcodés dans la landing.

- Backend : créer `GET /api/public/pricing?market=waemu` ou `?country=SN`.
- Réponse : plans, devise, prix mensuel/annuel, utilisateurs inclus, prix utilisateur additionnel, limites, méthodes de paiement disponibles.
- Tests : API publique pricing + fallback market.
- DoD : le backend devient la source de vérité publique des prix.

#### Sprint P4 — Landing géographique

**Objectif** : adapter la landing à la zone sans dupliquer la source pricing.

- Frontend : `useGeoContent` détecte pays/marché, sélecteur manuel, fallback global.
- Frontend : Landing consomme l'API pricing ; contenu hero/FAQ/moyens de paiement par marché.
- Tests : Vitest landing + geo, scénario CA→CAD, FR→EUR, SN→XOF, CM→XAF.
- DoD : aucun XOF par défaut pour Canada/France ; correction manuelle possible.

#### Sprint P5 — Upgrade/Billing localisé

**Objectif** : aligner `/billing/upgrade` avec la source backend.

- Frontend : consommer l'API pricing ou endpoint authentifié équivalent.
- Backend : vérifier cohérence abonnement courant, promo codes, devises.
- Tests : Billing frontend + Billing API.
- DoD : prix affiché = prix backend, pas un prix hardcodé.

#### Sprint P6 — Paiements locaux & checkout

**Objectif** : brancher les rails de paiement selon marché.

- Afrique : paiement manuel/Mobile Money selon pays et prestataire.
- Europe/Canada/USA : carte, facture, virement selon disponibilité.
- Admin : audit paiement, validation/rejet, notifications.
- Tests : manual payments, promo, audit log, checkout devise.
- DoD : chaque devise affichée correspond à un flux paiement ou à une mention “sur devis / paiement manuel”.

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
