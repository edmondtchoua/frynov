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

## Gate sécurité audit — ✅ REMÉDIÉ (2026-06-07)

> Ajout 2026-06-06 : l’audit sécurité traduit en tests d’acceptation exécutables.
> **2026-06-07 : toutes les exigences sont implémentées et les gates passent** (branche
> `feature/security-audit-remediation`). Voir `docs/security/security-remediation-tests.md`
> et `docs/modules/rbac.md`.

Suites de validation (toutes vertes) :

- Backend : `php artisan test --filter=SecurityRemediationTest` ✅ · `--filter=ModuleGatingTest` ✅
- Frontend : `npm run test:unit -- src/security/__tests__/frontendSecurity.spec.ts src/stores/__tests__/auth.spec.ts` ✅
- `composer audit --locked` ✅ · `npm audit --omit=dev` ✅ (0 vulnérabilité)

Exigences bloquantes — état :

- ✅ modules métier **fail-closed** côté serveur (`module:` sur les 9 modules métier) ;
- ✅ permissions métier appliquées côté serveur (créations gated, `viewer` bloqué) ;
- ✅ aucune relation cross-tenant via IDs front (parent catégorie tenant-scopé) ;
- ✅ preuves de paiement **privées** (disque local + URL signée courte) ;
- ✅ audit trail **vérifiable** (hash canonique partagé création/vérification) ;
- ✅ token Bearer **en mémoire** (plus de `localStorage`/`sessionStorage`) ;
- ✅ plus de `v-html` sur SVG/HTML de données modules (`ModuleIcon`) ;
- ✅ hiérarchie des rôles (`manager` ne peut pas créer/élever un `manager`).

## Gate UX/UI audit — expérience produit et accessibilité

> Ajout 2026-06-06 : un audit UX/UI approfondi est disponible dans `docs/ux-ui/audit-ux-ui-approfondi.md`. Les refontes front doivent désormais traiter les priorités P0/P1 avant toute validation production.
> **2026-06-07 — P0 entamé** (branche `feature/ux-ui-audit-p0`) : UX-01 + UX-04 livrés.

Axes bloquants :

- ✅ **UX-01 (P0)** navigation alignée modules actifs + permissions — sidebar pilotée
  par `active_modules` + rôle ; modules inactifs **visibles mais verrouillés** (lien
  upgrade), entrées hors-rôle masquées (`AppLayout.spec.ts`) ;
- ✅ **UX-04 (P0)** accessibilité clavier/ARIA — sidebar + global (`.sr-only`,
  `:focus-visible`, `aria-expanded/controls/label`, landmark nav) ; tabs (`<nav aria-label>`
  + `aria-current` — pattern correct pour des onglets-liens, pas `role=tab`) ; modales
  (directive globale **`v-focus-trap`** : piège Tab + restauration du focus + Échap,
  appliquée aux modales Paramètres/Rôles) ; toggles (`role=switch`). Suivi rapide :
  généraliser `v-focus-trap` aux modales admin/modules restantes (1 attribut chacune) ;
- 🟡 (P1, **UX-03**) design system partagé — **fondation livrée** (`shared/ui/` :
  `BaseButton`, `BaseCard`, `BaseModal`, `StateBlock`, `FormField` ; `docs/modules/ux-design-system.md`).
  **Adoption** : listes Produits, Clients, Stock, Paiements, **Commandes**, **Livraisons**
  migrées (`StateBlock` + `.table-scroll`). Reste : Fournisseurs/Retours (états dans le
  tableau), import, et les vues admin ;
- ✅ (P1, **UX-05**) états loading/empty/error/forbidden standardisés — `StateBlock` +
  page `/unavailable` (module désactivé / permission / quota) ;
- 🟡 (P1, **UX-06**) responsive — utilitaire `.table-scroll` + appliqué aux listes migrées ;
  reste : généraliser aux autres listes (idéalement cartes empilées sur mobile) ;
- 🟡 (P1, **UX-07**) formulaires critiques — **utilitaires livrés** (`FormField` erreurs liées
  `aria-describedby` ; `useUnsavedChanges` anti-perte de données). Reste : câbler dans
  ProductForm / création commande / onboarding ;
- ✅ (P1, **UX-09**) pricing/upgrade basé sur prix backend — modale d'upgrade (Paramètres)
  alimentée par `publicPricingService` (plans + prix confirmés, montant pré-rempli) ;
- 🔲 (P1, **UX-02**) cohérence admin/tenant · 🔲 (P1, **UX-08**) onboarding orienté résultat.

## Gate catalogue produits spéciaux — services, digital, garanties, IMEI/VIN

- Ne pas annoncer le catalogue comme compatible téléphones sérialisés, véhicules/VIN, produits digitaux ou garanties tant que les tests d'acceptation du document `docs/modules/catalog-produits-speciaux-audit.md` ne passent pas.
- Priorité P0 : introduire une politique serveur de stock/livraison (`stock_tracking`, `fulfillment_type`) et corriger le flux commande pour que les services n'utilisent jamais de faux stock.
- Priorité P1 : ajouter des caractéristiques spéciales dynamiques (ex. IMEI, VIN, numéro moteur, licence) avec flags `is_unique`, `is_filterable`, `is_searchable`, puis des unités sérialisées avec unicité tenant, allocation commande, isolation multitenant et garanties.
- Priorité P2 : ajouter les produits digitaux avec assets privés, licences/entitlements et contrôles d'accès serveur.
- Productivité quotidienne : ajouter une duplication produit/catégorie sûre avec wizard de complétion, en copiant les champs non uniques et en vidant/régénérant SKU, codes-barres, GTIN, valeurs uniques, stock, garanties émises et licences individuelles. Audit préalable : `docs/modules/catalog-duplication-audit.md`.
- L'agent de code doit traiter le document d'audit catalogue comme cahier d'acceptation fonctionnel et sécurité avant toute refactorisation large.

### Audit consolidé P1 — duplication assistée sécurisée catalogue

> Audit source : `docs/modules/catalog-duplication-audit.md`. Verdict : **duplication produit/catégorie absente**, **refonte partielle ciblée recommandée**, **validation produit requise avant implémentation**. Ne pas lancer de refonte complète sans arbitrage explicite.

État actuel consolidé :

- ✅ Création produit existante via `POST /api/catalog/products`, avec génération SKU/code-barres interne et validation GTIN côté serveur.
- ✅ Édition/affichage produit existants via `ProductFormView` et `ProductShowPage`.
- ✅ Création/édition catégorie existantes via `CategoryController` et génération automatique de slug.
- ⚠️ Le formulaire produit est réutilisable partiellement, mais trop dense pour y intégrer tout le wizard sans composant dédié.
- ❌ Aucun endpoint, service, action UI, wizard ou test de duplication produit/catégorie n'existe aujourd'hui.

Risques bloquants à couvrir côté serveur :

- duplication de `sku`, `barcode`, `internal_barcode`, `gtin`, SKU/code-barres variantes ;
- duplication de stock réel, quantités réservées, lots, batch numbers, séries, IMEI/VIN, licences individuelles ;
- duplication de garanties émises, mouvements d'inventaire, commandes/factures, logs, audit trail ou relations transactionnelles ;
- contournement par payload frontend manipulé ;
- création partielle produit/variantes sans transaction ;
- relations cross-tenant sur produit source, catégorie, fournisseur, variantes, attributs ou médias.

Architecture validable avant code :

1. `ProductDuplicationService` dédié avec allowlist de champs copiables et blocklist stricte de champs interdits.
2. `CategoryDuplicationService` ou extension contrôlée de `CatalogService` pour catégories.
3. Endpoints sous `auth:sanctum` + `EnsureUserBelongsToTenant` + `role:manager|admin` :
   - `GET /api/catalog/products/{id}/duplicate/preview` ;
   - `POST /api/catalog/products/{id}/duplicate` ;
   - `GET /api/catalog/categories/{id}/duplicate/preview` ;
   - `POST /api/catalog/categories/{id}/duplicate`.
4. Preview serveur unique consommée par le wizard : champs copiés, à compléter, régénérés, exclus, warnings.
5. Création finale transactionnelle, avec rechargement source côté serveur, validation tenant/permissions, rejet/ignore des champs interdits et audit `product.duplicated`.
6. `ProductDuplicationWizard.vue` dédié : type de duplication, informations générales, variantes/attributs, exclusions stock/sérialisation/garantie/digital, champs à compléter, résumé.

Critères d'acceptation à transformer en tests avant merge :

- produit simple dupliqué sans copier SKU/code-barres/GTIN/stock ;
- produit à variantes dupliqué avec structure/attributs, sans SKU/code-barres/stock variantes ;
- produit sérialisé dupliqué sans unités, IMEI, VIN, serials, batches ni stock ;
- produit avec garantie dupliqué sans garanties émises ;
- produit digital dupliqué sans clés/licences/entitlements individuels ;
- catégorie dupliquée sans réutiliser slug/path/statistiques/historiques ;
- permissions manager/admin, rejet viewer/cashier ;
- rejet cross-tenant ;
- rollback transactionnel si une variante échoue ;
- wizard frontend : bouton visible selon permission, étape “Champs à compléter” obligatoire, résumé copié/vidé/régénéré/exclu.

Arbitrages à valider avant implémentation : libellé du bouton, convention de nom brouillon, génération automatique ou manuelle du SKU, règle `internal_barcode`, règle `barcode`/`gtin`, copie des médias, SKU variantes, catégories enfants, statut `draft` ou statut source, sévérité audit.

## État global

| Indicateur | Valeur |
|---|---|
| Tests backend | **638 ✅** (636 passed, 2 skipped, **0 incomplete**) — +RBAC A/B2/C + **remédiation audit sécurité (modules fail-closed, RBAC créations, hiérarchie rôles, isolation, preuves privées, chaîne d'audit)** |
| Tests Vitest frontend | **191 / 191 ✅** (+gates sécurité : token mémoire, `ModuleIcon` anti-`v-html`) — couverture ~38 % |
| Branche | `release/v1.0.0` — **RC `v1.0.0-rc.1`** (figée depuis `develop`) ; `develop` = intégration |
| Dernière tag | `v0.8.0` (publiée sur `main`) → **`v1.0.0-rc.1`** candidat prod (cf. [Go/No-Go v1.0.0](recette/go-no-go-v1.0.0.md)) |
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
| CountryRules | ✅ | 5+8 | Migration + Model + RegistrationRuleService + 30 pays seedés + **admin CRUD UI** (`/api/admin/country-rules`, Sprint 21) |
| Multi-sites | ✅ Quasi-complet | 10 | Filtres par site (listes + rapports) + **scoping d'accès par agence** (`user_warehouses`, isolation testée) ; reste : page Agences + scoping ressource-unique |
| Sync | 🧪 Scaffold testé — **masqué (feature flag)** | 33 | Scaffold CRUD (HasTenant, `/api`, `tenant`, `role:manager\|admin`) + 33 tests. **Routes derrière `config('frynov.modules.sync')` = `FEATURE_SYNC` (off par défaut)** → invisible en prod, activé en test. Domaine métier : Phase 3 |

**Total tests backend : 568 (2 skipped, 0 incomplete)**

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
| `DemoSeeder` | **3 tenants démo couvrant TOUS les modules MVP** : catalogue (simples + déclinaisons + attributs), stock + **mouvements** + **entrepôts**, clients, commandes (tous statuts) + paiements + livraisons, **retours/SAV**, **caisse POS** (ouverte + clôturée), **marketplace**, **promotions**, **paiement manuel**, **période fiscale**, **import**, **ajustement de stock**, **transfert inter-entrepôts** (multi-sites). Convention monétaire uniforme (centimes). Vérifié par `DemoSeederTest` (42 assertions). | ✅ updateOrCreate (idempotent, sauf n° de commande) |

---

> **Démo** : `php artisan migrate:fresh --seed` → 3 tenants (Découverte/Essentiel/Croissance) testables sur **toutes** les fonctionnalités MVP. Logins : `admin@afrikstyle.sn` · `admin@techzone.ci` · `admin@grossiste.cm` (mdp `Secret123!`). `DemoSeederTest` garantit la couverture à chaque évolution.

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
- ✅ **Smoke tests admin back-office** : 8 vues couvertes (`AdminViews.spec.ts`). Couverture frontend 30.8 → 38.6 %, ratchet 38/57/33.
- ✅ **Fix pricing sièges (audit approfondi)** : `PlansSeeder` posait `max_users = included_users` → impasse (Business plafonné à 10 utilisateurs avec message « mettez à niveau » sans plan supérieur ; sièges en sus affichés mais jamais facturés). Désormais **sièges = guide souple** sur les plans payants (`max_users`/`max_agents` = `null` = illimité), **seul le palier gratuit Découverte garde un cap dur (1)**. `included_users` conservé sur `plan_prices` pour l'affichage / futur overage facturé. Régression verrouillée par `PlanLocalizationTest::paid_plans_do_not_hard_cap_seats_only_the_free_tier_does`.
  > ⚠️ Déploiement : re-seeder requis sur les environnements existants (`php artisan db:seed --class=Database\\Seeders\\PlansSeeder`, idempotent `updateOrCreate`).

- ✅ **Fix géolocalisation RGPD (audit approfondi)** : la landing appelait `https://ipapi.co/json/` côté navigateur → l'IP de chaque visiteur partait chez un tiers sans consentement. Remplacé par un endpoint **`GET /api/public/geo`** qui dérive le pays des **headers d'edge/CDN** (`CF-IPCountry`, `CloudFront-Viewer-Country`, …) — **l'IP ne quitte jamais notre infra, aucun appel tiers**. À défaut d'edge, `useGeoContent` se rabat sur la **locale navigateur** (`navigator.language`). Tests : 3 backend (`PublicPricingApiTest`) + 2 frontend (`useGeoContent.spec.ts`, dont « never a third party »).

Reste recommandé (non bloquant) : aucun — les deux findings de l'audit approfondi sont traités.

---

### Réconciliation `develop` + tests approfondis (2026-06-05) — ✅

- ✅ **Branches réconciliées dans `develop`** : `feature/pre-release-hardening` (durcissement) ⊆ `feature/p1-admin-plan-limits` (pricing localisé + `plan_limits` éditables + sièges + geo). Merge `--no-ff` + récupération des smoke tests admin (perdus sur une branche divergente). `develop` = surensemble complet.
- ✅ **Audit `AdminPlanController` (P1 `plan_limits` éditables)** : sain — édition **super-admin uniquement** (`RequireAdmin`), validation explicite (pas de mass-assign), source canonique `plan_limits` + miroir legacy, audité.
- ✅ **Tests sécurité approfondis ajoutés** :
  - `regular_user_cannot_edit_plan_limits` — un utilisateur tenant ne peut **pas** relever ses propres quotas (403, limite inchangée).
  - `inviting_beyond_the_user_cap_is_blocked_with_402` — palier gratuit bloque la 2ᵉ invitation **de bout en bout** (HTTP → `EnforceQuota` → 402 `quota_exceeded`).
  - `paid_plan_with_unlimited_seats_allows_inviting_beyond_included` — plan payant (`max_users=null`) laisse inviter au-delà des sièges inclus (201) → fix sièges vérifié end-to-end.
- **État `develop`** : backend **568 verts** (0 fatal, 0 incomplete) · frontend **154 verts** (couverture 38.7 %) · `vue-tsc` propre.

---

### Recette v0.8.0 — correctifs de pré-finalisation (2026-06-06) — ✅

Recette d'acceptation sur `release/v0.8.0` (cf. [`docs/recette/recette-v0.8.0.md`](recette/recette-v0.8.0.md)). Anomalies relevées en recette, corrigées à la racine :

- ✅ **Login : rôles team-scoped + abonnement manquants** — l'endpoint public de login renvoyait des rôles vides (team context Spatie non posé) → onglets catalogue réduits + « aucun abonnement ». Corrigé : `login()` pose `setPermissionsTeamId`, le frontend rafraîchit via `/me` après login **et** après onboarding.
- ✅ **Modales invisibles (Stock/Alertes/Ventes/Paiements/Livraisons)** — le CSS `.modal-backdrop/.modal-box/…` n'existait nulle part → modales rendues hors écran (« les boutons Entrée/Sortie ne marchent pas »). CSS global ajouté à `main.css`.
- ✅ **Téléchargements import/export « Route [login] not defined »** — `window.open` perdait le token Bearer (401 → redirection vers une route `login` non nommée). Corrigé : downloads via axios `responseType:'blob'` (token attaché) + route web `login` nommée (401 propre).
- ✅ **Modèles d'import téléchargeables** depuis l'écran Import/Export (Produits/Clients/Fournisseurs).
- ✅ **Templates d'import — listes déroulantes tenant** : colonnes **Catégorie** + **Fournisseur** (valeurs du tenant) et **Statut** (enum) en déroulante Excel non bloquante (feuille masquée `Listes`, référencée par plage → nombre illimité). `TemplateService` tenant-aware, valeur hors liste créée à l'import.
- **État `release/v0.8.0`** : backend **573** (571 verts, 2 skipped, 0 fatal) · frontend **159 verts** · `vue-tsc` propre. **8 commits** en avance sur `develop` (à fusionner à la finalisation v0.8.0 → `main` + tag + back-merge `develop`).

---

## Audit profond modules — GO / NO-GO release (2026-06-04)

### Verdict global

**GO conditionnel** : le socle ERP est exploitable pour une release contrôlée si le scope reste limité aux modules métier déjà couverts et si la chaîne pricing localisé est sécurisée par une source backend unique. Le principal **NO-GO produit** restant avant mise en avant commerciale internationale était l'absence d'API publique de pricing : la landing/upgrade ne doivent pas contractualiser des prix hardcodés côté frontend.

### Synthèse par domaine

| Domaine | Verdict | Justification | Action prioritaire |
|---|---|---|---|
| Auth + Workspace + Onboarding | ✅ GO | Auth Sanctum, RBAC Spatie, isolation tenant, onboarding complet et guard de redirection déjà documentés/testés. | Conserver tests Auth/tenant à chaque sprint transverse. |
| Catalogue + Stock + Commandes | ✅ GO | Modules cœur couverts par tests unitaires/intégration, quotas produits/commandes déjà branchés, variantes et stock sécurisés. | Smoke test vente complète avant release. |
| Paiements + Livraisons + POS | ✅ GO conditionnel | Flux métier couverts ; POS livré. Les paiements réels restent dépendants des rails par marché. | Ne pas activer checkout local sans mapping devise → moyen de paiement. |
| Clients + Fournisseurs + Import/Export + Rapports | ✅ GO | CRUD, imports, exports et rapports présents ; RBAC sur mutations/rapports. | Garder limites d'import et export dans la matrice quotas. |
| Marketplace + Sync | 🟡 GO contrôlé | Marketplace visible ; Sync masqué par feature flag. | Garder Sync off en production tant que Phase 3 non validée. |
| Platform Admin + AuditLog | ✅ GO | Back-office, modules, plans, promotions, paiements manuels, audit HMAC. | Ajouter smoke tests admin UI si changement frontend. |
| Billing / Plans / Quotas | 🟡 GO conditionnel | `PlanPrice`, `PlanLimit`, quotas et seeders existent ; compatibilité anciens codes à surveiller. | Stabiliser source publique backend des prix, puis brancher landing/upgrade. |
| Landing géographique | 🔴 NO-GO commercial avant API | Risque d'afficher XOF/XAF à Canada/France si le frontend reste source de vérité. | Lancer Sprint P3 : API publique `/api/public/pricing`. |

### Points GO validés

- Les modules métier peuvent rester visibles sur les plans publics : la protection doit rester portée par RBAC, quotas et actions sensibles, pas par disparition silencieuse du menu.
- La stratégie XOF/XAF/EUR/CAD/USD est cohérente si la devise vient du marché résolu côté backend.
- Les anciens codes `starter`, `pro`, `enterprise` doivent rester supportés pendant la migration commerciale vers Découverte/Essentiel/Croissance/Business.

### Points NO-GO à ne pas contourner

- Pas de release commerciale internationale si Landing/Upgrade affichent des prix contractuels hardcodés.
- Pas de checkout local par devise sans disponibilité paiement documentée par marché.
- Pas de suppression/cachage de modules sans message d'upgrade ou explication d'accès.
- Pas de commit pricing sans tests Billing ciblés et test de non-régression Canada → CAD / France → EUR / UEMOA → XOF / CEMAC → XAF.

### Étape lancée maintenant

**Sprint P3 — API publique de pricing** est l'étape la plus nécessaire : P0 est documenté, P1/P2 ont déjà une base de code testée, et P3 supprime le risque critique de prix dupliqués dans le frontend avant la refonte de landing P4.

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

#### Sprint P3 — API publique de pricing — 🔄 lancé

**Objectif** : éviter les prix hardcodés dans la landing.

- ✅ Backend : créer `GET /api/public/pricing?market=waemu` ou `?country=SN`.
- ✅ Réponse : plans, devise, prix mensuel, utilisateurs inclus, prix utilisateur additionnel, limites, marchés sélectionnables.
- ✅ Tests : API publique pricing + fallback market + Canada/CAD + override manuel marché.
- 🟡 Reste : ajouter prix annuels et méthodes de paiement disponibles lorsque le checkout local P6 est cadré.
- DoD : le backend devient la source de vérité publique des prix.

#### Sprint P4 — Landing géographique — ✅ livré (2026-06-06)

**Objectif** : adapter la landing à la zone sans dupliquer la source pricing.

- ✅ Frontend : `useGeoContent` détecte pays/marché (via `/api/public/geo` + fallback locale), **sélecteur manuel** (`setMarketOverride`), fallback global.
- ✅ Frontend : **la landing consomme `/api/public/pricing`** (nouveau `services/publicPricingService.ts`) — les prix/devises/périodes viennent du **backend** (source de vérité), plus de prix contractuels en dur. `pricingAmounts` conservé **uniquement** comme repli hors-ligne si l'API est injoignable. Re-fetch au changement de marché dans le sélecteur. Montants en centimes (÷100) formatés selon la devise (XOF/XAF sans décimales).
- ✅ Tests : backend `PublicPricingApiTest::each_target_country_resolves_its_local_market_and_currency` (SN→XOF, CM→XAF, FR→EUR, CA→CAD au niveau source) ; frontend `publicPricingService.spec.ts` (3) + assertions de consommation API dans `LandingView.spec.ts`.
- ✅ DoD : **aucun XOF par défaut pour Canada/France** (la devise suit le pays résolu) ; **correction manuelle** possible via le sélecteur.
- 🟡 Reste (P5) : brancher `/billing/upgrade` (espace authentifié) sur la même source.

#### Sprint P5 — Upgrade/Billing localisé — ✅ livré (2026-06-06)

**Objectif** : aligner `/billing/upgrade` avec la source backend.

- ✅ Frontend : **`UpgradeView` consomme `/api/public/pricing`** (même `publicPricingService` que la landing) — les prix des plans viennent du backend, plus de table `localizedPrices` en dur (conservée seulement en repli hors-ligne). Re-fetch au changement de devise dans le sélecteur ; `base_amount_minor` (centimes) ÷100 selon la devise du marché.
- ✅ `BillingView` affiche déjà l'abonnement **courant** depuis `/api/me/subscription` (prix backend) — inchangé.
- ✅ Tests : `UpgradeView.spec.ts` (garde anti-réintroduction de prix en dur + sélecteur), au-dessus du `publicPricingService.spec` (P4) et de `PublicPricingApiTest` (backend).
- ✅ DoD : **prix affiché = prix backend**, pas un prix codé en dur.
- 🟡 Affinage possible : aligner la devise d'upgrade sur la **devise de facturation du tenant** (aujourd'hui marché géo, comme avant) plutôt que sur la géolocalisation — à traiter avec P6 (checkout local).

#### Sprint P6 — Paiements locaux & checkout

**Objectif** : brancher les rails de paiement selon marché.

- Afrique : paiement manuel/Mobile Money selon pays et prestataire.
- Europe/Canada/USA : carte, facture, virement selon disponibilité.
- Admin : audit paiement, validation/rejet, notifications.
- Tests : manual payments, promo, audit log, checkout devise.
- DoD : chaque devise affichée correspond à un flux paiement ou à une mention “sur devis / paiement manuel”.

---

### Sprint 20 — Multi-sites : filtres par site — 🔄 en cours (filtres livrés 2026-06-06)

**Livré — filtrage par entrepôt/site**
- ✅ Backend : `GET /api/orders?warehouse_id=` (`OrderService::paginate`) et `GET /api/payments?warehouse_id=` (`PaymentService::list`) filtrent par site ; le stock (`GET /api/inventory?warehouse_id=`) le faisait déjà.
- ✅ Frontend : sélecteur **« Tous les entrepôts »** sur **OrderListView**, **PaymentListView**, **SalesReportView** et **StockReportView** (StockListView l'avait déjà), via le composable partagé `useWarehouses` (fail-soft : liste vide si l'API échoue → « tous les sites »).
- ✅ **Rapports par site** : `GET /api/reports/sales?warehouse_id=` (CA, top produits, méthodes de paiement) et `/api/reports/stock?warehouse_id=` (valeur stock, ruptures, alertes, mouvements) scopés par entrepôt.
- ✅ Tests : `OrderServiceTest` / `PaymentServiceTest` / `ReportServiceTest` (filtres par entrepôt), `useWarehouses.spec.ts` + `reportService.spec.ts` (forward du param).
- ✅ **Scoping d'accès par agence** (`user_warehouses`) : un membre **non-manager** assigné à des entrepôts ne voit QUE leurs données (Commandes, Paiements, Stock, Rapports). Managers/admins jamais restreints ; aucun assignement = accès complet (rétrocompatible). `User::accessibleWarehouseIds()` + `WarehouseScope::resolve()` (centralisé, anti-fuite) appliqués aux **5 endpoints de liste**. Assignation : `PUT /api/workspace/users/{id}/warehouses` (manager/admin) + UI **Sites** dans Paramètres → Équipe.
- ✅ Tests scoping : `WarehouseScopeTest` (5, logique) + `WarehouseAccessScopingTest` (5, **isolation HTTP end-to-end** : opérateur restreint ne voit pas l'autre site, manager voit tout, site interdit → vide, non-manager → 403) + `authService.warehouses.spec`.

**Reste Sprint 20**
- Page Agences/Branches dédiée + métadonnées agence sur `Warehouse` (l'onglet **Entrepôts** CRUD existe déjà sous Stock ; l'assignation membre→sites est dans Paramètres → Équipe).
- ⚠️ **Zone d'ombre** (à acter au Go/No-Go) : le scoping couvre les **listes** ; les GET de ressource unique (`/orders/{id}`, `/payments/{id}`…) restent **tenant**-scopés mais pas **warehouse**-scopés (un opérateur connaissant un UUID d'une autre agence pourrait l'ouvrir). À durcir si le besoin se confirme.

---

### Sprint 21 — CountryRules UI + Onboarding — 🔄 (admin CountryRules livré 2026-06-06)

**Livré — admin CountryRules (super-admin)**
- ✅ Backend : `AdminCountryRuleController` CRUD complet sous `/api/admin/country-rules` (`auth:sanctum` + `RequireAdmin` → super-admin uniquement, audité). Code pays/devise normalisés en majuscules **avant** validation (unique insensible à la casse).
- ✅ Frontend : `CountryRuleListView` (table + modale créer/éditer/supprimer) + entrée menu **Règles pays** dans `AdminLayout` + route `/admin/country-rules`.
- ✅ Tests : `AdminCountryRuleTest` (8 : CRUD, code majuscule, doublon insensible à la casse, ISO invalide, **isolation user/guest → 403/401**) + smoke `AdminViews.spec`.

**Onboarding** — déjà vérifié **complet** à l'audit pré-release (les 5 `needs_*` + provisioning + guard de redirection). Reste : câbler `nb_branches` → création d'entrepôts secondaires (dépend du `Branch` de Sprint 20).

---

### Backlog — TVA / remises au niveau commande

> Gap fonctionnel relevé à l'audit : `OrderService::create` calcule
> `Σ(quantité × prix)` sans TVA ni remise. À spécifier si le besoin métier se confirme.

---

## Architecture v2 — Multi-clusters par pays & souveraineté des données

> Vision post-v1.0 : pour respecter la **souveraineté/résidence des données** (chaque pays
> impose que les données de ses entreprises restent sur son sol), passer d'un déploiement
> mono-cluster (v1, cf. [`docs/guides/deployment-vps.md`](guides/deployment-vps.md)) à
> **un cluster + une base de données par pays**.

### Cible

```
                    ┌────────────────────────────────────────────────┐
   Visiteur/SPA ──▶ │  Résolveur de région (edge/DNS + /api/public/geo)│
                    └───────────────┬───────────────┬────────────────┘
                          country=SN │      country=CM │   country=FR …
                    ┌───────────────▼──┐  ┌──────────▼──────┐  ┌────────▼────┐
                    │ Cluster UEMOA    │  │ Cluster CEMAC   │  │ Cluster EU  │
                    │ API + MySQL + Redis│ API + MySQL + Redis│ API + MySQL │  …un par zone/pays
                    │ (données SN/CI/…) │  │ (données CM/…)  │  │ (données FR)│
                    └──────────────────┘  └─────────────────┘  └─────────────┘
```

- **1 base par pays/zone** : les données tenant (catalogue, ventes, clients) **ne quittent pas**
  leur juridiction. Mapping **tenant → cluster** figé à l'inscription (selon le pays).
- **Plan de contrôle** (super-admin, facturation, plans, `CountryRule`) : soit central « fédéré »
  (agrège les clusters en lecture), soit répliqué par cluster — à arbitrer (les données de
  facturation peuvent être centralisées si la loi locale le permet, les données *opérationnelles* non).
- **Auth** : tokens Sanctum **émis par le cluster** du tenant (pas de SSO cross-cluster en v2.0 ;
  envisageable plus tard via un IdP central qui ne stocke pas les données métier).

### Ce que la **v1 frontend** prépare déjà (pour ne pas re-développer en v2)

- ✅ **SPA découplée** : artefact statique, l'URL d'API vient de `VITE_API_BASE_URL` (build-time) →
  le **même** code se déploie vers n'importe quel cluster pays sans modification.
- ✅ **Détection de marché/pays** déjà en place : `useGeoContent` + `GET /api/public/geo`
  (pays via headers edge/CDN, RGPD-safe) + sélecteur manuel → brique réutilisable pour le
  **résolveur de région** (country → base d'API du bon cluster).
- ✅ **Pricing/contenu par marché** déjà branchés sur le backend (`/api/public/pricing`) → cohérents
  par cluster.
- 🔲 **À ajouter en v2 (côté frontend)** : une étape de **résolution de cluster** (country → `apiBaseUrl`)
  avant login — soit via un petit endpoint « directory » public central (`/clusters?country=XX`),
  soit via DNS géo (`api.<pays>.<domaine>`). Tant que c'est non livré, garder `VITE_API_BASE_URL`
  mono-cluster ; le code n'a pas à changer, seule la **résolution** de l'URL devient dynamique.

### Chantiers backend v2 (hors v1.0)

- Connexions DB par pays (ou déploiements isolés) + provisioning tenant ciblant le bon cluster.
- Service « directory » des clusters (public : country → endpoint ; privé : santé, capacité).
- Back-office super-admin **fédéré** (vue multi-clusters) ou par-cluster.
- Réplication/agrégation **uniquement** des données autorisées hors-sol (facturation/usage), jamais des données opérationnelles tenant.

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
| ✅ RBAC rôles custom + permissions fines (Phase B2) | rc.4 livré — `docs/modules/rbac.md` |
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
