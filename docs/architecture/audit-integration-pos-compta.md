# Rapport d'audit — Intégration POS & précurseurs comptabilité

> Audit rc.153 (2026-07-05) : 2 revues parallèles (intégration POS, précurseurs compta) +
> **preuves E2E de la même session** (preview branché sur la base MySQL locale `fry_nov_db`).
> Objet : répondre à « le POS desktop/mobile est-il réellement branché ? » et préparer le
> module comptable SYSCOHADA ([plan d'architecture](comptabilite-syscohada.md)).

---

## 1. Verdict : le POS est RÉELLEMENT branché (preuves E2E)

Les « POS desktop » et « POS mobile » ne sont pas des applications séparées : ce sont des vues du
SPA Vue (`/pos/desktop`, `/pos/mobile`, + `/pos` tablette historique) partageant le composable
`usePosSession` et `posService`, qui appellent la vraie API (`/api/pos/*`, module
`backend/app/Modules/Pos`). Validé de bout en bout ce jour, données réelles :

| Flux | Preuve |
|---|---|
| Login → dashboard | `POST /api/auth/login` 200 + `/api/auth/me` (compte démo TechZone CI) |
| Vente **paiement mixte** (10 000 esp. + 6 000 MM) | `POST …/checkout` 201, attendu caisse 50 000 → **60 000** (seule la part espèces entre au tiroir) |
| Ticket de caisse | `GET /api/pos/orders/{id}/receipt` 200 — rendu 80 mm avec les 2 legs |
| Mouvement de caisse (sortie 5 000) | 201, attendu 60 000 → **55 000** |
| POS mobile | même session partagée (55 000), indicateur réseau, file offline |
| Retour comptoir / RMA | cycle créer → approuver → restocker : stock 198 → 199, audit `return.approved` écrit |
| Historique / clôture / RBAC / isolation tenant | 20 tests d'intégration Pos* (sessions, split, mouvements, refund, receipt, viewer 403, cross-tenant 404) |

> Note historique : les échecs « 423/404 » qui faisaient croire à un POS non branché venaient d'un
> serveur d'un AUTRE projet qui squattait le port 8000 (consigné au backlog rc.152).

## 2. Trous restants pour une exploitation production (audit POS)

| # | Gravité | Constat | Détail |
|---|---|---|---|
| A-1 | **CRITIQUE** | Idempotence checkout POS absente côté serveur | Le front envoie désormais `X-Idempotency-Key` (stable entre retries offline) mais `PosController::checkout` l'ignorait → rejeu réseau = **double vente**. `PaymentController` l'implémente déjà (modèle à répliquer). → **RC-22 (P0)** |
| A-2 | ÉLEVÉE | **Taxes inexistantes** | Aucune table de taxes, aucun calcul TVA (Order/OrderLine sans montants de taxe exploités, `OrderService::create` = Σ qty×PU). Bloquant pour la facturation SYSCOHADA → traité par le module compta **P1 (`accounting_taxes`)** + injection au checkout (P2). |
| A-3 | ÉLEVÉE | Session front perdue au reload | Token volontairement en mémoire (anti-XSS, documenté) mais **pas de refresh token / cookie HttpOnly** : F5 en pleine session de caisse → reconnexion. → P1 (stratégie refresh). |
| A-4 | MOYENNE | Pas de sélecteur client au POS | Backend OK (`customer_id` validé tenant), l'UI ne l'expose pas. |
| A-5 | MOYENNE | Moyens de paiement en dur | Enum global (cash/MM/carte/virement/chèque), pas de paramétrage tenant/marché pour le POS (le catalogue par marché n'existe que côté billing SaaS). |
| A-6 | MOYENNE | Pas de sélecteur entrepôt/caisse à l'ouverture | Backend OK (`warehouse_id`+`label`), l'UI d'ouverture ne les propose pas (multi-boutique aveugle). |
| A-7 | BASSE | Remises absentes au POS | Ni `discount` au checkout, ni champ commande. |
| A-8 | BASSE | RBAC par rôles, pas par permissions | `pos.open/sale/refund/close` sont seedées mais `PosController::guard` vérifie `hasAnyRole(admin,manager,cashier)` — un rôle custom doté de `pos.sale` serait refusé. |
| A-9 | OK | Journalisation POS complète | opened/sale/cash_movement/refund/closed → `audit_logs` immuables. |
| A-10 | OK | Pas de code mort/mocké dans le module POS | — |

## 3. Précurseurs comptabilité (audit backend/front)

| Brique | Statut | Notes |
|---|---|---|
| Factures / proformas / devis / avoirs tenant | **ABSENT** | Aucune table invoice/proforma/credit_note ; seul le billing SaaS (ManualPayment) existe ; le ticket POS n'est pas une facture. → Module compta P3 |
| Taxes | **ABSENT** | Aucune colonne tax_* dans orders/order_lines/products, aucun calcul (cf. A-2) |
| Paiements commandes | EXISTE | `payments` (uuid, tenant, centimes, devise, méthode, warehouse, **idempotency_key unique (tenant, key)**, soft delete) — base saine ; pas d'allocations N↔N → P3 |
| Périodes | EXISTE (inventaire) | `fiscal_periods` : statuts open→review→locked (irréversible), **integrity_hash HMAC-SHA256 chaîné**, **trigger MySQL** bloquant les stock_movements en période verrouillée, `PeriodLockService::assertOperationAllowed`. Pattern d'excellence à répliquer pour `accounting_periods` (P1) |
| Audit trail | EXISTE | `audit_logs` **immuables par construction** (hooks anti update/delete) + **chaînage d'intégrité** (HMAC de l'entrée précédente, genesis). Manque pour SYSCOHADA strict : `reason_code` et référence de pièce — portés par `accounting_entries` (source_*, document_id) plutôt que par l'audit log |
| Numérotation | EXISTE | `SequenceService` + `sku_sequences` (FOR UPDATE, seed de continuité) — réutilisé pour FA-/PF-/AV-/numéros d'écritures par journal |
| Multi-devise | PARTIEL | Devise du tenant (settings, XOF défaut), centimes partout ; **aucune conversion** (hypothèse V1 compta : une devise par tenant) |
| Gating modules/plan | EXISTE | `erp_modules` (catégorie `finance` existante) + `tenant_modules` + `ModuleRegistryService::activatePlanModules` + middleware `module:` fail-closed → ajouter la ligne `accounting` au seeder + rattacher aux plans + navItem `module: 'accounting'` dans AppLayout (`_allNavItems`) |
| Rôles/permissions | EXISTE | 9 rôles seedés (pattern `{module}.{action}`) ; les rôles/permissions comptables (`accountant`, `chief-accountant`, `auditor`, `accounting-viewer`, `accounting.*`) sont à créer (P1) |
| Pièces jointes privées | PARTIEL | Pattern payment-proofs (disque privé + URL signée) éprouvé mais **ad hoc** → table générique `accounting_documents` morphable (P1/P3) |
| Exports | EXISTE | `ExcelExporter` (PhpSpreadsheet) + `PdfExporter` (DomPDF, HTML inline) génériques → journaux/balances (XLSX) et factures/relevés (PDF) |

**Conclusion d'audit** : ~70 % de l'infrastructure socle du module comptable existe déjà
(périodes verrouillables avec intégrité, audit immuable chaîné, numérotation anti-course,
idempotence, gating modules/plans, exports, pièces privées, montants centimes). Les 30 % à
construire sont le cœur métier : référentiel SYSCOHADA, écritures, moteur d'imputation,
facturation/proformas/avoirs/allocations et TVA.

## 4. Priorisation (roadmap consolidée)

- **P0 — RC-22 (immédiat)** : idempotence checkout POS serveur (A-1) + statuts de synchronisation +
  test de rejeu. Le front est déjà prêt (clé stable par vente).
- **P1 — RC-23/24** : référentiel compta (plan SYSCOHADA seedé, journaux, taxes A-2, paramètres
  tenant, rôles compta, gating) + refresh token (A-3) + sélecteurs client/entrepôt au POS (A-4/A-6).
- **P2 — RC-25/26** : écritures manuelles + moteur d'imputation + outbox (ventes POS, paiements,
  retours, écarts de clôture) ; permissions granulaires POS (A-8) ; moyens de paiement par tenant (A-5).
- **P3 — RC-30/31** : facturation/proforma/avoirs/allocations + PDF + écritures auto + remises (A-7).
- **P4 — RC-29/30** : livres, balances, lettrage, rapprochement, clôtures/RAN.
- **P5 — RC-31+** : états SYSCOHADA, comparatifs, consolidation, analytics.

Détail complet du module comptable : [comptabilite-syscohada.md](comptabilite-syscohada.md).
