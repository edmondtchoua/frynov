# RBAC — Contrôle d'accès (rôles, permissions, modules, accès temporaires)

> Doc technique. Périmètre : programme RBAC **A + B2 + C** de la v1.0.0.
> Stack : [spatie/laravel-permission] v7 (`teams = true`, clé d'équipe `tenant_id`).

Le contrôle d'accès se compose de **quatre couches** complémentaires, appliquées dans
cet ordre par la pile de middleware :

1. **Authentification** — `auth:sanctum` (token Bearer).
2. **Isolation tenant** — `EnsureUserBelongsToTenant` (alias `tenant`) : ancre le
   `tenant_id` sur l'utilisateur authentifié et **pose le team context Spatie**
   (`setPermissionsTeamId`). Toute résolution de rôle/permission est donc tenant-scopée.
3. **Gating module** (Phase A + durcissement audit) — `module:<code>` : si le tenant n'a
   pas le module actif, **403 pour tous (admins inclus)**. **Fail-closed** : un tenant sans
   ligne `tenant_modules` est refusé (un menu masqué n'est jamais un contrôle d'accès).
   Appliqué à **tous** les modules métier : `catalog`, `inventory`, `orders`, `customers`,
   `payments`, `delivery`, `suppliers`, `import_export`, `reports` (le module cœur
   `dashboard` reste toujours actif).
4. **Gating rôle/permission** (Phase B2) — `role:` ou `role_or_permission:` : autorise par
   rôle **ou** par permission granulaire.

---

## 1. Rôles & permissions de base (seeder)

`database/seeders/RolesAndPermissionsSeeder.php` seede les permissions
(`<module>.<action>` + permissions transverses/granulaires) et **8 rôles de base**
(globaux, `tenant_id = NULL`, partagés par tous les tenants) :

| Rôle | Permissions (résumé) |
|---|---|
| `admin` | **toutes** |
| `manager` | toutes **sauf** `users.*`, `roles.*`, `subscriptions.manage`, `admin.*`, `tenants.manage` |
| `member` | `{dashboard,catalog,inventory,orders,customers,delivery}.{view,create,update}` |
| `viewer` | toutes les `*.view` |
| `agent` | `orders.read/create`, `customers.read`, `stock.read` |
| `cashier` | `orders.create/confirm`, `payments.create`, `products.read`, `customers.read`, `pos.*` |
| `commercial` | `orders.create/read`, `customers.create/read`, `catalog.read` |
| `delivery` | `deliveries.read/update`, `orders.read` |

> **Conséquence clé pour B2.2** : pour *garder* le comportement existant en passant une
> route de `role:manager|admin` à `role_or_permission:`, il faut choisir une permission
> que **seuls admin/manager possèdent**. Les `module.action` de base (`catalog.create`,
> `orders.create`, `customers.create`, `inventory.create`…) sont détenues par `member` et
> ne doivent **jamais** servir de garde — sinon `member` gagnerait l'accès en écriture.

---

## 2. B2.1 — Rôles custom par tenant

- **`TenantRoleService`** : CRUD de rôles **custom** scopés au tenant (Spatie `tenant_id`).
  - `grantablePermissions(Tenant)` borne les permissions accordables : exclut
    `admin.*`, `tenants.*`, `subscriptions.manage`, `modules.activate/deactivate`,
    `roles.manage` (escalade) **et** les `module.action` des modules optionnels
    (`delivery`, `suppliers`, `import_export`, `reports`) **non actifs** pour le tenant.
  - `boundedPermissions()` réintersecte toujours côté serveur (défense en profondeur).
- **`TenantRoleController`** → `/api/workspace/roles` (**admin-tenant only**) :
  `GET` (→ `{ data: rôles base+custom, grantable: [perms] }`), `POST`, `PATCH/{role}`,
  `DELETE/{role}`. Isolation par `ownsCustomRole` (un tenant ne touche que ses rôles).
- **Assignation** : `UpdateUserRoleRequest` accepte les rôles custom du tenant
  (assignables par admin/manager, jamais escaladants).

---

## 3. B2.2 — Application des permissions sur les routes

Les groupes de routes d'écriture sensibles passent de `role:manager|admin` à
`role_or_permission:manager|admin|<perm-granulaire>`. La règle d'or : la/les
permission(s) ajoutée(s) sont **exclusives à admin/manager** (cf. §1), donc :

- les rôles de base conservent **exactement** leur comportement (member/viewer/cashier/…
  toujours `403`, admin/manager toujours autorisés **par rôle**) ;
- un **rôle custom** porteur de la permission **passe la garde**.

### Sécurité du mécanisme

`role_or_permission` s'appuie sur `User::canAny()` → `Gate::before` (Spatie) →
`checkPermissionTo()`, qui **avale `PermissionDoesNotExist`** et renvoie `false`. Une
route peut donc référencer une permission **non encore seedée** (cas de nombreux tests
qui ne seedent que les *noms* de rôles) : la garde reste un `403` propre, jamais un `500`.

### Table de correspondance route → permission

| Module | Routes gardées | Middleware |
|---|---|---|
| **Catalog** | produits/catégories/attributs (write) | `role_or_permission:manager\|admin\|products.create\|products.update\|products.delete\|products.archive` |
| **Customers** | `DELETE /api/customers/{id}` | `…\|customers.delete` |
| **Suppliers** | `DELETE /api/suppliers/{id}` | `…\|suppliers.delete` |
| **Inventory** | `move-in/out`, `adjust`, `scan`, `deliveries`, `count` | `…\|inventory.adjust\|inventory.receive` |
| **Inventory** | ajustements `approve/reject` | `…\|inventory.audit` |
| **Inventory** | seuil de stock | `…\|inventory.adjust` |
| **Inventory** | entrepôts (CRUD) | `…\|inventory.audit` |
| **Inventory** | périodes fiscales `store/lock` | `…\|inventory.audit` |
| **Inventory** | transferts `store/ship/receive/resolve` | `…\|inventory.transfer` |
| **Orders** | retours `approve/restock/reject` | `…\|orders.manage` |
| **Orders** | `confirm/fulfill/cancel` | `…\|orders.manage` |
| **Payments** | `DELETE` (void) — *garde dans le contrôleur* | `hasAnyRole(admin,manager)` **ou** `payments.delete` |
| **Marketplace** | listings `create/update/delete` | `…\|marketplace.manage` |
| **Delivery** | `dispatch/deliver/fail` | `…\|delivery.manage` |
| **ImportExport** | import `approve/execute` | `…\|import_export.create\|import_export.update` |
| **Reports** | `dashboard/sales/stock` | `…\|reports.export` |
| **Sync** *(scaffold, derrière `FEATURE_SYNC`)* | `store/update/destroy` | `role:manager\|admin` — **inchangé** (non exposé en prod) |

### Permissions de gestion ajoutées (seeder)

Trois routes de **cycle de vie** n'avaient aucune permission CRUD dédiée nommée
correctement. On a donc ajouté **3 permissions** (admin/manager uniquement, accordables) :

| Permission | Couvre | Note |
|---|---|---|
| `orders.manage` | confirmer / livrer / annuler une commande + retours | module cœur → toujours accordable |
| `delivery.manage` | dispatch / livrer / échec d'une livraison | préfixe `delivery.` → accordable si module `delivery` actif |
| `marketplace.manage` | gérer les annonces marketplace | pas de module gaté → toujours accordable |

> Les autres modules réutilisent une permission **existante** exclusive admin/manager
> (`products.*`, `customers.delete`, `inventory.*`, `reports.export`, `import_export.*`,
> `payments.delete`) — pas de nouvelle permission superflue.

---

## 4. B2.3 — UI Paramètres → Rôles

- **`roleService`** (`frontend/src/modules/settings/services/roleService.ts`) :
  `list()` / `create()` / `update()` / `remove()` sur `/api/workspace/roles`.
- **`RolesPanel.vue`** (onglet **Rôles**, admin only) : liste rôles base (lecture seule) +
  custom (éditables) ; création/édition via modale avec **cases à cocher des permissions
  `grantable` regroupées par module** ; suppression.
- **Onglet Équipe** : les sélecteurs de rôle (tableau + invitation) incluent désormais les
  rôles custom (`<optgroup>` « Rôles personnalisés »), chargés via `GET /api/workspace/roles`.
  Pour un *manager* (qui ne peut pas lire le catalogue de rôles), le sélecteur retombe sur
  les rôles de base (échec `403` silencieux).

---

## 5. Phases A & C (rappel)

- **A — Gating module** (durci par l'audit sécurité) : `EnsureTenantHasModule`
  (alias `module:<code>`), data-driven (`erp_modules` + `tenant_modules`), **fail-closed**.
  Gaté sur **tous** les modules métier : `catalog`, `inventory`, `orders`, `customers`,
  `payments`, `delivery`, `suppliers`, `import_export`, `reports`. Voir `ModuleGatingTest`
  + `SecurityRemediationTest`. (Tests : `Tests\TestCase` provisionne tous les modules par
  défaut — `activateAllModules` ; les suites qui testent la posture non-provisionnée
  désactivent `autoProvisionModules`.)
- **C — Accès temporaires** : `temporary_access_grants` + `TemporaryAccessService` (rôle à
  échéance) + commande `access:revoke-expired` (chaque minute). Voir `TemporaryAccessTest`.

---

## 6. Tests

| Suite | Couvre |
|---|---|
| `PermissionEnforcementTest` (Auth) | B2.2 : custom role avec `products.create`/`customers.delete`/`inventory.adjust`/`orders.manage` **passe** la garde ; `member` **bloqué** ; `admin` ok par rôle. |
| `TenantRoleTest` (Auth) | B2.1 : CRUD rôles custom, bornage `grantable`, isolation inter-tenant, assignation. |
| `CatalogSecurityTest`, `InventorySecurityTest`, `OrdersRbacTest`, `PaymentSecurityTest` | comportement des rôles de base **préservé** (viewer/member `403`). |
| `roleService.spec.ts`, `RolesPanel.spec.ts` (Vitest) | service + smoke UI rôles. |

> ⚠️ **Piège de test** : le guard Sanctum **met en cache le premier utilisateur résolu**
> dans un même test. Un test qui enchaîne deux `withToken()` (deux jetons) réutilise
> silencieusement le premier utilisateur. → **un seul appel HTTP authentifié par test**,
> fixtures créées via modèles/services (cf. `PermissionEnforcementTest`).

[spatie/laravel-permission]: https://spatie.be/docs/laravel-permission
