# Audit RBAC / ACL — avant v1.0.0

> Objectif : auditer, sécuriser et réajuster le contrôle d'accès (rôles, permissions,
> modules, quotas, isolation) pour une gestion fine par tenant respectant les plans.
> Date : 2026-06-06. Périmètre : `release/v1.0.0`.

---

## 1. Architecture actuelle (constats)

| Brique | État | Détail |
|---|---|---|
| **Isolation multitenant** | ✅ Solide | `HasTenant` + `TenantScope` global (résout `current.tenant.id` / `auth()->user()->tenant_id`) ; testé (`MultiTenantIsolationTest`). |
| **Rôles (Spatie, teams=true)** | ✅ | Rôles tenant-scopés ; team context posé par `EnsureUserBelongsToTenant`. 8 rôles seedés : admin, manager, member, viewer, cashier, agent, commercial, delivery. |
| **Permissions** | ⚠️ Définies, **non appliquées** | ~68 permissions granulaires seedées (`users.*`, `roles.*`, `modules.*`, `products.*`, `pos.*`…) **mais 0 route gardée par `permission:`** — tout passe par `role:`. Les permissions sont donc surtout décoratives au niveau routes. |
| **Garde de routes** | ⚠️ Rôle uniquement | `role:` ×17 · `permission:` ×0 · `module:` ×0. |
| **Quotas de plan** | ✅ | `EnforceQuota` (`quota:users`, `quota:products`…) lit `plan_limits` ; testé (anti-escalade). |
| **Registre de modules** | ⚠️ Lecture seule | `tenant_modules` (active/trial) + `ModuleRegistryService::tenantHasModule()` **mais utilisé seulement pour renvoyer la liste au frontend** (`/me/modules`, `active_modules`). **Aucun gating backend.** |
| **Accès par agence** | ✅ (livré) | `user_warehouses` + `WarehouseScope` (Sprint 20). |
| **Accès temporaires** | ❌ Inexistant | Aucun mécanisme d'expiration d'accès utilisateur. |

---

## 2. Exigences ↔ état réel (analyse d'écart)

| # | Exigence | État | Écart / risque |
|---|---|---|---|
| **1** | Gestion **fine** des accès par tenant | ⚠️ **Partiel** | Permissions granulaires existent mais **non enforced** (routes en `role:`). L'accès réel est *role-based*, pas *permission-based*. |
| **2** | Tenant **invite + configure rôles + attribue permissions précises** | ⚠️ **Partiel** | Invitation ✅, changement de rôle (parmi 8 rôles **fixes**) ✅. **Pas de rôles custom** ni d'**attribution de permission par utilisateur** (l'UI choisit un rôle figé ; `roles.manage` n'a ni endpoint ni écran). |
| **3** | **Isolation stricte** (features/routes/menus/permissions non autorisés) | ⚠️ **Partiel** | Isolation **tenant** ✅ ; menus filtrés (`usePermission`) ✅ ; mais blocage **par module** absent côté backend (#4) et permissions fines non appliquées (#1). |
| **4** | Super-admin **retire un module → ACL auto-révoquées pour TOUS (admins inclus)** | ❌ **GAP critique (sécurité)** | Le super-admin peut activer/désactiver (`tenant_modules`), mais **aucun middleware ne bloque les routes du module**. Retirer un module **masque les menus** (frontend) mais **n'empêche pas** l'appel direct des API du module. |
| **5** | **Accès temporaires** (durée limitée, permissions spécifiques, **expiration auto**) | ❌ **GAP total** | N'existe pas. |

---

## 3. Plan de réajustement (par phases)

> ⚠️ **Blast-radius** : ajouter un gating `permission:`/`module:` sur les routes **casse les ~600 tests**
> existants (les tenants de test n'activent pas explicitement modules/permissions). Chaque phase doit
> donc livrer **le gating + la mise à jour des setups de test + des tests d'isolation dédiés**.

### Phase A — **Gating par module** (ferme le trou #4) — *priorité sécurité*
- Middleware `EnsureTenantHasModule` (alias `module:<code>`) → `tenantHasModule()` sinon **403**, **sans bypass rôle** (un admin tenant perd l'accès si le module est retiré).
- Appliqué aux groupes de routes des modules **optionnels/retirables** (ex. `marketplace`, `pos`, `import-export`, `reports`, `suppliers`, `delivery`) — pas aux **modules cœur** non retirables (catalog/inventory/orders) pour éviter de rendre un tenant inutilisable + limiter le blast-radius.
- Révocation auto : aucune table d'ACL à purger (les rôles restent) — l'accès est **neutralisé** au runtime par le middleware (conforme « révoqués ou neutralisés »).
- Tests : tenant sans module → 403 sur ses routes ; avec module → 200 ; menu déjà masqué.
- **Décision requise** : liste exacte des modules « retirables » vs « cœur ».

### Phase B — **Permissions fines + rôles configurables par tenant**
- Option B1 (léger) : **permissions par rôle éditables par tenant** (l'admin tenant coche les permissions de ses rôles) + migration des routes sensibles vers `permission:`.
- Option B2 (complet) : **rôles custom par tenant** (créer/nommer un rôle, lui attribuer un sous-ensemble de permissions **borné par le plan**).
- Dans les deux cas : exposer `roles.manage` (endpoint + écran Paramètres → Rôles), borner les permissions accordables à celles **incluses dans le plan/modules actifs**.
- **Décision requise** : B1 ou B2 pour v1 ? (B2 = vrai « fine-grained » mais plus lourd.)

### Phase C — **Accès temporaires auto-expirants**
- Table `temporary_access_grants` (tenant_id, user_id, permissions[]/role, `expires_at`, granted_by).
- Application : à la résolution des permissions, **inclure** les grants non expirés ; **exclure** au-delà de `expires_at`.
- Expiration **sans intervention** : (a) check `expires_at` au runtime (immédiat) **+** (b) job planifié `RevokeExpiredGrants` (nettoyage + audit). Le scheduler (`schedule:run`, déjà câblé en prod) le déclenche.
- UI : Paramètres → Équipe → « Accès temporaire » (membre + permissions + échéance).
- Tests : grant actif → accès ; après `expires_at` → 403 ; job purge + audit.

### Transverse
- Migrer progressivement les routes critiques de `role:` → `permission:` (sans casser les rôles existants : les rôles **portent** déjà les permissions).
- Audit log sur tout changement d'ACL (déjà en place pour `role_changed` ; ajouter `permission_changed`, `module_*`, `temp_access_*`).

---

## 4. Décisions actées (fondateur, 2026-06-06)

**Scope v1.0.0 : A + B2 + C** (tout avant GO ferme), livré **phase par phase** (chacune testée).

| Phase | Décision | État |
|---|---|---|
| **A — Gating module** | ✅ Retenu | ✅ **Livré** : middleware data-driven `module:<code>` (fail-open pour tenants non provisionnés), gate sur `reports` · `suppliers` · `import_export` · `delivery` ; retrait d'un module ⇒ **403 pour tous, admins inclus** ; `ModuleGatingTest`. |
| **B — Rôles/permissions fins** | ✅ Retenu — **B2 (rôles custom par tenant)** | 🔲 À venir : création de rôles tenant + attribution de permissions **bornée par le plan/modules actifs** + migration des routes sensibles `role:`→`permission:`. |
| **C — Accès temporaires** | ✅ Retenu | ✅ **Livré** : `temporary_access_grants` + `TemporaryAccessService` (rôle accordé à échéance, révocation sûre — ne retire jamais un rôle permanent), commande planifiée `access:revoke-expired` (chaque minute → expiration **sans action manuelle**), endpoints workspace + UI **« Accès temp. »** (Paramètres → Équipe). `admin` non grantable temporairement (pas de super-élévation). `TemporaryAccessTest` (6). |

### Contraintes additionnelles actées
- **Modules dynamiques** : ajouter/enrichir un module **ne doit pas** imposer de refonte → **respecté** (gating data-driven : un nouveau module = 1 ligne `module:<code>` + 1 row `erp_modules`, zéro logique hardcodée).
- **À mettre en perspective (planifié — sprint sécurité dédié)** :
  - **Invitations par email** : aujourd'hui l'invitation crée l'utilisateur + renvoie un mot de passe temporaire ; cible = **email d'invitation** (lien d'activation / définition du mot de passe). Nécessite un mailer configuré.
  - **Login 2 niveaux (2FA email)** : mot de passe **puis code de validation par email**. Nécessite : mailer, table/colonnes OTP (hash + expiration), étape de *challenge* à la connexion, flag par tenant/utilisateur. À cadrer en sprint dédié.
