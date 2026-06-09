# Couverture i18n — tracker vivant (UX-13)

> **Mise à jour : 2026-06-09 (rc.69).** Source de vérité de l'avancement i18n, vue par vue.
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
| ✅ **Complet** | 15 | 31 % |
| 🟡 **Partiel** (câblé, reliquats listés ci-dessous) | 11 | 23 % |
| ⬜ **À faire** (non câblé) | 22 | 46 % |
| **Câblées i18n (✅+🟡)** | **26** | **54 %** |

> ⚠️ Le module **Customers** (`CustomerListView`, `CustomerDetailView`) est géré par une **session
> concurrente** → ne pas y toucher tant que ce verrou n'est pas levé.

## ✅ Complètes (15)

| Module | Vue | Namespace | RC |
|---|---|---|---|
| catalog | `ProductFormView` | `catalog.productForm.*` | rc.69 |
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

*(+ `NotFoundView` pilote — chrome partagé — complet.)*

## 🟡 Partielles — câblées mais reliquats à finir (11)

| Module | Vue | Reliquat(s) | Correctif |
|---|---|---|---|
| admin | `CountryRuleListView` | `error.value = 'Suppression impossible.'` (L≈215) | → `t('common.deleteFailed')` ou clé dédiée |
| admin | `PromotionListView` | pagination « Page {x} / {y} » | → `common.pageOf` |
| admin | `TenantListView` | pagination « Page {x} / {y} » | → `common.pageOf` |
| inventory | `StockTransferView` | confirm « Expédier le transfert {n} ? » + bouton « Expédier » | clés `inventory.*` |
| inventory | `StockListView` | `'Une erreur est survenue.'` (L≈418) | → `t('common.genericError')` |
| suppliers | `SupplierListView` | pagination « Page {x} / {y} » | → `common.pageOf` |
| deliveries | `DeliveryListView` | « ← Précédent » / « Suivant → » | → `common.previous`/`next` |
| payments | `PaymentListView` | pagination « Page {x} / {y} » | → `common.pageOf` |
| orders | `OrderListView` | pagination (libellé en dur) | → `common.pageOf` |
| reports | `StockReportView` | « (top 10) » | clé `reports.*` |
| reports | `SalesReportView` | en-tête « SKU » (limite : identique FR/EN) | optionnel |

### 🔁 Cross-cutting — pagination
Le motif **« Page X / Y » + Précédent/Suivant** est dupliqué/en dur dans **6 vues**
(Promotion, Tenant, Supplier, Payment, Order, Delivery). Action recommandée : ajouter
`common.pageOf: 'Page {current} / {total}'` (déjà fait localement sous `admin.pageOf` en rc.68 →
promouvoir vers `common`) et balayer les 6 vues en une passe (**quick win : 1 RC, 6 vues**).

## ⬜ À faire — non câblées (22)

| Module | Vues |
|---|---|
| admin | `AdminDashboardView`, `AuditLogView`, `ModuleListView` |
| billing | `BillingView`, `UpgradeView` |
| catalog | `AttributesView`, `VariantsView`, `LabelPrintView`, `ProductShowPage` |
| inventory | `BatchDeliveryView`, `MovementHistoryView` |
| orders | `OrderCreateView`, `OrderDetailView`, `ReturnsView` |
| onboarding | `OnboardingView` |
| pos | `PosView` |
| import-export | `ImportHistoryView`, `ImportWizardView` |
| marketplace | `MarketplaceListingsView` |
| settings | `SettingsView` |
| customers ⚠️ | `CustomerListView`, `CustomerDetailView` *(session concurrente — exclu)* |

## 🛣️ Ordre de bascule recommandé (prochaines RC)

1. **Quick win pagination** — `common.pageOf` + balayage des 11 partielles → **26/26 câblées = complètes**.
2. **Catalogue restant** — `ProductShowPage`, `LabelPrintView`, `AttributesView`, `VariantsView`.
3. **Ventes** — `OrderCreateView`, `OrderDetailView`, `ReturnsView`.
4. **POS** — `PosView` (vue dense, prévoir un namespace `pos.*`).
5. **Import/Export**, **Marketplace**, **Settings**, **Onboarding**, **Billing**.
6. **Admin secondaire** — `AdminDashboardView`, `AuditLogView`, `ModuleListView`.
7. **Customers** — dès que le verrou de session concurrente est levé.

## 🤖 Garde automatisée (proposée)

Cible : `npm run i18n:check` (et job CI) qui échoue si —
- **(a)** des clés existent dans `fr` mais pas `en` (ou inversement) — parité ;
- **(b)** une vue `*.vue` contient du **texte FR en dur** hors `$t`/`t` (heuristique : nœuds texte
  de template avec lettres accentuées ou mots FR, hors liste d'exceptions ci-dessus).

Tant que la garde n'existe pas, ce tracker + la Definition of Done tiennent lieu de contrôle manuel.
