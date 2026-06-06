# Changelog — Frynov ERP

Toutes les évolutions notables. Format inspiré de [Keep a Changelog](https://keepachangelog.com/),
versionnage [SemVer](https://semver.org/).

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
