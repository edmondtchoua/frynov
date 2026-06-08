# Changelog — Frynov ERP

Toutes les évolutions notables. Format inspiré de [Keep a Changelog](https://keepachangelog.com/),
versionnage [SemVer](https://semver.org/).

## [Non publié] — i18n : module Fournisseurs traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-suppliers` (release `v1.0.0` → `rc.24`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Fournisseurs entièrement internationalisé (UX-13)** — `SupplierListView` et
  `SupplierDetailView` : toutes les chaînes visibles passent par `$t` (template) / `t()` (script :
  `confirm`/`alert`, fallback d'erreur). Nouveau namespace `suppliers.*` + libellés génériques
  mutualisés sous `common.*` (name, email, phone, status, actions, notes, active/inactive,
  createdAt/updatedAt, saving, retry, genericError) pour réemploi par les prochains modules.
  Le sélecteur de langue bascule l'UI Fournisseurs en direct (FR ↔ EN).

### Tests
- `i18n` enregistré globalement dans `test-setup.ts` (`$t` disponible à chaque montage) ; specs
  changeant la locale la restaurent (isolation).
- Frontend **234** (+ namespace `suppliers` dans `i18n.spec`, re-rendu EN dans
  `SupplierListView.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 2ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave2` (release `v1.0.0` → `rc.23`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03, 2ᵉ vague)** — `SupplierListView` (création/édition fournisseur) et
  `CategoryListView` (création/édition catégorie) migrées de leur modale ad-hoc vers `<BaseModal>`
  (overlay, `Teleport`, focus-trap, Échap, clic-extérieur, en-tête/fermeture cohérents ; styles
  `.modal-*` dupliqués supprimés). Pour le formulaire fournisseur (vrai `<form>`), le `<form id>`
  reste dans le slot par défaut et le bouton du `#footer` y est lié par l'attribut `form="…"`
  (validation native + Entrée préservées). 4 modales migrées au total (2 vagues).

### Tests
- Frontend **230** (+2 : `SupplierListView.spec.ts` — liste + ouverture d'un `role="dialog"`
  `aria-modal` avec bouton `form="supplier-form"`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : fondation i18n FR + EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-foundation` (release `v1.0.0` → `rc.22`).
Voir `docs/modules/i18n.md`.

### UX
- **Fondation i18n (UX-13)** — noyau d'internationalisation **léger et sans dépendance**
  (`src/i18n/index.ts`) qui imite l'API `t()` de vue-i18n (migration future facilitée) :
  langues **FR + EN**, clés en chemin pointé + interpolation `{param}`, fallback FR puis clé brute.
  `$t` exposé globalement (typé pour `vue-tsc`), composable `useI18n()`. **Sélecteur de langue**
  (`LanguageSwitcher`, barre supérieure) avec **persistance** (`localStorage` — non sensible,
  contrairement au token d'auth) et mise à jour de `<html lang>`. Pilote : `NotFoundView` migrée.
  La migration des chaînes du reste de l'app est incrémentale (français = source de vérité).

### Tests
- Frontend **227** (+6 : `i18n.spec.ts` — traduction/interpolation/fallback/persistance +
  `LanguageSwitcher`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : composant Icon (UX-11) (2026-06-07)

Branche `feature/ux-icon-component` (release `v1.0.0` → `rc.21`).
Voir `docs/modules/ux-design-system.md` (§ Composants).

### UX
- **Composant `Icon` (UX-11)** — `shared/ui/Icon.vue` centralise les petites icônes ligne
  redessinées à la main un peu partout : `<Icon name="plus" :size="14" />`. Registre **statique**
  de primitives SVG (whitelist, **pas de `v-html`**), grille 16×16, `currentColor` ; décoratif par
  défaut (`aria-hidden`), `title` → `role="img"`. Noms : plus, search, view, edit, close, trash,
  check, download, filter, chevron-left/right. Adopté sur **Produits** (bouton + recherche) et
  **Paiements** ; industrialisation incrémentale du reste des SVG inline.

### Tests
- Frontend **221** (+5 : `Icon.spec.ts` — primitives par nom, a11y décoratif/`title`, taille) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : filtres persistés dans l'URL (UX-12) (2026-06-07)

Branche `feature/ux-url-filters` (release `v1.0.0` → `rc.20`).
Voir `docs/modules/ux-design-system.md` (§ Filtres de liste persistés).

### UX
- **Filtres dans l'URL (UX-12)** — nouveau composable `useUrlFilters` : un objet de filtres
  réactif est synchronisé avec la query string. Les filtres **survivent au rafraîchissement et
  au bouton Précédent**, et une liste filtrée devient **partageable par URL**. Valeurs vides /
  par défaut omises (URL propre), types coercés, `router.replace` (pas de pollution d'historique).
  Adopté sur **Produits** et **Paiements** ; adoption incrémentale du reste.

### Tests
- Frontend **216** (+3 : `useUrlFilters.spec.ts` — hydratation, miroir, clés inconnues) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 1ʳᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-migration` (release `v1.0.0` → `rc.19`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03)** — `CustomerListView` (création/édition client) et `PaymentListView`
  (enregistrement de paiement) migrées de leur modale ad-hoc vers le composant partagé
  `<BaseModal>` : overlay, `Teleport`, **focus-trap**, Échap, clic-extérieur, en-tête et bouton
  de fermeture cohérents. Chrome local et styles `.modal-*` dupliqués supprimés. La fermeture est
  liée via `@update:model-value` pour préserver les effets de bord (réinitialisation du formulaire).
  Reste l'adoption incrémentale (~22 vues).

### Tests
- Frontend **213** (+1 : `PaymentListView.spec.ts` ouvre un `role="dialog"` `aria-modal`) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : cartes mobiles (UX-06) (2026-06-07)

Branche `feature/ux-mobile-cards` (release `v1.0.0` → `rc.18`).
Voir `docs/modules/ux-design-system.md` (§ Tableaux responsives).

### UX
- **Cartes empilées sur mobile (UX-06)** — nouveau pattern CSS-only `.data-table--cards` :
  sous **640px**, chaque ligne de tableau devient une **carte** ; chaque cellule porte
  `data-label="<colonne>"` (libellé affiché via `::before`), l'identité prend `.cell-primary`
  (titre pleine largeur) et les actions `.cell-actions`. Comme une carte a de la place
  verticale, les colonnes masquées par `.hide-mobile` y **réapparaissent** en lignes libellées.
  Le défilement horizontal reste le comportement par défaut. Adopté sur **Commandes, Clients,
  Paiements** ; adoption incrémentale du reste des listes (contrat documenté + testé).

### Tests
- Frontend **212** (+1 : contrat « card-stacking » dans `PaymentListView.spec.ts`) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : feedback action (403) + page 404 (2026-06-07)

Branche `feature/ux-action-feedback` (release `v1.0.0` → `rc.17`).
Voir `docs/modules/ux-design-system.md`, `docs/recette/etat-des-lieux-v1.0.0.md`.

### UX
- **403 jamais silencieux (UX-10)** — le client API émet `api:forbidden` (message du backend) ;
  `useNotifications` l'écoute (une fois, au montage) et le remonte en **toast d'erreur** via la
  nouvelle fonction `pushToast(message, severity = 'error')`. Une action refusée par
  rôle / permission / module est désormais visible (auparavant : échec muet). Les toasts client
  portent `type: 'client'` (libellé « Accès refusé »).
- **Page 404 design-system (UX-14)** — `NotFoundView` (catch-all) reconstruite sur `StateBlock`
  (`empty`) + `BaseButton` (retour tableau de bord), cohérente avec `/unavailable` (402).

### Tests
- Frontend **211** (+4 : `composables/__tests__/useNotifications.spec.ts`) · `vue-tsc` propre ·
  `npm audit` 0 vulnérabilité.

## [Non publié] — Remédiation audit sécurité (2026-06-07)

Implémente l'intégralité des gates de l'audit sécurité (branche
`feature/security-audit-remediation`). Voir `docs/security/security-remediation-tests.md`,
`docs/modules/rbac.md`.

### Sécurité
- **Gating module fail-closed** — `module:<code>` étendu à **tous** les modules métier
  (catalog, inventory, orders, customers, payments + delivery/suppliers/import_export/
  reports) ; un tenant sans le module (ou sans aucune ligne `tenant_modules`) est **refusé**
  (un menu masqué n'est pas un contrôle d'accès). `dashboard` (cœur) reste actif.
- **Permissions métier sur les créations** — `POST` clients/paiements/commandes gardés par
  `role_or_permission:manager|admin|<module>.create` → un `viewer` ne peut plus créer.
- **Hiérarchie des rôles** — autorité centrale `RoleHierarchy` : un `manager` ne peut plus
  inviter, attribuer ni accorder temporairement le rôle `manager` (anti-escalade latérale).
- **Isolation multitenant** — le `parent_id` d'une catégorie doit appartenir au tenant courant.
- **Preuves de paiement privées** — stockées sur le disque privé (plus le disque public) ;
  payload tenant sans `proof_url` ; téléchargement admin via **URL signée courte**.
- **Chaîne d'audit vérifiable** — empreinte d'intégrité calculée via un payload canonique
  **partagé** entre création et vérification (`created_at` épinglé, `ts` unix s) →
  `verify-chain` valide réellement une chaîne propre.
- **Frontend** — token Bearer **en mémoire seule** (plus de `localStorage`/`sessionStorage`,
  legacy purgé) ; `v-html` sur les SVG de modules remplacé par un composant `ModuleIcon`
  (whitelist statique). Dépendances : `composer audit` / `npm audit` = 0 vulnérabilité.

### Tests
- Backend **638** (636 passés, 2 skipped) — `SecurityRemediationTest` (16) + `ModuleGatingTest`
  durci verts. Frontend **191** (gates `frontendSecurity` + `auth` verts) · `vue-tsc` propre.

### En attente
- Audit UX/UI (`docs/ux-ui/audit-ux-ui-approfondi.md`, P0/P1) et cahiers catalogue
  (produits spéciaux + duplication) — sessions dédiées, arbitrage produit requis.

## [1.0.0-rc.4] — 2026-06-06 (RBAC Phase B2 — rôles custom + permissions fines)

Achève le programme RBAC **A + B2 + C**. La Phase **B2** ajoute des rôles configurables
par tenant **et** applique réellement les permissions fines sur les écritures sensibles.

### Ajouté
- **RBAC B2.2 — application des permissions** : 15 groupes de routes d'écriture sensibles
  migrés de `role:manager|admin` → `role_or_permission:manager|admin|<perm-granulaire>`
  (Catalog, Customers, Suppliers, Inventory ×6, Orders ×2, Marketplace, Delivery,
  Import/Export, Reports) + void paiement gardé dans le contrôleur via `payments.delete`.
  Les permissions choisies sont **exclusives à admin/manager** → le comportement des rôles
  de base est **strictement préservé** (un `member` reste bloqué) ; un **rôle custom**
  porteur de la permission **passe la garde**.
- **3 permissions de gestion** ajoutées au seeder (admin/manager, accordables) pour les
  routes de cycle de vie sans permission CRUD dédiée : `orders.manage`, `delivery.manage`,
  `marketplace.manage`.
- **RBAC B2.3 — UI Paramètres → Rôles** (admin) : `RolesPanel.vue` + `roleService` —
  lister les rôles (base en lecture seule + custom éditables), créer/éditer un rôle custom
  (permissions `grantable` **regroupées par module**), supprimer. Les sélecteurs de rôle
  (onglet Équipe + invitation) incluent désormais les **rôles personnalisés**.

### Sécurité
- `role_or_permission` s'appuie sur `Gate::before` (Spatie) qui **avale**
  `PermissionDoesNotExist` → une route peut référencer une permission non seedée sans
  jamais transformer un `403` en `500` (robustesse des ~600 tests existants).

### Docs
- `docs/modules/rbac.md` (architecture 4 couches + **table route→permission**),
  `docs/user/roles.md` (guide admin), audit `docs/recette/rbac-acl-audit-v1.0.0.md`
  (B2 → **livré**), `docs/plan.md`.

### Reste avant GO ferme v1.0.0
- Recette finale + décision P6 (approche A recommandée) + CI install propre.
- En perspective : invitations email + login 2FA email.

### Tests
- Backend **622** (620 passés, 2 skipped) — dont `PermissionEnforcementTest` (9).
- Frontend **189** (+`roleService` ×5, `RolesPanel` ×3) · `vue-tsc` propre.

## [1.0.0-rc.3] — 2026-06-06 (RBAC durci)

Incrément depuis rc.1 (rc.2 = UI gaps ; rc.3 = RBAC A+C). Programme RBAC **A+B2+C**
décidé ; **A et C livrés**, **B2 (rôles custom + permissions fines) en session dédiée**.

### Ajouté
- **RBAC Phase A — gating module dynamique** : middleware `module:<code>` (data-driven) ;
  retirer un module à un tenant **bloque réellement ses routes backend** pour TOUS les
  utilisateurs (admins inclus), plus seulement les menus. Fail-open pour tenants non
  provisionnés. Modules gatés : `reports`, `suppliers`, `import_export`, `delivery`.
- **RBAC Phase C — accès temporaires auto-expirants** : `temporary_access_grants` +
  commande planifiée `access:revoke-expired` (chaque minute → expiration **sans action
  manuelle**) + UI « Accès temp. » (Paramètres → Équipe). `admin` non grantable temporairement.
- **UI gaps comblés (rc.2)** : Ajustements de stock (Stock → Ajustements) + édition des
  limites de plan (admin).

### Audits
- `docs/recette/rbac-acl-audit-v1.0.0.md` (5 exigences ↔ état + plan A/B2/C) ;
  `docs/recette/route-audit-v1.0.0.md` (réconciliation routes front↔back, 0 lien cassé).

### Reste avant GO ferme v1.0.0
- **RBAC Phase B2** : rôles custom par tenant (bornés par le plan) + migration
  `role:`→`permission:` des routes sensibles (session dédiée — blast-radius).
- Recette finale + décision P6 (approche A recommandée) + CI install propre.
- En perspective : invitations email + login 2FA email.

### Tests
- Backend **607** (605 passés, 2 skipped) · frontend **181** · `vue-tsc` propre.

## [1.0.0-rc.1] — 2026-06-06 (candidat production)

Release candidate figée depuis `develop` après v0.8.0. Décision : voir
[Go/No-Go v1.0.0](docs/recette/go-no-go-v1.0.0.md) (**GO conditionnel**).

### Ajouté
- **Landing & Upgrade localisés (P4/P5)** — la page tarifs publique **et** l'écran
  d'upgrade connecté consomment `GET /api/public/pricing` (source backend unique) ;
  plus aucun prix contractuel codé en dur côté frontend ; sélecteur pays/devise.
- **Multi-sites (Sprint 20)** — filtre par entrepôt sur Commandes, Paiements et
  Rapports (ventes/stock) ; **scoping d'accès par agence** (`user_warehouses`) :
  un membre non-manager assigné à des sites ne voit que leurs données (isolation
  testée end-to-end) ; assignation via Paramètres → Équipe (modale « Sites »).
- **Admin Règles pays (Sprint 21)** — CRUD super-admin des `CountryRule`
  (devise/fuseau/approbation/blocage/plans par pays).

### Modifié
- Convention de filtre entrepôt des services de liste passée en tableau
  (`?array $warehouseIds`) pour porter à la fois le filtre UI et la restriction d'accès.

### Supprimé
- Code mort : `CreateDeliveryRequest` / `UpdateDeliveryRequest` (règles vides,
  validation faite inline dans le contrôleur) ; artefacts `video_frames/*.png`.

### Tests
- Backend **597** (595 passés, 2 skipped MySQL-only), frontend **175**, `vue-tsc` propre,
  `vite build` OK. +`WarehouseScopeTest`, `WarehouseAccessScopingTest` (isolation HTTP),
  `AdminCountryRuleTest`, services pricing/reports/warehouses.

### À acter avant GO ferme
- Décision P6 (paiements locaux — approche A « manuel » recommandée, déjà fonctionnelle).
- Zones d'ombre : scoping d'accès limité aux **listes** (GET ressource-unique tenant-scopé).
- Branche par défaut GitHub `master` → `main`.

## [0.8.0] — 2026-06-05 (MVP consolidé, pré-1.0)

Première release consolidée depuis `v0.7.0` : MVP complet, audité et durci pour
de premiers clients réels.

### Ajouté
- **POS Web MVP** — module Caisse : sessions de caisse (ouverture/clôture avec
  rapprochement d'écart), encaissement (panier, scan, déclinaisons), atomique.
- **Pricing localisé** — `plan_prices` (10 marchés) + `plan_limits` ; API publique
  `GET /api/public/pricing` ; limites de plan éditables (super-admin).
- **Géolocalisation RGPD-safe** — `GET /api/public/geo` (headers edge/CDN, aucun
  appel tiers), repli locale navigateur.
- Couverture de tests : backend **570** (0 incomplete), frontend **154**
  (couverture 38.7 %), dont smoke tests admin back-office et tests sécurité
  (anti-escalade quotas, enforcement des sièges).
- **Démo exhaustive** — `DemoSeeder` couvre désormais TOUS les modules MVP
  (déclinaisons, mouvements de stock, entrepôts, POS, retours, marketplace,
  promotions, paiement manuel, périodes fiscales, imports, ajustements,
  transferts inter-entrepôts), idempotent et vérifié par `DemoSeederTest`.

### Corrigé
- **Sièges** : les plans payants ne plafonnent plus les utilisateurs
  (`max_users = null`) — fin de l'impasse « Business bloqué à 10 ».
- **Landing** : scroll restauré (verrou de viewport scopé aux shells app/admin).
- **Harnais de test** : `phpunit.xml memory_limit` (la suite OOM-ait à 128 Mo) ;
  `/** @test */` → `#[Test]` (PHPUnit 12) ; suffixes testsuite.

### Sécurité
- Sync (Phase 3) masqué derrière `FEATURE_SYNC` (off par défaut).
- Re-vérification approfondie : isolation multitenant, RBAC, intégrité — RAS.

### Nettoyage
- 32 fichiers stubs générés morts purgés (dont migrations parasites
  `paymentss`/`customerss`).

---

## [0.7.0] — Sprint 7A
- Billing SaaS, Admin back-office, Security, Marketplace (340 tests).

## [0.1.0-alpha.1]
- Bootstrap initial (Laravel 11 + système modulaire).
