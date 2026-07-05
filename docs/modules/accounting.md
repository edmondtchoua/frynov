# Module Comptabilité (SYSCOHADA) — Documentation technique

> **RC-23 → RC-30** — cette page couvre le **référentiel** (P1), les **écritures & moteur
> d'imputation** (P2) et la **facturation client** (P3). Les livres/états (P4-P5) arrivent dans les
> incréments suivants — architecture complète :
> [docs/architecture/comptabilite-syscohada.md](../architecture/comptabilite-syscohada.md).

## Vue d'ensemble

Module additif `app/Modules/Accounting`, gated par `tenant_modules` (`module:accounting`, seedé dans
`ErpModulesSeeder`, associé aux plans via `PlanModulesSeeder`). Toutes les tables tenant portent
`tenant_id` (trait `HasTenant`, TenantScope fail-closed).

## Modèles & tables

| Modèle | Table | Rôle |
|---|---|---|
| `AccountClass` | `accounting_account_classes` | Classes SYSCOHADA 1–9 — référentiel **global** (seed `AccountingClassesSeeder`) |
| `Account` | `accounting_accounts` | Plan de comptes du tenant — `code` unique/tenant, `class_code` dérivé du 1er chiffre, `kind` (asset/liability/equity/revenue/expense), `is_system` (requis moteur, indésactivable), comptes auxiliaires (`auxiliary_type/of_id`) |
| `Journal` | `accounting_journals` | VT, AC, CA, BQ, OD, ST, AV, RG — `sequence_prefix` pour la numérotation d'écritures (SequenceService) |
| `Tax` | `accounting_taxes` | Taux en **points de base** (1800 = 18 %), comptes collecté/déductible, `amountFor()` arrondi demi-supérieur entier |
| `AccountingSettings` | `tenant_accounting_settings` | 1 ligne/tenant : devise, `default_accounts` (références symboliques `@cash`→`571`… du moteur d'imputation), `auto_post` |
| `FiscalYear` / `AccountingPeriod` | `accounting_fiscal_years` / `accounting_periods` | Exercice + 12 périodes mensuelles, statuts open/locked/closed. Dates **pures** sérialisées `Y-m-d` |

## Provisionnement — `ChartOfAccountsProvisioner`

`provision(Tenant, ?userId)` — **idempotent** (updateOrCreate partout) :
38 comptes SYSCOHADA (dont les comptes système du moteur : 571, 521, 585, 411, 419, 401, 701, 706,
601, 603, 4431, 4452, 471, 658, 758, 31, 11, 13), 8 journaux, TVA locale selon le pays du tenant
(`defaultVatRateBp` : UEMOA 18 %, CEMAC 19,25 %, NE 19 %, CD 16 %…), exercice courant + 12 périodes,
paramètres avec mapping des comptes par défaut. Audit `accounting.provisioned`.

## Endpoints

Préfixe `/api/accounting` · middlewares : `auth:sanctum` + `EnsureUserBelongsToTenant` + `module:accounting`.

| Méthode | URL | RBAC | Description |
|---|---|---|---|
| GET | `overview` | lecture¹ | provisionné ? + compteurs + classes |
| POST | `provision` | gestion² | initialise le référentiel (idempotent) |
| GET/POST/PUT | `accounts[/{id}]` | lecture / gestion | plan de comptes (recherche, filtre classe, pagination) ; code immuable, compte système indésactivable (422) |
| GET/PUT | `journals[/{id}]` | lecture / gestion | journaux (code & type immuables) |
| GET/POST/PUT | `taxes[/{id}]` | lecture / gestion | taxes |
| GET/PUT | `settings` | lecture / gestion | paramètres (audit sur update) |
| GET | `fiscal-years` | lecture | exercices + périodes |
| POST | `periods/{id}/lock` | gestion | verrouille (audit `accounting.period.locked`) |
| POST | `periods/{id}/unlock` | **admin \| accounting.periods.reopen** | réouverture contrôlée, motif obligatoire, audité |

¹ `accountant|chief-accountant|accounting-viewer|auditor|admin|manager|accounting.view`
² `chief-accountant|admin|accounting.manage`

## Rôles (RolesAndPermissionsSeeder)

`accountant` (saisie + lecture + export) · `chief-accountant` (gestion référentiel, post/extourne,
clôture) · `accounting-viewer` (lecture seule) · `auditor` (lecture + audit trail, aucune écriture).
La **réouverture de période** est une permission dédiée (`accounting.periods.reopen`) non accordée
au chef comptable par défaut (séparation des pouvoirs).

## Frontend

`frontend/src/modules/accounting` — routes `/accounting/{chart|taxes|periods|settings}` (entrée menu
**Comptabilité**, `module: 'accounting'`). Vues : ChartOfAccountsView (assistant de provisionnement
+ plan filtrable + création de compte), TaxesView, PeriodsView (verrouillage/réouverture avec motif),
AccountingSettingsView (auto-post + comptes par défaut). i18n FR/EN (`accounting.*`).

## Écritures & moteur d'imputation (RC-25/26 — P2)

### Écritures — `EntryService`
Tables `accounting_entries` / `accounting_entry_lines`. Cycle : **draft → post → reversed**.
Garde-fous imposés (service + tests) :
- **Équilibre** : Σ débits = Σ crédits, ≥ 2 lignes, chaque ligne un seul côté > 0 (sinon 422).
- **Période** : l'`entry_date` doit tomber dans une période OUVERTE (verrouillée/close → 422).
- **Numérotation** : au POST, n° séquentiel par journal (`SequenceService`, préfixe `VT26…`) +
  rattachement période/exercice ; audit `accounting.entry.posted`.
- **Immutabilité** : une écriture `posted` ne se modifie ni se supprime → **extourne** (`reverse`)
  = contre-écriture (côtés inversés) postée, liée dans les deux sens (`reversal_of_id`/`reversed_by_id`),
  source marquée `reversed`. Double extourne interdite. Audit `accounting.entry.reversed`.
- Traçabilité : `source_type`/`source_id`, `rule_code`, `inputs_snapshot` (rejouable/explicable).

Endpoints : `GET entries[/{id}]` (lecture), `POST entries` (create, `accounting.entries.create`),
`POST entries/{id}/post` (`accounting.entries.post`), `POST entries/{id}/reverse` (`accounting.entries.reverse`).

### Moteur d'imputation — `ImputationEngine` + outbox
`accounting_outbox` : un événement métier = une ligne **unique par (tenant, event_type, source)**
→ le rejeu (retry, resync offline) ne produit **jamais** deux écritures. Le module Comptabilité
**écoute** les événements POS (`PosAccountingSubscriber`, dépendance à sens unique Accounting→Pos ;
le POS ignore la comptabilité). `record()` est best-effort et ne bloque jamais le flux métier ;
`accounting:process-outbox` (planifié /5 min) rejoue les `pending`.

Résolution des comptes : références symboliques `@cash`→571… via `settings.default_accounts`.
Écriture `draft` ou `posted` selon `auto_post`. Règles seedées :

| Événement (source) | Journal | Écriture |
|---|---|---|
| `pos.sale` (Order) | VT | débit trésorerie par leg (571/585/521), crédit 701 |
| `pos.refund` (OrderReturn) | AV | débit 701, crédit trésorerie |
| `pos.session_gap` (CashRegisterSession) | CA | manquant : 658/571 · surplus : 571/758 |
| `cash.movement` (CashMovement) | CA | float_add 571/521 · withdrawal 521/571 · expense 471/571 (refund ignoré : porté par pos.refund) |
| `payment.recorded` (Payment) | CA/BQ | débit trésorerie, crédit 411 |

> L'idempotence `orders.pos_reference` (RC-22) garantit une commande unique par vente ; combinée à
> l'unicité outbox, **une vente POS ⇒ exactement une écriture**, même en resync offline.

### Frontend
`EntriesView` (`/accounting/entries`) : liste (n°, date, journal, libellé, montant, **origine**
= règle auto ou « Manuelle », statut), création manuelle (pavé de lignes avec **contrôle d'équilibre
en direct**), comptabilisation, extourne. i18n FR/EN.

## Facturation client (RC-30 — P3)

Tables `invoices` / `invoice_lines` / `payment_allocations`. `InvoiceService` :
- **Brouillon** : lignes avec **TVA calculée serveur-side** (HT après remise en points de base →
  `Tax::amountFor`), totaux HT/TVA/TTC recomposés. Modifiable librement ; création possible **depuis
  une commande** (`fromOrder`).
- **Émission** : numéro `FA-` séquentiel + **écriture d'émission** via le moteur d'imputation
  (`invoice.issued` → débit **411** client TTC / crédit **701** HT + **4431** TVA). La facture devient
  immuable.
- **Allocation de paiement** (N↔N) : un paiement alloué à une (ou plusieurs) facture(s), borné au
  reste dû ET au disponible du paiement ; met à jour `paid_minor` + statut
  (issued → partially_paid → paid) ; **écriture d'encaissement** (`payment.allocated` → débit
  trésorerie / crédit 411).
- **PDF** : `InvoicePdfRenderer` (DomPDF, réutilise le pattern ImportExport) — `GET …/invoices/{id}/pdf`.

Endpoints : `GET invoices[/{id}][/pdf]` (lecture) · `POST invoices`, `invoices/from-order/{orderId}`,
`invoices/{id}/issue`, `invoices/{id}/payments` (saisie, `accounting.entries.create`).
Frontend : `InvoicesView` (`/accounting/invoices`) — liste, création à totaux en direct, émission,
encaissement, lien PDF. i18n FR/EN.

## Tests

Backend : `AccountingReferentialTest` (8) · `AccountingEntryTest` (7 — équilibre, post/numéro,
période verrouillée, immutabilité, extourne miroir) · `AccountingImputationTest` (6 — vente split →
écriture équilibrée par tender, **idempotence du rejeu**, remboursement, écart de clôture, mouvement,
tenant sans module = 0 écriture) · `AccountingInvoiceTest` (7 — TVA/remise, émission → 411/701/4431,
allocation partielle/multiple bornée, encaissement, cycle HTTP, RBAC caissier). Front :
`ChartOfAccountsView.spec.ts` (3) + `InvoicesView.spec.ts` (3) + garde i18n.
