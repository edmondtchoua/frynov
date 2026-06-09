# Couverture i18n — tracker vivant (UX-13)

> **Mise à jour : 2026-06-09 (rc.80).** Source de vérité de l'avancement i18n, vue par vue.
> Remplace les estimations « par zone » de l'état-des-lieux par un **décompte réel par vue**
> (audit multi-agents du 2026-06-09). Le français reste la **source de vérité** ; l'anglais suit.

## ⚖️ Definition of Done i18n (règle opposable)

**Toute vue créée ou modifiée doit livrer FR + EN dans le même changement.** Concrètement :

1. **Zéro chaîne utilisateur en dur** dans une vue touchée : tout passe par `$t('ns.cle')` (template)
   ou `t('ns.cle')` (script — messages `confirm`/`alert`, libellés calculés, erreurs).
2. **Parité des clés** : chaque clé ajoutée existe dans `messages.fr` **et** `messages.en`
   (mêmes chemins) dans `frontend/src/i18n/index.ts`.
3. **Mutualiser** le générique sous `common.*` (name, status, date, amount, previous/next,
   pageOf, cancel, save, genericError…) ; le spécifique sous le namespace du module.
4. **Mettre à jour ce tracker** (faire basculer la ligne) dans le même commit.

Exceptions documentées (ne comptent pas comme reliquat) : CSS, commentaires, `console.*`,
**notes d'audit persistées** envoyées au backend (ex. `note:` POST `/api/inventory` — restent FR
canonique), noms propres / marques (Orange Money, Wave, MTN), codes ISO/devises, placeholders
d'exemple (`VET-0001`, `3700123456789`), symboles/emoji.

## 📊 Synthèse (48 vues de module)

| État | Vues | % |
|---|---|---|
| ✅ **Complet** | 38 | 79 % |
| 🟡 **Partiel** | 0 | 0 % |
| ⬜ **À faire** (non câblé) | 10 | 21 % |
| **Câblées i18n (toutes complètes)** | **38** | **79 %** |

> ✅ **rc.80** : **38 vues complètes** (+ `SettingsView`, gros écran 6 onglets/4 modals). Namespace
> partagé **`geo.*`** (pays par ISO) + `common.currencyName` étendu, réutilisables par Onboarding.
> Aucune vue câblée sans reliquat (garde CI dure depuis rc.71). Reste **10 vues non câblées**.

> ⚠️ Le module **Customers** (`CustomerListView`, `CustomerDetailView`) est géré par une **session
> concurrente** → ne pas y toucher tant que ce verrou n'est pas levé.

## ✅ Complètes (38)

| Module | Vue | Namespace | RC |
|---|---|---|---|
| catalog | `ProductFormView` | `catalog.productForm.*` | rc.69 |
| catalog | `AttributesView` | `catalog.attributes.*` | rc.72 |
| catalog | `VariantsView` | `catalog.variants.*` | rc.72 |
| catalog | `ProductShowPage` | `catalog.productShow.*` | rc.73 |
| catalog | `LabelPrintView` | `catalog.labelPrint.*` | rc.73 |
| catalog | `ProductListView` | `catalog.*` | rc.44 |
| catalog | `CategoryListView` | `catalog.*` | rc.45 |
| admin | `PlanListView` | `admin.*` | rc.68 |
| admin | `ManualPaymentView` | `admin.*` | rc.68 |
| admin | `TenantDetailView` | `admin.*` | rc.65 |
| auth | `LoginView` | `auth.*` | rc.61 |
| auth | `RegisterView` | `auth.*` | rc.61 |
| auth | `ProfileView` | `profile.*` | rc.63 |
| dashboard | `DashboardView` | `dashboard.*` | rc.62 |
| inventory | `WarehouseView` | `inventory.*` | rc.55 |
| inventory | `StockAlertsView` | `inventory.*` | rc.57 |
| inventory | `StockAdjustmentView` | `inventory.*` | rc.58 |
| inventory | `FiscalPeriodView` | `inventory.*` | rc.60 |
| suppliers | `SupplierDetailView` | `suppliers.*` | rc.24 |
| suppliers | `SupplierListView` | `suppliers.*` + `common.pageOf` | rc.24 / rc.70 |
| catalog | `ProductListView` *(pagination)* | `catalog.*` + `common.pageOf` | rc.44 / rc.70 |
| admin | `TenantListView` | `admin.*` + `common.pageOf` | rc.64 / rc.70 |
| admin | `PromotionListView` | `admin.*` + `common.pageOf` | rc.66 / rc.70 |
| admin | `CountryRuleListView` | `admin.*` + `common.deleteFailed` | rc.67 / rc.70 |
| inventory | `StockListView` | `inventory.*` + `common.pageOf/genericError` | rc.56 / rc.70 |
| inventory | `StockTransferView` | `inventory.*` (ship confirm) | rc.59 / rc.70 |
| payments | `PaymentListView` | `payments.*` + `common.pageOf` | rc.28 / rc.70 |
| orders | `OrderListView` | `orders.*` + `common.pageOf` | rc.34 / rc.70 |
| orders | `OrderDetailView` | `orders.detail.*` | rc.74 |
| orders | `ReturnsView` | `orders.returns.*` | rc.74 |
| orders | `OrderCreateView` | `orders.create.*` | rc.75 |
| pos | `PosView` | `pos.*` | rc.76 |
| import-export | `ImportHistoryView` | `importExport.*` | rc.77 |
| import-export | `ImportWizardView` | `importExport.wizard.*` | rc.78 |
| marketplace | `MarketplaceListingsView` | `marketplace.*` | rc.79 |
| settings | `SettingsView` | `settings.*` + `geo.*` | rc.80 |
| deliveries | `DeliveryListView` | `deliveries.*` + `common.pageOf/prev/next` | rc.26 / rc.70 |
| reports | `SalesReportView` / `StockReportView` | `reports.*` (+ `top10`) | rc.30 / rc.70 |

*(+ `NotFoundView` pilote — chrome partagé — complet.)*

## 🟡 Partielles — ✅ toutes soldées en rc.70 (0 restante)

Les 11 vues câblées qui conservaient des reliquats ont été **finalisées en rc.70** :

- **Pagination unifiée** → `common.pageOf` adopté dans `PromotionListView`, `TenantListView`,
  `SupplierListView`, `PaymentListView`, `OrderListView`, `DeliveryListView`, `StockListView`,
  `ProductListView`, `ManualPaymentView` (migrée de `admin.pageOf` → `common.pageOf`, doublon supprimé).
- **Prev/Next** → `common.previous`/`common.next` (`DeliveryListView`).
- **Erreurs** → `CountryRuleListView` `'Suppression impossible.'` → `common.deleteFailed` ;
  `StockListView` `'Une erreur est survenue.'` → `common.genericError`.
- **Confirm transfert** → `StockTransferView` (`inventory.shipTransferTitle/Confirm/ship` ;
  paramètre local `t` renommé `tr` pour ne plus masquer la fonction i18n `t`).
- **Rapports** → `StockReportView` « (top 10) » → `reports.top10`. (`SalesReportView` « SKU » : laissé tel
  quel, identique FR/EN.)

> 🔁 **Cross-cutting pagination — résolu.** Clé canonique `common.pageOf: 'Page {current} / {total}'`.
> Toute nouvelle liste paginée doit l'utiliser (cf. Definition of Done) ; les vues ⬜ ci-dessous
> l'adopteront lors de leur traduction.

## ⬜ À faire — non câblées (22)

| Module | Vues |
|---|---|
| admin | `AdminDashboardView`, `AuditLogView`, `ModuleListView` |
| billing | `BillingView`, `UpgradeView` |
| inventory | `BatchDeliveryView`, `MovementHistoryView` |
| onboarding | `OnboardingView` |
| customers ⚠️ | `CustomerListView`, `CustomerDetailView` *(session concurrente — exclu)* |

## 🛣️ Ordre de bascule recommandé (prochaines RC)

1. ~~**Quick win pagination** — `common.pageOf` + balayage des 11 partielles → 26/26 câblées complètes.~~ ✅ **rc.70**
1b. ~~**Garde automatisée** (gate CI dur).~~ ✅ **rc.71** (cf. section ci-dessous)
2. **Catalogue restant** — `ProductShowPage`, `LabelPrintView`, `AttributesView`, `VariantsView`.
3. **Ventes** — `OrderCreateView`, `OrderDetailView`, `ReturnsView`.
4. **POS** — `PosView` (vue dense, prévoir un namespace `pos.*`).
5. **Import/Export**, **Marketplace**, **Settings**, **Onboarding**, **Billing**.
6. **Admin secondaire** — `AdminDashboardView`, `AuditLogView`, `ModuleListView`.
7. **Customers** — dès que le verrou de session concurrente est levé.

## 🤖 Garde automatisée — ✅ livrée (rc.71)

`npm run i18n:check` (spec `src/i18n/__tests__/i18n-coverage.guard.spec.ts`) — **gate CI dur**, câblé
dans `ci-feature.yml` (job *quality*) et `ci-develop.yml` (job *Build Frontend*), + exécuté dans la
suite vitest (`npm run coverage`). Échoue si :

- **(a) Parité** — une clé existe dans `messages.fr` mais pas `messages.en` (ou inversement). Robuste,
  zéro faux positif.
- **(b) Texte FR en dur** — une vue `src/modules/**/views/*.vue` **hors allowlist** contient un
  **caractère accenté** dans un nœud texte de `<template>` (hors `{{ }}`) ou un attribut statique
  `placeholder`/`title`/`aria-label`/`alt`/`label`. Heuristique haute confiance (accent), faux
  positifs quasi nuls.
- **(c) Anti-bitrot** — l'allowlist ne référence que des vues réellement présentes.

**Ratchet** : l'`ALLOWLIST` du spec liste les 22 vues non encore traduites. On **retire une entrée**
dès qu'une vue est traduite (sinon la garde ne la protège pas). Objectif : allowlist vide.

**Validation à la livraison** : la garde a immédiatement débusqué un reliquat manqué par l'audit
(`WarehouseView` — libellés de devises en dur), corrigé via le nouveau `common.currencyName.*`
(mutualisé entre `WarehouseView` et `ProductFormView`).

**Limites connues** (couvertes par la Definition of Done, pas encore par la garde) : chaînes FR
**sans accent** (`Nom`, `Statut`…), strings **côté `<script>`**, et le **chrome partagé** /
`src/pages`. Extensions possibles : scan des littéraux script avec liste d'exceptions
(`note:` d'audit), élargissement de la portée.
