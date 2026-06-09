# État des lieux v1.0.0 — polish UX restant + suite de la recette finale

> Mise à jour : 2026-06-09. Branche `release/v1.0.0` à **`v1.0.0-rc.78`** (cette session → `rc.79+`).
> Complète (et actualise) `go-no-go-v1.0.0.md` (figé à rc.1). Depuis rc.1 ont été livrés :
> RBAC B2 (rc.4), remédiation audit sécurité (rc.5), audit UX/UI P0+P1 (rc.6→rc.13),
> polish UX P1 — garde « modifications non enregistrées » sur la création de commande +
> récap onboarding (rc.14→rc.15), **backfill `tenant_modules`** (rc.16),
> feedback action 403 + page 404 design-system (rc.17), cartes mobiles (rc.18),
> migration `BaseModal` — 1ʳᵉ vague (rc.19), filtres persistés dans l'URL (rc.20),
> composant `Icon` (rc.21), fondation i18n FR+EN (rc.22), `BaseModal` 2ᵉ vague (rc.23),
> module Fournisseurs traduit FR/EN (rc.24), `BaseModal` 3ᵉ vague (rc.25),
> module Livraisons traduit FR/EN + `BaseModal` (rc.26), `BaseModal` 4ᵉ vague (rc.27),
> module Paiements traduit FR/EN (rc.28), `BaseModal` 5ᵉ vague (rc.29),
> module Rapports traduit FR/EN (rc.30), `BaseModal` vagues 6→8 (rc.31→rc.33),
> liste Commandes traduite FR/EN (rc.34), **migration `BaseModal` terminée** (rc.35→rc.43, 0 ad-hoc),
> liste Produits traduite FR/EN (rc.44), liste Catégories traduite FR/EN (rc.45),
> **refonte UI Side-Drawer — fondation `BaseModal`** (rc.46),
> **confirmations centrées `ConfirmDialog`/`useConfirm`** (rc.47→rc.48, migration `confirm()` terminée),
> **polish volets — Stock, Paiements, Admin, Fournisseurs, Entrepôts, Livraisons & Inventaire** (rc.49→rc.53 : sous-titre en-tête, `.input-affix` unité/devise/%, `.input-group` ; POS déjà conforme),
> **refonte « Side-Drawer » terminée** (rc.54 : sous-titre Marketplace + 7 `alert()` → toasts `pushToast` ; plus aucun `confirm()`/`alert()` bloquant),
> **module Inventaire traduit FR/EN — complet 6/6** : Entrepôts (rc.55), Stock (rc.56), Alertes (rc.57), Ajustements (rc.58), Transferts (rc.59), Périodes (rc.60) — namespace `inventory.*`,
> **module Auth traduit FR/EN** (rc.61 : Login + Register, namespace `auth.*`),
> **Dashboard traduit FR/EN** (rc.62, namespace `dashboard.*`),
> **Profil utilisateur traduit FR/EN** (rc.63, namespace `profile.*`),
> **back-office Admin traduit FR/EN** (rc.64 liste tenants + rc.65 fiche détail + rc.66 Promotions + rc.67 Règles pays + rc.68 Plans & Paiements manuels, namespace `admin.*`),
> **formulaire produit traduit FR/EN** (rc.69, `ProductFormView` — namespace `catalog.productForm.*`)
> **+ tracker i18n vivant** [`i18n-coverage.md`](i18n-coverage.md) — **avancement réel par vue : 37 ✅ / 0 🟡 / 11 ⬜ sur 48** (rc.70 : pagination unifiée `common.pageOf`, 26 vues câblées toutes complètes) — & **Definition of Done i18n** (toute vue créée/modifiée livre FR+EN).
> **garde i18n CI dure** (rc.71 : `npm run i18n:check` — parité FR/EN + texte FR en dur, ratchet allowlist).
> Tests : **backend 642** (640 ✅ / 2 skipped) · **frontend 257** · `vue-tsc` propre ·
> `composer audit` / `npm audit` 0 vulnérabilité.

---

## A. Polish UX restant (audit `ux-ui/audit-ux-ui-approfondi.md`)

> Le **gros de l'audit est livré** : UX-01 (nav modules+permissions), UX-02 (admin/tenant),
> UX-04 (a11y), UX-05 (états + page `/unavailable`), UX-09 (pricing backend), UX-03 design
> system + **adoption sur TOUTES les listes** (tenant + admin + import), UX-06 (`.table-scroll`),
> UX-07 (FormField + useUnsavedChanges, câblé dans ProductForm). Reste, par priorité :

| # | Élément | Priorité | Effort | Détail |
|---|---|---|---|---|
| 1 | ~~**UX-07 — étendre la garde formulaire**~~ | P1 | S | ✅ **Livré** (rc.14) — `useUnsavedChanges` câblé dans la **création de commande** (ProductForm déjà fait). Reste l'onboarding (faible enjeu : pas de perte de saisie longue). |
| 2 | ~~**UX-08 — panneau récap onboarding**~~ | P1 | M | ✅ **Livré** (rc.15) — récap « Ce qui sera configuré » (étape 5) + checklist de fin. Sauvegarde de progression = post-1.0. |
| 3 | ~~**UX-10 — feedback action (403)**~~ | P2 | S | ✅ **Livré** (rc.17) — les 403 de l'API (`api:forbidden`) remontent en **toast** d'erreur (`useNotifications.pushToast`) ; plus d'échec silencieux. |
| 4 | ~~**UX-14 — page 404 dédiée**~~ | P2 | S | ✅ **Livré** (rc.17) — `NotFoundView` migrée sur `StateBlock` + `BaseButton` (cohérence design system). Page 402 = `/unavailable` (déjà livrée). |
| 5 | ~~**UX-06 — cartes mobiles**~~ | P2 | M | ✅ **Pattern livré** (rc.18) — `.data-table--cards` (cartes empilées ≤640px, `data-label` par cellule) adopté sur **Commandes / Clients / Paiements**. Reste l'adoption incrémentale (Produits, Stock, Livraisons, Fournisseurs, Retours, admin). |
| 6 | ~~**UX-03 — adoption `BaseModal`**~~ | P2 | M | ✅ **TERMINÉ (rc.19→rc.43)** — **33 modales / 24 vues** migrées vers `<BaseModal>`, **plus aucune modale ad-hoc** (`grep modal-overlay\|modal-backdrop` = 0). Chrome unifié (overlay, focus-trap, Échap, clic-extérieur) partout. Contrat testé sur 7 specs. **Évolution (rc.46+)** : refonte **Side-Drawer** — `BaseModal` rendu en volet latéral droit par défaut (`variant="drawer"`), `variant="center"` pour les confirmations (Phase 2). Voir `ux-design-system.md`. |
| 7 | ~~**UX-12 — filtres persistés**~~ | P2 | M | ✅ **Pattern livré** (rc.20) — composable `useUrlFilters` (filtres dans l'URL : refresh / back / liens partageables) sur **Produits + Paiements**. Reste l'adoption incrémentale (Commandes, Stock, Clients…). |
| 8 | ~~**UX-11 — composant `Icon`**~~ | P2 | M | ✅ **Composant livré** (rc.21) — `shared/ui/Icon.vue` (registre SVG whitelist, sans `v-html`) adopté sur **Produits + Paiements**. Reste l'industrialisation incrémentale des SVG inline. |
| 9 | ~~**UX-13 — i18n**~~ | P2 | L | ✅ **Fondation (rc.22) + migration en cours (rc.24→rc.69)** — noyau i18n léger sans dépendance (FR + EN), API `$t`/`useI18n`, `LanguageSwitcher`, persistance. **Avancement réel : 26 vues ✅ / 0 🟡 / 22 ⬜ à faire (48 au total)** — tracker vivant **[`i18n-coverage.md`](i18n-coverage.md)** (source de vérité par vue) + **Definition of Done i18n** (toute vue créée/modifiée livre FR+EN dans le même changement). **Garde CI dure** (rc.71, `npm run i18n:check` : parité FR/EN + texte FR en dur, ratchet allowlist). Fait : Fournisseurs, Livraisons, Paiements, Rapports, listes Commandes/Produits/Catégories, Inventaire, Auth, Dashboard, Profil, back-office Admin, formulaire produit, pagination unifiée. Reste (cf. tracker) : Catalogue (ProductShow/Label/Attributs/Variantes), Ventes, POS, Import, Marketplace, Paramètres, Onboarding, Billing ; Customers exclu (session concurrente). |

**Verdict UX** : aucun item bloquant pour le GO. **Tous les items P2 (UX-01→14) sont livrés ou
amorcés** (primitive/fondation + vues phares) ; ne reste que l'**adoption incrémentale** des
patterns (cartes, `BaseModal`, filtres URL, `Icon`, chaînes i18n) — post-1.0, non bloquant.

---

## B. Suite de la recette finale v1.0.0

### B.1 Conditions du GO ferme (de `go-no-go` §Décision) — état actualisé

| Cond. | Sujet | État 2026-06-07 |
|---|---|---|
| **C1** | Recette finale signée | 🔲 **À faire** — périmètre élargi (voir B.3) |
| **C2** | Décision P6 actée | 🟡 Note `docs/decisions/p6-checkout-approach.md` écrite, **approche A recommandée** ; manque la **signature** fondateur. Aucun code bloquant (ManualPayment + upgrade backend livrés). |
| **C3** | CI build sur install propre | ✅ **Satisfait** — `ci-develop.yml` / `ci-feature.yml` font `npm ci` + `npm run build` + `php artisan test`. |
| **C4** | Zones d'ombre acceptées/durcies | 🟡 Voir B.4 (certaines **résolues** par la remédiation sécurité). |

### B.2 ⚠️ Nouveau prérequis de déploiement — CRITIQUE (régression possible)

La remédiation sécurité (rc.5) a rendu le **gating module `fail-closed`** : un tenant **sans
ligne `tenant_modules`** est désormais **refusé sur tous les modules métier** (catalog,
inventory, orders, customers, payments, delivery, suppliers, import_export, reports).

- ✅ Les **nouveaux** tenants sont provisionnés (`SubscriptionService::activatePlanModules` à la
  souscription).
- ❌ **Aucun backfill** n'existe pour les **tenants existants** non provisionnés → ils seraient
  **verrouillés** après déploiement.

**Action obligatoire avant prod** : exécuter la commande de backfill livrée
**`php artisan tenants:backfill-modules`** (idempotente ; option `--dry-run` pour prévisualiser)
qui active les modules du plan pour chaque tenant existant (fallback : tous les modules si le
plan n'est pas résolu), puis vérifier en staging sur copie de prod. *(Sinon, NO-GO.)*
Tests : `BackfillTenantModulesTest` (4).

### B.3 Recette fonctionnelle — périmètre à repasser (sur `migrate:fresh --seed`)

1. **Socle rc.1** : recette v0.8.0 + 5 parcours rc.1 (cf. `go-no-go` §2) — toujours valides.
2. **RBAC B2 (rc.4)** : Paramètres → **Rôles** (créer/éditer/supprimer un rôle custom,
   permissions groupées par module) ; assigner un rôle custom à un membre (onglet Équipe) ;
   vérifier qu'un rôle custom porteur d'une perme d'écriture peut agir, et qu'un `member` ne peut pas.
3. **Sécurité (rc.5)** : module désactivé ⇒ 403 (y compris admin) ; `viewer` ne peut pas créer
   client/paiement/commande ; un `manager` ne peut pas inviter/élever un `manager` ; preuve de
   paiement non accessible en URL publique (admin via URL signée) ; `verify-chain` = `ok`.
4. **UX (rc.6→rc.17)** : sidebar (module inactif **verrouillé** + lien upgrade) ; navigation
   clavier (focus visible, focus-trap modales, Échap) ; états `StateBlock` (vide/chargement/erreur)
   sur toutes les listes ; page `/unavailable` ; modale d'upgrade aux **prix backend** ;
   garde « modifications non enregistrées » sur la fiche produit **et la création de commande** ;
   une action refusée (403) affiche un **toast d'erreur** (jamais d'échec silencieux) ;
   une URL inconnue affiche la **page 404** design-system avec retour au tableau de bord.
5. **Régression mobile** : listes scrollables ; sidebar drawer.

### B.4 Zones floues / d'ombre — état actualisé

| Sujet | État |
|---|---|
| Scoping d'accès = listes uniquement (GET ressource-unique tenant-scopé, pas warehouse) | 🟡 Risque toujours accepté pour v1.0.0 (post-1.0 : durcir les GET unitaires). |
| TVA / remises au niveau commande | 🔲 Backlog (à spécifier si besoin métier). |
| Onboarding `nb_branches` → entrepôts secondaires | 🔲 Post-1.0 (page Agences). |
| Branche par défaut GitHub | ❌ **Toujours `master`** (origin/HEAD) — basculer sur `main`. |

### B.5 Checklist finale v1.0.0 (actualisée)

- [ ] **Backfill `tenant_modules`** : exécuter `php artisan tenants:backfill-modules` sur la prod (B.2) + vérif staging — *bloquant* (commande ✅ livrée + testée).
- [ ] Recette finale signée (périmètre B.3).
- [ ] Décision P6 signée (approche A).
- [ ] Zones d'ombre B.4 acceptées **ou** durcies.
- [x] CI : `npm ci && npm run build` + `php artisan test` (ci-develop/feature). *(rappel : faire tourner la CI sur `release/v1.0.0`.)*
- [ ] Mettre à jour `go-no-go` / `CHANGELOG` pour le tag final `v1.0.0` (récap rc.4→rc.13).
- [ ] GitFlow : `release/v1.0.0` → `main` + tag **`v1.0.0`** + back-merge `develop`.
- [ ] Branche par défaut GitHub = `main`.
- [ ] `migrate:fresh --seed` validé sur l'environnement de prod cible.
- [ ] *(En perspective, post-1.0)* invitations par email + login 2FA email.

---

## C. Recommandation

**Aucun bloquant côté code** hormis le **backfill `tenant_modules`** (B.2), qui est impératif
vu le passage en fail-closed. Séquence recommandée : (1) backfill + vérif staging → (2) finir le
polish UX P1 items 1–2 si souhaité → (3) recette fonctionnelle B.3 → (4) signer P6 → (5) bascule
GitFlow `main` + tag `v1.0.0`.
