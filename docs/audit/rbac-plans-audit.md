# Audit — RBAC/ACL, plans d'abonnement & cohérence des droits (Frynov ERP)

> Date : 2026-07-06 · Périmètre : RBAC/ACL, plans, cohérence promesse↔accès, self-service tenant, sécurité multi-tenant.
> **Statut : audit terminé, aucune modification structurante appliquée.** Diagnostic → matrice d'écarts → roadmap → reco refonte.

---

## 1. Résumé exécutif

| Axe | Verdict |
|---|---|
| **Isolation multi-tenant** | 🟢 Solide — `TenantScope` fail-closed (sentinelle UUID) + `GuardPortalPrincipal` + Spatie **teams** (`tenant_id`) |
| **Anti-escalade de privilèges** | 🟢 Solide — `is_super_admin` non fillable ; plan & modules **super-admin only** ; `RoleHierarchy` + liste `BLOCKED` |
| **Self-service RBAC tenant** | 🟢 Mature — rôles custom bornés (modules actifs + BLOCKED), invitations hiérarchisées, accès temporaires, audit |
| **Enforcement backend des permissions** | 🟠 **Incomplet** — 4 écritures sans contrôle de permission (viewer peut écrire) |
| **Quotas ↔ promesse de plan** | 🟠 **Incomplet** — 5/9 quotas appliqués ; `customers/imports/api/storage/branches` **déclarés mais non enforced** |
| **Cohérence features commerciales ↔ réalité technique** | 🟡 Écart narratif — le tableau `features` sous-entend un feature-tiering qui n'existe pas (tous les modules sur tous les plans) |
| **Audit trail** | 🟡 Bon mais lacunaire — invitation utilisateur & sync de permissions non tracées |

**Niveau de maturité global : élevé (~80 %).** L'architecture (isolation, teams, module gating, quotas, audit chaîné, snapshots) est saine et bien pensée. **Aucun vecteur d'escalade de privilèges trouvé.** Les risques réels sont : (1) quelques écritures non gardées côté permission → un rôle faible peut manipuler des données maîtres ; (2) des quotas « promis » non appliqués → un plan bas obtient **plus que promis**. Rien de critique côté fuite inter-tenant.

**Règle fondamentale (« ni plus, ni moins »)** :
- **« ni plus »** — respecté pour products/orders/warehouses/users/agents ; **violé** pour customers/imports/api/storage (non plafonnés).
- **« ni moins »** — respecté (tous les modules du plan sont activés à la souscription et au changement de plan).

---

## 2. État des plans (matrice)

Modèle de tarification confirmé : **tous les modules sur tous les plans**, différenciation par **sièges + quotas** (commentaire `PlanModulesSeeder` : *« modules accessibles sur chaque plan public… monétiser les sièges et limites »*). `plan_modules.is_included = true` pour toutes les paires.

| Plan (code) | Nom public | Prix mensuel (XOF) | Sièges inclus | Produits | Commandes/mois | Clients | Entrepôts | API/mois | Stockage | Essai |
|---|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| `starter` | **Découverte** | 0 | 1 (dur) | 100 | 50 | 100 | 1 | 0 | 250 Mo | 14 j |
| `essential` | **Essentiel** | 9 900 | 2 (souple) | 500 | 300 | 1 000 | 1 | 0 | 1 Go | 14 j |
| `pro` | **Croissance** | 24 900 | 5 (souple) | 5 000 | 2 000 | 10 000 | 3 | 10 000 | 10 Go | 14 j |
| `enterprise` | **Business / Enterprise** | 59 900 | 10 (souple) | ∞ | ∞ | ∞ | ∞ | ∞ | ∞ | 30 j |

*(Montants stockés en ×100 ; annuel = ×12 ; statut/badge/tax/setup_fee présents.)*

**Modules (11)** : `dashboard` (core, toujours actif) · catalog · inventory · orders · customers · payments · delivery · suppliers · import_export · reports · accounting. **Aucun n'est réservé à un plan.**

**Snapshots** : `SubscriptionChangeRequest.plan_snapshot` fige les conditions du plan cible à la demande → une modification du plan n'altère pas les contrats existants. ✅ (versioning implicite adéquat.)

**Incohérences observées** :
- Nom `enterprise` = **« Business / Enterprise »** (ambigu — mélange deux niveaux de valeur).
- Le tableau `features` (JSON d'affichage) sous-entend une exclusivité (« Rapports avancés » = Pro, « API & intégrations » = Enterprise) **qui n'existe pas** techniquement : tous les tenants ont les modules reports/import_export. → écart marketing↔technique.
- `max_api_calls_per_month` promis à Pro/Enterprise mais **non appliqué** (et pas d'API tenant réelle) → promesse creuse.

---

## 3. État du RBAC/ACL

**Stack** : `spatie/laravel-permission` avec **teams activées** (`config/permission.php` : `teams=true`, `team_foreign_key='tenant_id'`). Le team id est positionné à chaque requête (`ResolveTenant` / `EnsureUserBelongsToTenant` → `setPermissionsTeamId($tenant->id)`). Rôles de base : `tenant_id=NULL` (globaux) ; rôles custom : `tenant_id=<tenant>` (isolés). Unicité composite `(tenant_id, name, guard_name)`.

**Rôles (12 + super-admin)** : admin, manager, member, viewer, agent, cashier, commercial, delivery + accountant, chief-accountant, accounting-viewer, auditor. `super-admin` piloté par le flag `is_super_admin` (pas par une permission).

**Catalogue de permissions (~95)** : global (`tenants.*`, `subscriptions.*`, `users.*`, `roles.*`, `modules.*`, `admin.*`), `accounting.*` (9), `pos.*` (4), `<module>.<action>` (view/create/update/delete/export × 10 modules), + granulaires (products/inventory/orders lifecycle).

**Points forts** :
- 🟢 Rôles custom **bornés** : `TenantRoleService::boundedPermissions()` = intersection `demandé ∩ grantablePermissions`, appliquée en create **et** update.
- 🟢 Liste `BLOCKED` (jamais attribuable par un admin tenant) : `admin.access/tenants/plans/modules/audit`, `tenants.view/manage`, `subscriptions.manage`, `modules.activate/deactivate`, `roles.manage`.
- 🟢 Permissions d'un **module inactif** exclues du grantable (plan/module-gated).
- 🟢 `RoleHierarchy` : un admin ne peut jamais **inviter/accorder `admin`** ; un manager ne peut pas accorder `manager`.
- 🟢 Garde « dernier admin » (impossible de rétrograder le seul admin).
- 🟢 Accès temporaires (`TemporaryAccessService`, transactionnel, révocable).

**Faiblesses** :
- 🟠 **Enforcement 100 % via middleware de route** — **aucune Policy/Gate**. Si un middleware manque sur une route, l'action est ouverte (cf. §4). Pas de 2ᵉ ligne de défense contrôleur pour la plupart des modules.
- 🟠 Permissions déclarées mais **non vérifiées** sur certaines écritures (customers/suppliers/delivery — cf. §4).
- 🟡 `accounting.*` n'est pas généré par la boucle module→action (permissions manuelles) — cohérent mais à surveiller.

---

## 4. Matrice plan → module → permission → protection (écarts)

> Contrôle : la permission promise est-elle **réellement vérifiée côté backend** ?

| Module | Route (écriture) | Middleware permission | Protégé backend | Écart | Prio |
|---|---|---|:--:|---|:--:|
| Customers | `POST /api/customers` | `role_or_permission:manager\|admin\|customers.create` | ✅ | — | — |
| Customers | **`PUT /api/customers/{id}`** | **aucun** | ❌ | Un `viewer` peut **modifier** un client | **P0** |
| Suppliers | **`POST /api/suppliers`** | **aucun** | ❌ | Un `viewer` peut **créer** un fournisseur | **P0** |
| Suppliers | **`PUT /api/suppliers/{id}`** | **aucun** | ❌ | Un `viewer` peut **modifier** un fournisseur | **P0** |
| Suppliers | `DELETE /api/suppliers/{id}` | `…\|suppliers.delete` | ✅ | — | — |
| Delivery | **`POST /api/deliveries`** | **aucun** | ❌ | Un `viewer` peut **créer** une livraison | **P0** |
| Delivery | `…/dispatch\|deliver\|fail` | `…\|delivery.manage` | ✅ | — | — |
| Orders / Catalog / Inventory / Reports / ImportExport / Accounting / Marketplace / Digital | écritures | `role_or_permission:*` présent | ✅ | — | — |
| Workspace (users/roles) | create/update | garde **contrôleur** (`hasAnyRole` / `authorizeManageRoles`) | ✅ | — | — |

**Quotas (promesse de plan) vs enforcement réel** :

| Quota | Appliqué backend ? | Écart |
|---|:--:|---|
| `max_users` / `max_agents` | ✅ (dur sur free ; souple sinon) | — |
| `max_products` | ✅ (`quota:products` + `assertCanAddProduct`) | — |
| `max_monthly_orders` | ✅ | — |
| `max_warehouses` | ✅ | — |
| **`max_customers`** | ❌ | Découverte (promis 100) peut créer **∞** clients — **P1** |
| **`max_imports_per_month`** | ❌ | Import non plafonné — **P1** |
| **`max_api_calls_per_month`** | ❌ | Non appliqué (pas d'API tenant) — P3 |
| **`storage_mb`** | ❌ | Aucun calcul d'usage stockage — P2 |
| **`max_branches`** | ❌ | Non appliqué (colonne présente) — P2 |

---

## 5. Risques

| Catégorie | Risque | Sévérité |
|---|---|---|
| **Sécurité / accès** | Rôles faibles (`viewer`, `member`) peuvent écrire clients/fournisseurs/livraisons (4 endpoints) | 🔴 Haute |
| **Commercial** | Plans bas obtiennent **plus que promis** (clients/imports/stockage illimités) → perte de valeur d'upsell | 🟠 Moyenne |
| **Commercial / légal** | `features` promet des exclusivités inexistantes (Rapports/API/Marketplace) → promesse non tenue au sens strict | 🟡 Faible-moyen |
| **Dette technique** | Enforcement uniquement par middleware de route (pas de Policy) → fragile aux refactors de routes | 🟠 Moyenne |
| **Traçabilité** | Invitation utilisateur & sync de permissions non auditées → angle mort forensique | 🟡 Moyenne |
| **Exploitation SaaS** | `max_api_calls` promis sans mécanisme → incohérence offre | 🟡 Faible |

Aucune fuite inter-tenant, aucune escalade super-admin, aucun self-plan-change identifié. 🟢

---

## 6. Recommandations

**Immédiates (P0/P1 — correctifs simples)**
1. Ajouter `role_or_permission:` sur les 4 écritures non gardées (customers PUT, suppliers POST/PUT, delivery POST).
2. Enforcer `max_customers` (+ éventuellement `max_imports_per_month`) via `QuotaService::assertCanAddCustomer()` + middleware `quota:customers`.
3. Tracer l'invitation utilisateur (`workspace.user_created`) + wrapper la création user+rôle en transaction.

**Structurelles (P2/P3)**
4. Introduire des **Policies** par module (2ᵉ ligne de défense) + `$this->authorize()` dans les contrôleurs d'écriture.
5. Décider du sort des quotas non appliqués : soit les enforcer (storage/branches), soit les retirer de l'offre pour cohérence.
6. Aligner `features` (marketing) sur la réalité technique : soit re-tierer les modules par plan, soit reformuler les features en « inclus partout, volumes selon plan ».
7. Auditer les syncs de permissions (log dédié).

**Futures (P4/P5)**
8. Tests d'accès automatisés (matrice rôle×endpoint) en CI.
9. Reporting d'usage vs quota par tenant (dashboard super-admin — déjà amorcé par `plans/analytics`).

---

## 7. Roadmap priorisée

- **P0 — Sécurité d'accès** : gardes permission sur customers PUT, suppliers POST/PUT, delivery POST. *(1 fichier route × 3, non destructif, mais **change l'accès** → validation requise.)*
- **P1 — Cohérence plan↔quota** : enforcement `max_customers` (+ imports). *(nouveau `assertCanAddCustomer` + middleware ; **change l'accès** → validation.)*
- **P2 — Défense en profondeur** : Policies + `authorize()` sur les écritures ; enforcement storage/branches ou retrait.
- **P3 — Traçabilité** : audit log invitation + sync permissions ; transaction inviteUser.
- **P4 — Cohérence commerciale** : refonte `features`/tiering (cf. §9) ; clarifier le plan « Business / Enterprise ».
- **P5 — Outillage** : tests d'accès en CI, reporting usage/quota.

---

## 8. Plan de correction (fichiers, tests, régressions)

### P0 — Gardes de permission manquantes (à valider avant application)
- **Fichiers** : `app/Modules/Customers/routes/api.php`, `app/Modules/Suppliers/routes/api.php`, `app/Modules/Delivery/routes/api.php`.
- **Changement** : ajouter `->middleware('role_or_permission:manager|admin|<perm>')` sur les 4 routes (perms existantes : `customers.update`, `suppliers.create`, `suppliers.update`, `delivery.create`… vérifier que `delivery.create`/`suppliers.create` existent, sinon utiliser `<module>.manage`).
- **Tests** : 4 tests feature « un viewer reçoit 403 sur POST/PUT ; un manager/admin réussit ».
- **Régression** : si un rôle métier légitime (ex. `commercial`, `member`) créait des clients/fournisseurs via ces endpoints, il perdra l'accès → **vérifier les permissions attendues par rôle avant d'appliquer** (d'où la validation).

### P1 — Quota clients
- **Fichiers** : `app/Modules/Billing/Services/QuotaService.php` (+ `assertCanAddCustomer` + case dans `check()`/`usage()`), route de création client (`quota:customers`).
- **Migration** : aucune (colonne `max_customers` déjà présente).
- **Tests** : « un tenant Découverte bloqué à 100 clients (402) ».
- **Régression** : des tenants existants au-delà de la limite verraient la **création** bloquée (données conservées) → comportement voulu, mais à annoncer.

### P3 — Audit & transaction (correctifs SAFE, non bloquants pour l'accès)
- **Fichier** : `WorkspaceController::inviteUser()` — ajouter `AuditService::logFromRequest('workspace.user_created', …)` + `DB::transaction()` autour de `User::create` + `assignRole`.
- **Régression** : nulle (additif).

### P2/P4 — structurels : à cadrer séparément (Policies, tiering features) — **ne pas coder sans validation**.

---

## 9. Phase complémentaire — Faut-il refondre les plans ?

**Verdict : conserver la structure actuelle (les 4 plans sont sains et cohérents), avec 3 ajustements ciblés.** Pas de refonte lourde nécessaire.

**Justification** : les 4 plans sont bien différenciés (free → 2 → 5 → 10 sièges, quotas croissants), le modèle « tous modules + quotas » est simple et pro-adoption, le snapshot protège les contrats. La refonte serait sur-ingénierie.

**3 ajustements recommandés (matrice)** :

| Plan actuel | Problème | Recommandation | Impact | Risque migration | Prio |
|---|---|---|---|:--:|:--:|
| `enterprise` « Business / Enterprise » | Nom ambigu (2 niveaux) | Renommer en **« Enterprise »** (ou scinder Business ⁄ Enterprise **seulement si** un besoin commercial réel de 2 paliers hauts existe) | Nom d'affichage + i18n ; **pas** de changement de code plan | Faible (snapshot protège l'existant) | P4 |
| `features` (tous plans) | Sous-entend un feature-tiering inexistant | Reformuler : « Tous les modules inclus — volumes selon le plan » ; déplacer les vraies exclusivités (API, SLA) en **add-ons** ou en quotas réels | Tableau `features` (JSON) + page pricing | Nul (affichage) | P4 |
| Quotas déclarés non enforced | Promesse non tenue | Enforcer `max_customers`/`imports` (P1) ; enforcer ou retirer storage/api/branches | QuotaService + middleware | Faible | P1/P2 |

**Add-ons envisageables** (si monétisation par module souhaitée plus tard) : accounting SYSCOHADA, API/intégrations, sièges additionnels (déjà modélisés via `extra_user_amount_minor`), stockage supplémentaire.

### Seeds

Les seeders existants sont **déjà idempotents** (`updateOrCreate`, slugs stables) et cohérents :
`PlansSeeder` (plans + `plan_prices` 10 marchés + `plan_limits`), `PlanModulesSeeder`, `ErpModulesSeeder`, `RolesAndPermissionsSeeder`, `PaymentMethods`/`Markets`, `SuperAdminSeeder`. **Aucun seeder manquant critique.** Un `assertCanAddCustomer` ne nécessite pas de seed (colonne présente).

**Aucune suppression brutale requise** : les plans se conservent (statut `active`/`draft`/`archived` déjà en place), l'archivage est non destructif, les snapshots figent les droits. La stratégie de migration (archiver plutôt que supprimer, snapshot par abonnement) est **déjà implémentée**.

---

## 10. Corrections appliquées (validées) & reste à cadrer

**✅ Appliqué (validé par le product owner, tests inclus)** :
- **P0** — gardes de permission ajoutées sur les 4 écritures : `PUT /customers/{id}` (`customers.update`),
  `POST /suppliers` (`suppliers.create`), `PUT /suppliers/{id}` (`suppliers.update`),
  `POST /deliveries` (`delivery.create`) — motif `manager|admin|<perm>`. Test `WriteEndpointGuardsTest`
  (viewer → 403, admin → passe).
- **P1** — quota `max_customers` enforced : `QuotaService::assertCanAddCustomer` + middleware
  `quota:customers` sur la création client (402 au-delà). Test `CustomerQuotaTest`.
- **P3** — invitation utilisateur : audit `workspace.user_created` + création user+rôle en transaction.
- **P4** — `features` reformulés (« Tous les modules inclus — volumes selon le plan ») ; plan `enterprise`
  renommé **« Enterprise »** (back seeder + i18n FR/EN). Re-seed requis (`db:seed --class=PlansSeeder`).

**✅ P2 — Défense en profondeur & quotas restants (traité)** :
- **Policies** : `App\Shared\Authorization\ModulePolicy` (base réutilisable) + `SupplierPolicy` /
  `CustomerPolicy` / `DeliveryPolicy`, enregistrées dans `AppServiceProvider`. `Gate::authorize(...)`
  ajouté sur les écritures des 3 contrôleurs (create/update/delete) → 2ᵉ ligne de défense si un
  middleware de route venait à manquer. Test `ModulePolicyTest` (viewer refusé, admin/manager autorisés).
- **Quota imports** : `max_imports_per_month` enforced (`assertCanCreateImport` + `quota:imports` sur
  `POST /import/upload`). Test `ImportQuotaTest`.
- **`max_branches`** : **aucun modèle Branch** en base → quota **redondant avec `max_warehouses`**.
  Recommandation : le retirer de l'offre/plan_limits (ou l'aliaser sur warehouses). Non enforçable.
- **`storage_mb`** : pas de compteur d'usage stockage central (fichiers épars sur plusieurs disques).
  Enforcement fiable = un **tracker d'usage** (compteur incrémenté à l'upload/suppression) → chantier
  dédié, **différé** (ne pas half-implémenter un calcul inexact).
- **`max_api_calls_per_month`** : aucune API tenant réelle → non applicable ; **retirer de l'offre** tant
  qu'il n'y a pas d'API.

**✅ P5 — Outillage (traité)** :
- **Garde-fou CI** `RouteAccessGuardTest` : toute route d'écriture sous `module:` DOIT porter une garde
  de permission — une nouvelle route non gardée fait échouer la CI. **Ce test a immédiatement révélé 5
  écritures non gardées supplémentaires** (au-delà des 4 de l'audit initial), toutes fermées :
  `POST /inventory/adjustments` (`inventory.adjust`), `DELETE /payments/{id}` (`payments.delete`),
  `POST /import/upload` (`import_export.create`), `PATCH /import/{id}/mapping` +
  `DELETE /import/{id}` (`import_export.update`).
- **Reporting usage vs quota** : `GET /api/me/subscription/usage` (`QuotaService::usageReport`) →
  usage/limite/reste/% par ressource (users/products/customers/warehouses/orders/imports). Test
  `UsageReportTest`. **Jauge d'usage** affichée dans l'onglet Abonnement (barres vert / orange ≥80 % /
  rouge ≥100 %) — vérifiée en navigateur (Découverte : « Utilisateurs 4/1 » en rouge, « Commandes 40/50 »).

**⏳ Reste (optionnel)** : extension des Policies aux autres modules (products/orders…) — le garde-fou CI
couvre déjà le risque de régression.
