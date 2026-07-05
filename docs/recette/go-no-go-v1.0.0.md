# Go / No-Go — Frynov ERP v1.0.0 (candidat production)

> Version candidate à la **mise en production et au début des activités**.
> Branche : `release/v1.0.0` (figée depuis `develop`). RC : **`v1.0.0-rc.54`**.
> Date d'évaluation : 2026-06-06 · **Actualisé : 2026-06-08 (rc.54)**.
> Détail rc-par-rc depuis rc.1 : voir `etat-des-lieux-v1.0.0.md`.

---

## 🚦 Décision

| | |
|---|---|
| **Verdict** | **GO conditionnel** pour publier `v1.0.0-rc.1` et lancer la recette finale. |
| **GO ferme (prod)** sous conditions | (C1) recette finale signée · (C2) décision P6 actée (approche A « manuel/sur-demande » suffit, **aucun code bloquant**) · (C3) CI build sur install propre (`npm ci`) · (C4) acceptation explicite des 2 zones d'ombre ci-dessous. |
| **NO-GO** seulement si | une anomalie bloquante apparaît en recette finale, ou refus d'accepter les zones d'ombre sans durcissement préalable. |

Le socle ERP est **exploitable en production contrôlée** : modules métier couverts par tests, sécurité multitenant + RBAC + audit en place, monnaie centralisée, pricing localisé branché sur le backend, multi-sites (filtres + scoping d'accès) testé en isolation.

> **Durcissements livrés depuis rc.1** (cf. `etat-des-lieux-v1.0.0.md`) : remédiation audit
> sécurité (**module gating fail-closed** + commande de backfill `tenants:backfill-modules`), **RBAC
> rôles custom**, et **audit UX/UI complet** — design system (`BaseModal`/`BaseButton`/`StateBlock`/
> `Icon`/`ConfirmDialog`), **refonte UI « Side-Drawer »** (toutes les modales en volet latéral droit +
> confirmations centrées + alertes en toasts), **i18n FR/EN**, feedback d'action non bloquant.
> ⚠️ **Prérequis prod** (régression possible) : exécuter `php artisan tenants:backfill-modules` avant
> déploiement, sinon les tenants existants seraient verrouillés (gating fail-closed).

---

## 1. Tests automatisés

| Dimension | Résultat | Détail |
|---|---|---|
| **Backend (unit + intégration)** | ✅ **642** (640 passés, **2 skipped**, 0 fatal) | Skips = contraintes `CHECK` **MySQL-only** (`StockIntegrityTest`), non exécutables sous SQLite, validées en prod. (rc.1 : 597) |
| **Frontend (Vitest)** | ✅ **252** (51 fichiers) | + design system (`BaseModal` drawer/center, `ConfirmDialog`), i18n (noyau + namespaces), `useNotifications`, en plus des composants/services/money/géo-pricing/multi-sites historiques. (rc.1 : 179) |
| **Type-check** | ✅ `vue-tsc --noEmit` propre | — |
| **Build prod** | ✅ `vite build` produit `dist/` | ⚠️ le script `npm run build` enchaîne `vue-tsc` : exécuter en CI après `npm ci` (vue-tsc est en devDependency). |

**Couverture clé (sécurité & métier)** : isolation multitenant, RBAC 11 modules, idempotence paiements, quotas de plan (Enterprise + downgrade), convention centimes (`money.ts`), audit trail HMAC (chaîne vérifiable), **scoping d'accès par agence (isolation HTTP testée)**, pricing localisé public, templates d'import, POS, retours/SAV.

---

## 2. UX / UI

| Parcours | État | Référence |
|---|---|---|
| Auth / Onboarding (6 étapes, devise persistée, guard) | ✅ | recette v0.8.0 §1 |
| Catalogue (produits, catégories, variantes N-axes, attributs, étiquettes) | ✅ | recette v0.8.0 §4 |
| Stock multi-entrepôts (entrée/sortie/ajuster, **volets latéraux**) | ✅ | recette v0.8.0 §7 |
| Commandes → Paiements → Livraisons → Retours | ✅ | recette v0.8.0 §6/§8/§10 |
| POS / Caisse (ouvrir, encaisser, clôturer + écart) | ✅ | recette v0.8.0 §9 |
| Import/Export (modèles avec déroulantes tenant, downloads authentifiés) | ✅ | recette v0.8.0 §10 |
| **Landing localisée (P4)** : prix backend par pays + sélecteur devise | ✅ | `docs/user/billing.md` |
| **Upgrade localisé (P5)** : prix backend dans l'espace connecté | ✅ | `docs/user/billing.md` |
| **Multi-sites (Sprint 20)** : filtre entrepôt (listes + rapports) | ✅ | `docs/user/inventory.md` |
| **Accès membre→sites (Sprint 20)** : modale « Sites » (Paramètres → Équipe) | ✅ | ce doc §5 |
| **Admin Règles pays (Sprint 21)** : CRUD super-admin | ✅ | `docs/user/navigation.md` |
| **Ajustements de stock** (Stock → Ajustements) : demande + validation manager + historique | ✅ câblé v1.0.0-rc | `route-audit-v1.0.0.md` |
| **Édition des limites de plan** (super-admin, `PlanListView`) | ✅ câblé v1.0.0-rc | `route-audit-v1.0.0.md` |
| Responsive (sidebar drawer, tableaux scrollables) | ✅ | recette v0.8.0 §13 |
| **Design system (UX-03/05/11)** : `BaseModal`, `BaseButton`, `StateBlock`, `Icon`, `ConfirmDialog` | ✅ rc.19→ | `docs/modules/ux-design-system.md` |
| **Refonte UI « Side-Drawer » (UX-03)** : toutes les modales → volet latéral droit ; confirmations centrées (`useConfirm`) ; alertes → toasts | ✅ rc.46→rc.54 | `ux-design-system.md` |
| **i18n FR/EN (UX-13)** : noyau sans dépendance + sélecteur + 8 zones traduites | ✅ rc.22→rc.45 | `docs/modules/i18n.md` |
| **Feedback non bloquant (UX-10)** : 403 + erreurs d'action → toasts (`pushToast`) | ✅ rc.17/rc.54 | `useNotifications` |

> Recommandé avant GO ferme : repasser la **recette v0.8.0** + les 5 nouveaux parcours ci-dessus sur un `migrate:fresh --seed`.

---

## 3. Sécurité

| Contrôle | État |
|---|---|
| Isolation multitenant (TenantScope + tests) | ✅ |
| RBAC sur 11 modules (writes manager/admin) | ✅ |
| `is_super_admin` non mass-assignable | ✅ |
| Audit trail HMAC (chaîne vérifiable) | ✅ |
| Scoping d'accès par agence (listes) | ✅ testé end-to-end |
| Prix contractuels = source backend (plus de hardcode front) | ✅ P4/P5 |
| Sync (Phase 3) masqué derrière `FEATURE_SYNC=false` | ✅ |
| Pas de secrets/clés en dur, pas de debug (`dd/dump/console.log`) en prod | ✅ (scan : 0) |

---

## 4. Zones floues (hypothèses à confirmer)

| Sujet | Hypothèse actuelle | Action |
|---|---|---|
| **P6 paiements locaux** | v1.0.0 lance avec **approche A** (manuel/sur-demande sur `ManualPayment` existant) ; PSP en post-1.0 | Valider la note `docs/decisions/p6-checkout-approach.md`. |
| Branche par défaut GitHub | `master` (obsolète) pointée par `origin/HEAD` alors que la prod est `main` | Basculer le défaut GitHub sur `main` (réglage repo). |
| TVA / remises au niveau commande | `OrderService::create` = Σ(qté×prix) sans TVA ni remise | Spécifier si le besoin métier se confirme (backlog). |
| Onboarding `nb_branches` | collecté mais ne crée pas encore d'entrepôts secondaires | Câbler avec la page Agences (reste Sprint 20). |

---

## 5. Zones d'ombre (limitations connues — à accepter ou durcir)

| Limitation | Risque | Décision attendue |
|---|---|---|
| **Scoping d'accès = listes uniquement** | Un opérateur restreint connaissant l'UUID d'une ressource d'une autre agence peut l'ouvrir via `GET /orders/{id}`, `/payments/{id}`… (reste **tenant**-scopé, pas warehouse-scopé) | ✅ **Risque accepté pour le lancement v1.0.0** (2026-06-06) — exige de connaître un UUID v7 d'une autre agence du même tenant. À durcir en post-1.0 (durcissement des GET ressource-unique + tests). |
| **Multi-sites** | Page « Agences » dédiée + métadonnées agence sur `Warehouse` non livrées (CRUD entrepôts + assignation membre→sites OK) | Post-1.0. |
| **POS offline** | Non livré (Phase 2) | Hors scope v1.0.0. |

---

## 6. Code mort / qualité

| Constat | Action |
|---|---|
| `CreateDeliveryRequest` / `UpdateDeliveryRequest` (règles vides, `Request` + validation inline dans le contrôleur) | ✅ **Supprimés** dans cette RC (Delivery 30 tests verts après suppression). |
| Module **Sync** (scaffold Phase 3, TODO de gabarit) | Conservé mais **masqué** (`FEATURE_SYNC=false`) ; 33 tests. Non mort, dormant. |
| `MakeModule.php` (TODO dans les **gabarits** générés) | Normal (contenu de templates, pas du runtime). |
| `VariantResolver::Cache::flush()` (TODO : cache tags ciblés) | Optimisation mineure, non bloquante (backlog). |
| `dd/dump/var_dump/console.log` en code prod | ✅ **0** (scan). |
| Artefacts `video_frames/*.png` | ✅ purgés + gitignorés (v0.9.0). |

> Note méthodo : le scan code-mort est **heuristique** (grep refs + spot-check des vues routées) — un outil dédié (`knip`/`ts-prune` front, `composer unused` back) est recommandé en CI pour une garantie exhaustive.

---

## 7. Checklist de finalisation v1.0.0

- [ ] **Backfill `tenant_modules`** (CRITIQUE — gating fail-closed) : `php artisan tenants:backfill-modules` sur la prod + vérif staging *(commande livrée + testée rc.16)*
- [ ] Recette finale signée (recette v0.8.0 + 5 parcours v0.9.0)
- [ ] Décision P6 actée (approche A recommandée ; note `docs/decisions/p6-checkout-approach.md` à signer)
- [ ] Zones d'ombre §5 acceptées **ou** durcies
- [x] CI configurée (`ci-develop`/`ci-feature` : `npm ci` + `npm run build` + `php artisan test`) — *à faire tourner sur `release/v1.0.0`*
- [x] **Audit UX/UI livré** : design system + refonte « Side-Drawer » + i18n FR/EN + feedback toasts (rc.6→rc.54)
- [ ] GitFlow : `release/v1.0.0` → `main` + tag `v1.0.0` + back-merge `develop`
- [ ] Branche par défaut GitHub = `main`
- [ ] `migrate:fresh --seed` validé sur l'environnement de prod cible
