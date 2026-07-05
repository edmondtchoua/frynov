# Module Comptabilité SYSCOHADA — Architecture & Plan d'implémentation

> **Statut : PLAN D'ARCHITECTURE (aucun code encore).** Rédigé après audit de l'existant (rc.153).
> Cible : le cœur comptable long terme de chaque tenant — opérations quotidiennes, pièces,
> écritures, journaux, livres, clôtures, états et conformité **SYSCOHADA révisé (OHADA)**.

---

## 0. Principes non négociables

1. **Ne rien casser** : le module est additif (`app/Modules/Accounting`), gated par `tenant_modules`
   (`module:accounting`) et par plan. Les modules existants (Orders, Payments, Pos, Inventory,
   Billing SaaS) ne changent pas de comportement ; ils **émettent des événements** que la compta consomme.
2. **Multi-tenant partout** : `tenant_id` + trait `HasTenant` (TenantScope fail-closed) sur toutes
   les tables ; validation d'appartenance sur toute référence croisée (leçon P-5).
3. **Partie double stricte** : une écriture (`accounting_entries`) n'est valide que si
   Σ débits = Σ crédits de ses lignes — contrainte vérifiée en service ET par invariant SQL de recette.
4. **Immutabilité comptable** : une écriture **comptabilisée** (`posted`) n'est jamais modifiée ni
   supprimée ; toute correction passe par **extourne** (contre-écriture liée). Soft delete réservé
   aux brouillons. Période clôturée = aucune écriture (réutilise le pattern `PeriodLockService`).
5. **Traçabilité de bout en bout** : chaque écriture automatique porte `source_type`/`source_id`
   (Order, Payment, OrderReturn, CashMovement, Invoice, CreditNote, StockMovement…) + l'id de la
   **règle d'imputation** appliquée et un **snapshot des inputs** → tout calcul est rejouable et
   explicable. Chaque transition d'état → `audit_logs` (user, date, old/new).
6. **Montants** : entiers en unités mineures (convention projet), devise portée par ligne ; un avoir
   ne franchit jamais une devise (règle existante RC-6G, reprise).
7. **Transactionnel** : génération d'écriture + liens source dans une seule transaction DB ; en cas
   d'échec de génération automatique, la transaction MÉTIER n'est pas bloquée : l'événement part en
   **outbox comptable** (`accounting_outbox`) rejouable (pattern NotificationOutbox existant).

---

## 1. Découpage en sous-domaines (bounded contexts)

```
app/Modules/Accounting/
├── Referential/    plan comptable, classes, journaux, taxes, paramètres tenant
├── Entries/        écritures, lignes, validation, extourne, lettrage
├── Invoicing/      devis → proforma → facture / acompte / avoir / récurrence
├── Ledger/         grand livre, balances, livres de caisse/banque (lectures + snapshots)
├── Periods/        exercices, périodes, clôture, report à nouveau
├── Engine/         moteur d'imputation (règles + templates + outbox)
└── Statements/     bilan, compte de résultat, états SYSCOHADA, exports
```

Chaque sous-domaine = Models + Services + Http (Controllers/Requests/Resources) + Tests + routes,
comme les modules existants (ModuleServiceProvider).

---

## 2. Modèle de données

Toutes les tables portent : `id uuid`, `tenant_id uuid (index)`, `created_by/updated_by uuid`,
`status`, `timestamps` ; celles qui reçoivent une validation : `validated_by`, `validated_at`.

### 2.1 Référentiel

| Table | Rôle | Colonnes clés |
|---|---|---|
| `accounting_account_classes` | Classes SYSCOHADA 1–9 (seed global, non tenant) | `code (1..9)`, `name`, `type (bilan/gestion)` |
| `accounting_accounts` | Plan comptable **par tenant** (seedé depuis le référentiel SYSCOHADA, personnalisable) | `class_code`, `code (ex: 411, 4111…)`, `name`, `parent_id`, `kind (asset/liability/equity/revenue/expense)`, `is_auxiliary`, `auxiliary_type (customer/supplier/null)`, `is_active`, `is_system` (comptes requis non supprimables) |
| `accounting_journals` | Journaux par tenant | `code (VT, AC, CA, BQ, OD, ST, AV, RG)`, `name`, `type (sales/purchases/cash/bank/misc/stock/credit_notes/adjustments)`, `sequence_prefix`, `is_system` |
| `accounting_taxes` | Taxes par tenant | `code (TVA18…)`, `name`, `rate_bp` (basis points — 1800 = 18 %), `collected_account_id`, `deductible_account_id`, `is_inclusive`, `country`, `is_active` |
| `tenant_accounting_settings` | Paramétrage 1-ligne/tenant | `country`, `currency`, `fiscal_year_start_month`, `numbering_rules json`, `default_accounts json` (caisse, banque, MM, clients, fournisseurs, ventes, achats, écarts, attente…), `rounding_account_id`, `locked_until date` |

Comptes auxiliaires clients/fournisseurs : un compte collectif (411/401) + auxiliaires générés
par tiers (`4110001…`) via `SequenceService` — liés à `customers.id`/`suppliers.id`
(`auxiliary_of_type`, `auxiliary_of_id`).

### 2.2 Exercices & périodes

| Table | Colonnes clés |
|---|---|
| `accounting_fiscal_years` | `label (2026)`, `starts_on`, `ends_on`, `status (open/closing/closed)`, `closed_by/at`, `carry_forward_entry_id` (RAN) |
| `accounting_periods` | `fiscal_year_id`, `starts_on`, `ends_on`, `status (open/locked/closed)`, `locked_by/at` — **réouverture contrôlée** : permission dédiée + audit |

> L'existant `fiscal_periods` (module Inventory, verrous de mouvements de stock) reste séparé
> à court terme ; un pont optionnel synchronisera les verrous (P4) pour éviter les migrations risquées.

### 2.3 Écritures

| Table | Colonnes clés |
|---|---|
| `accounting_entries` | `journal_id`, `period_id`, `fiscal_year_id`, `number` (séquence par journal+exercice), `entry_date`, `label`, `currency`, `status (draft/posted/reversed)`, `source_type`, `source_id`, `rule_id` (règle d'imputation), `inputs_snapshot json` (rejouabilité), `reversal_of_id`/`reversed_by_id` (extourne), `document_id` (pièce), `posted_by/at` |
| `accounting_entry_lines` | `entry_id`, `account_id`, `auxiliary_account_id?`, `label`, `debit_minor`, `credit_minor` (exactement un des deux > 0), `tax_id?`, `reconciliation_id?` (lettrage), `third_party_type/id?` |
| `accounting_reconciliations` | Lettrage : `code (AAA, AAB…)`, `account_id`, `status (partial/balanced)`, `balanced_at` |
| `accounting_documents` | Pièces justificatives : `kind`, `reference`, `file_path` (disque **local** privé, pattern payment-proofs), `original_filename`, `linked via morph` |
| `accounting_outbox` | Événements métier à comptabiliser : `event_type`, `source_type/id`, `payload json`, `status (pending/processed/failed/skipped)`, `attempts`, `last_error`, `entry_id?` — idempotence : unique (`tenant_id`,`event_type`,`source_type`,`source_id`) |
| `accounting_ledger_snapshots` | Soldes matérialisés par (compte, période) pour balances rapides : `account_id`, `period_id`, `debit_minor`, `credit_minor`, `balance_minor` — recalculables (jamais source de vérité) |

### 2.4 Facturation tenant (nouvelle — distincte du billing SaaS)

| Table | Colonnes clés |
|---|---|
| `invoices` | `number (FA-…)`, `kind (invoice/deposit/final/recurring)`, `customer_id`, `order_id?`, `proforma_id?`, `currency`, `issue_date`, `due_date`, `status (draft/issued/partially_paid/paid/overdue/cancelled)`, `subtotal_minor`, `tax_total_minor`, `total_minor`, `paid_minor`, `schedule json?` (échéancier), `recurrence json?`, `entry_id?` |
| `invoice_lines` | `product_id?`, `label`, `qty`, `unit_price_minor`, `discount_bp`, `tax_id?`, `tax_minor`, `total_minor`, `revenue_account_id?` (surcharge) |
| `proformas` | `number (PF-…)`, `customer_id`, `status (draft/sent/approved/expired/converted/partially_converted/cancelled)`, `valid_until`, `deposit_bp?`, `converted_invoice_ids json`, mêmes montants ; `approval json?` (workflow) |
| `proforma_lines` | idem invoice_lines + `converted_qty` (conversion partielle) |
| `credit_notes` / `credit_note_lines` | `number (AV-…)`, `invoice_id?`, `return_id?` (RMA), `reason`, montants, `entry_id?` |
| `payment_allocations` | **N↔N paiement/facture** : `payment_id`, `invoice_id`, `amount_minor` — paiement partiel, multiple, trop-perçu → avoir client (réutilise le concept ledger RC-6G) |

> `payments` existants (commandes) restent tels quels ; `payment_allocations` les relie aux factures
> quand la facturation est activée. Une vente POS reste facturable a posteriori (facture depuis commande).

---

## 3. Moteur d'imputation (Engine)

**Règles configurables** (`accounting_rules`) : `event_type`, `conditions json`
(pays, moyen de paiement, catégorie produit, type client…), `template_id`, `priority`, `is_active`.

**Templates** (`accounting_templates`) : liste de lignes paramétriques
`[{side: debit|credit, account_ref: '@cash'|'@sales'|'@tax_collected'|'570'|'@payment_method',
amount_expr: 'total'|'ht'|'tax'|'cash_portion'…}]` — les `@refs` se résolvent via
`tenant_accounting_settings.default_accounts` puis les surcharges par catégorie/moyen de paiement.

**Flux** : événement métier (déjà émis ou à émettre : `OrderFulfilled`, `PaymentRecorded`,
`PosSaleCompleted`, `CashMovementRecorded`, `OrderReturnRestocked`, `InvoiceIssued`,
`CreditNoteIssued`, `StockAdjusted`…) → listener léger → **`accounting_outbox`** →
worker `accounting:process-outbox` (posté par lot, transactionnel, idempotent par contrainte
unique) → écriture `draft` ou `posted` selon la politique du tenant (`auto_post` bool).

**Règles seedées par défaut (XOF/XAF, TVA optionnelle)** — exemples SYSCOHADA :

| Événement | Débit | Crédit |
|---|---|---|
| Vente POS cash | 571 Caisse | 701 Ventes (+ 4431 TVA collectée) |
| Vente POS mobile money / carte | 52x/585 (attente MM) | 701 (+ 4431) |
| Vente POS split | 571 + 52x (par leg, `cash_portion`/`leg_amount`) | 701 (+ 4431) |
| Facture client émise (non payée) | 411 Client (auxiliaire) | 701 (+ 4431) |
| Paiement client | 571/52x | 411 auxiliaire |
| Avoir / retour RMA | 701 (+ 4431 à régulariser) | 411 ou 571 (remboursement caisse RC-16) |
| Achat fournisseur | 60x/3x (+ 4452 TVA déductible) | 401 auxiliaire |
| Paiement fournisseur | 401 | 571/52x |
| Écart de caisse (clôture, difference_cents ≠ 0) | 658 ou 758 | 571 (ou inverse) |
| Sortie de caisse (dépense, CashMovement out/expense) | 6xx | 571 |

Chaque écriture générée garde `rule_id` + `inputs_snapshot` → **rejouable** (`accounting:replay
--entry=` recalcule depuis la source et compare).

---

## 4. Livres & états (Ledger / Statements)

- **Livre-journal** : écritures par journal/période, export PDF/CSV/XLSX.
- **Grand livre** : par compte (et auxiliaire), solde progressif, filtre période/tiers ; pagination serveur.
- **Balance générale & auxiliaire** : depuis `accounting_ledger_snapshots` (recalcul à la demande),
  colonnes SYSCOHADA (débit/crédit mouvements + soldes), comparatif N/N-1.
- **Livres de caisse/banque** : vues du grand livre filtrées 57x/52x + rapprochement bancaire
  (`accounting_reconciliations` type bank : import relevé CSV → matching manuel P4).
- **États financiers SYSCOHADA (P4)** : Bilan (actif/passif), Compte de résultat, TAFIRE/tableau des
  flux — pilotés par un **mapping états↔comptes** seedé (référentiel SYSCOHADA révisé), exportables.
- **Exports** : réutilise `ExcelExporter`/`PdfExporter` du module ImportExport (audité : générique) ;
  permission dédiée `accounting.export`.

---

## 5. Clôtures (Periods)

1. **Verrouillage période** (`locked`) : plus d'écriture datée dedans (message clair 422) ; le
   moteur route automatiquement une pièce tardive vers la première période ouverte avec mention.
2. **Clôture d'exercice** : contrôles (aucun draft, équilibre, lettrage caisses), génération du
   **report à nouveau** (RAN : solde des comptes de gestion → 13x résultat, soldes bilan → à nouveau),
   statut `closed`, exercice suivant ouvert avec soldes d'ouverture.
3. **Réouverture contrôlée** : permission `accounting.periods.reopen` (chef comptable/admin),
   motif obligatoire, audit.

---

## 6. Sécurité & conformité

- **Nouveaux rôles** (RolesAndPermissionsSeeder, scopés tenant/teams) : `accountant` (saisie),
  `chief-accountant` (validation/clôture), `accounting-viewer` (lecture), `auditor` (lecture + audit
  trail, aucune écriture). Rôles existants réutilisés : admin tenant (tout), cashier/manager (aucun
  accès compta sauf leurs propres tickets).
- **Permissions fines** : `accounting.entries.create|post|reverse`, `accounting.periods.close|reopen`,
  `accounting.invoices.*`, `accounting.settings.manage`, `accounting.export`, `accounting.audit.view`.
- **Gating** : middleware `module:accounting` + quota plan (`quota:accounting_entries` si besoin) —
  un tenant sans le module n'a ni routes ni menu (pattern existant fail-closed).
- **IDOR** : toutes les références croisées validées au tenant (leçon P-5) ; tests d'isolation
  systématiques (pattern PosReceiptTest).
- **Interdictions structurelles** : update/delete refusés en service sur `posted` ; migrations sans
  suppression physique ; extourne = seule voie de correction ; période fermée → 422.

---

## 7. UX/UI (frontend/src/modules/accounting)

Menu **Comptabilité** (visible si module actif + rôle) :
Tableau de bord (CA, encaissements, échéances, anomalies) · Factures · Proformas · Paiements &
lettrage · Journaux · Écritures · Grand livre · Balance · Rapprochement · Exercices & périodes ·
Plan comptable · Taxes · Paramètres · Rapports/Exports · Audit trail.

Standards d'écran (conventions projet) : filtres avancés (période, journal, compte, tiers, statut),
recherche, pagination serveur `{data, meta}` normalisée (leçon C-3), erreurs affichées (leçon C-5),
i18n **FR+EN dès la création** (DoD), badges de statut, actions conditionnées au rôle, indicateurs
d'anomalies (écriture déséquilibrée — impossible par construction mais contrôlée —, pièce manquante,
période sur le point de fermer, factures échues).

---

## 8. Tests & critères d'acceptation (extraits)

- Écriture : équilibre imposé (422 sinon), post → immuable, extourne liée, période fermée refusée.
- Moteur : chaque règle seedée testée sur cas réel (vente POS cash/split/MM, facture, paiement
  partiel via allocations, avoir/RMA, écart de clôture) — l'écriture générée est équilibrée,
  correctement imputée, tracée (`source_*`, `rule_id`, snapshot) et **idempotente** (rejeu outbox
  = 0 doublon).
- Facturation : proforma → conversion (totale/partielle) → facture → paiements partiels/multiples →
  statuts ; acompte ; avoir lié à un RMA existant.
- Isolation tenant + RBAC sur chaque endpoint ; module désactivé → 403/404.
- Exports PDF/XLSX/CSV ; audit trail consultable.

---

## 9. Roadmap

| Phase | Contenu | Incréments estimés |
|---|---|---|
| **P0 (fait / en cours)** | POS branché E2E (validé rc.148–153) + idempotence checkout offline + statuts de sync + lien vente→compta (`source_*` prêts) | RC-22 |
| **P1** | Référentiel : migrations + seed SYSCOHADA (classes, plan par défaut, journaux, taxes), paramètres tenant, écrans Plan comptable/Taxes/Paramètres, rôles & permissions, module gating | RC-23/24 |
| **P2** | Écritures manuelles (saisie équilibrée, brouillon/post/extourne) + moteur d'imputation + outbox + règles ventes POS/paiements/retours/écarts de caisse | RC-25/26 |
| **P3** | Facturation : factures + proformas + avoirs + allocations de paiement + PDF + conversion + génération auto d'écritures | RC-30/31 |
| **P4** | Grand livre, balances, livres caisse/banque, lettrage, rapprochement bancaire, snapshots, périodes/clôture/RAN | RC-29/30 |
| **P5** | États financiers SYSCOHADA (bilan, résultat, TAFIRE), comparatifs N/N-1, reporting, consolidation multi-sites, analytics | RC-31+ |

Chaque incrément : backend + frontend en parallèle, tests, i18n FR/EN, docs tech + utilisateur,
gate complet avant merge (process en vigueur).
