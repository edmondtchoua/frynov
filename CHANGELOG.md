# Changelog — Frynov ERP

Toutes les évolutions notables. Format inspiré de [Keep a Changelog](https://keepachangelog.com/),
versionnage [SemVer](https://semver.org/).

## [Non publié] — 🔐 RC-41 : Audit RBAC/plans — durcissement accès & quotas (2026-07-06)

Audit complet RBAC/ACL + plans (`docs/audit/rbac-plans-audit.md`) — architecture jugée saine (isolation
fail-closed, Spatie teams, self-service RBAC borné, anti-escalade). Correctifs P0/P1/P3/P4 appliqués :

- **P0 sécurité** : gardes de permission ajoutées sur 4 écritures non protégées (un rôle `viewer`
  pouvait écrire) — `PUT /customers/{id}`, `POST` + `PUT /suppliers`, `POST /deliveries`.
- **P1 quota** : `max_customers` désormais **appliqué** (`QuotaService::assertCanAddCustomer` +
  `quota:customers`), plus seulement affiché.
- **P3 traçabilité** : invitation utilisateur auditée (`workspace.user_created`) + transaction atomique.
- **P4 cohérence offre** : `features` reformulés (fin du tiering trompeur — tous modules inclus, volumes
  selon plan) ; plan `enterprise` renommé **« Enterprise »** (seeder + i18n). Re-seed requis.
- **P2 défense en profondeur** : Policies réutilisables (`ModulePolicy` + Supplier/Customer/Delivery,
  enregistrées dans `AppServiceProvider`) + `Gate::authorize()` sur les écritures (2ᵉ ligne après le
  middleware). Quota `max_imports_per_month` enforced (`quota:imports`). `max_branches`/`storage_mb`/
  `max_api_calls` documentés comme non applicables/différés.
- **P5 outillage** : garde-fou CI `RouteAccessGuardTest` (toute écriture de module doit être gardée) —
  a révélé **5 écritures non gardées supplémentaires**, toutes fermées (inventory adjustments, payments
  delete, import upload/mapping/cancel). Endpoint `GET /me/subscription/usage` (usage vs quota par ressource).
- **Tests** : `WriteEndpointGuardsTest`, `CustomerQuotaTest`, `ModulePolicyTest`, `ImportQuotaTest`,
  `RouteAccessGuardTest`, `UsageReportTest`. Suite feature **49/49**.

## [Non publié] — 🧩 RC-40 : Mise à niveau — taxes/frais, PSP auto, correction admin, analytics (2026-07-05)

- **Taxes & frais d'installation** : `plans.tax_rate_bps` + `setup_fee_minor` ; devis + net + demande
  intègrent taxe (sur brut après promo) et frais unique (changement de plan). Règlement routé vers la
  branche « net autoritatif » quand taxe/frais présents. Admin éditable. Test `TaxAndFeeTest`.
- **Paiement automatisé (PSP)** — désactivé par défaut : abstraction `PspGateway` + driver `FakePspGateway`
  (checkout factice + webhook HMAC). `POST me/subscription/psp/initiate` + `POST webhooks/psp` →
  activation automatique via l'approbation système. Prêt pour un vrai rail. Test `PspPaymentTest`.
- **Demande de correction (admin)** : `POST admin/manual-payments/{id}/request-correction` (non
  destructif) → demande en `pending_payment` + consigne + notification tenant. Bouton « Corriger ».
  Test `RequestCorrectionTest`.
- **Analytics (graphes)** : panneau « Statistiques » (barres CSS) sur l'écran admin des plans
  (revenu/adoption par plan + demandes par statut) via `GET admin/plans/analytics`.
- **Tests** : suite feature **42/42**. Taxes + analytics vérifiés en navigateur.

## [Non publié] — 🔗 RC-39 : Comptabilité — lettrage des comptes de tiers (P4.2) (2026-07-05)

Rapprochement des lignes d'un compte de tiers (411 clients, 401 fournisseurs) en groupes équilibrés.

- **Lettrage** (`LettrageService.letter`) : rapproche des lignes d'un **même compte** formant un groupe
  **soldé** (Σ débits = Σ crédits) sous un `lettrage_code` (A, B… par compte). Refuse un groupe
  déséquilibré ou < 2 lignes ; n'accepte que des lignes d'écritures comptabilisées/extournées non déjà
  lettrées. Le **non-lettré = le solde réellement ouvert** (factures non réglées, règlements non affectés).
- **Délettrage** (`unletter`) : rouvre les lignes d'un code. **Synthèse** par compte : lettré / ouvert
  / solde ouvert signé.
- **Front** : `LettrageView` (`/accounting/lettrage`) — sélection multi-lignes avec **contrôle
  d'équilibre en direct**, badge de code cliquable pour délettrer, filtre « non lettrées seulement ».
  i18n FR/EN.
- **Migration** : `accounting_entry_lines.lettrage_code` + `lettered_at`. Endpoints
  `GET reports/lettrage` (lecture), `POST reports/lettrage[/unletter]` (`accounting.entries.create`).
- **Tests** : `AccountingLettrageTest` (6) + `LettrageView.spec.ts` (2). Bug `only_open` (chaîne
  "false" rejetée par la règle `boolean`) **détecté en preview** et corrigé + couvert.

## [Non publié] — 📒 RC-38 : Comptabilité — balance générale & grand livre (P4.1) (2026-07-05)

Premiers **états de lecture** du module Comptabilité, calculés sur les écritures comptabilisées
(`LedgerService`, `posted` **et** `reversed` — une extourne reste un mouvement réel ; brouillons
ignorés). Montants signés (débit positif).

- **Balance générale** (`GET reports/trial-balance?from=&to=`) : par compte mouvementé — à-nouveau
  (net avant `from`), mouvements débit/crédit de la période, solde. Invariants garantis par la partie
  double : **Σ débits = Σ crédits** et **Σ soldes débiteurs = Σ soldes créditeurs**.
- **Grand livre** (`GET reports/general-ledger?account_id=&from=&to=`) : à-nouveau + lignes ordonnées
  (date, journal) avec **solde progressif**, mouvements et solde de clôture.
- **Front** : `BalanceView` (`/accounting/balance`) — balance filtrable par dates + **drill-down** vers
  le grand livre d'un compte. i18n FR/EN. Lecture ouverte au rôle `accounting-viewer` (caissier 403).
- **Tests** : `AccountingLedgerTest` (6) + `BalanceView.spec.ts` (2). Vérifié en preview sur données
  réelles (TechZone) : balance de 5 comptes **équilibrée** (débit = crédit = 1 136 300).

## [Non publié] — ✔️ RC-37 : Mise à niveau — écran admin de validation contextualisé (2026-07-05)

- La revue admin (`ManualPaymentView`) affiche le **contexte de la demande** liée à chaque paiement :
  `ManualPayment::toAdminArray()` ajoute un bloc `change_request` (plan source→cible, type, périodicité,
  durée, prise d'effet, net, **consentement recueilli**). Chargé sans N+1 (`with('changeRequest')`).
- Front : rappel « plan → plan · périodicité × durée · prise d'effet · ✓/⚠ consentement » sous le plan ;
  rétro-compatible (paiements legacy sans demande). i18n FR+EN.
- **Tests** : `AdminReviewContextTest`. Suite feature **37/37**. Vérifié en navigateur (super-admin).

## [Non publié] — 🔔 RC-36 : Mise à niveau — notifications in-app (P2b) (2026-07-05)

- **Fil in-app d'abonnement** (`subscription_notifications` + `SubscriptionNotification`) : le
  `SubscriptionNotifier` dépose une notification in-app à chaque événement (submitted/activated/rejected),
  en plus de l'e-mail. Endpoints `GET /api/me/subscription/notifications` + `POST .../{id}/read`.
- **Cloche unifiée** : `useNotifications` fusionne alertes marketplace + fil abonnement (tri par date,
  mark-as-read routé par `source`) ; `NotificationCenter` étiquette « Abonnement ».
- **Tests** : `InAppNotificationTest` (2). Suite feature **36/36**. Vérifié en navigateur.

## [Non publié] — 🗂️ RC-35 : Mise à niveau — back-office des plans (P5) (2026-07-05)

- **Cycle de vie éditorial** des plans : colonnes `status` (active/draft/archived) + `badge`,
  `Plan::scopeSelectable()`. **Sécurité (Phase 13)** : les points de sélection tenant + le pricing
  public passent par `selectable()` → un plan brouillon/archivé n'est jamais chiffrable (404).
- **Endpoints admin** : `POST /api/admin/plans` (créer, brouillon + prix `global` seedés),
  `DELETE .../{plan}` (archiver, non destructif), `PATCH` (+status/badge),
  `GET .../plans/analytics` (adoption + revenu par plan + demandes par statut).
- **Front admin** (`PlanListView`) : badges statut/affichage sur les cartes, sélecteur statut+badge,
  bouton Archiver, modale Créer un plan. i18n FR+EN.
- **Tests** : `AdminPlanManagementTest` (4). Suite feature **34/34**. Vérifié en navigateur (super-admin).

## [Non publié] — 🧭 RC-34 : Mise à niveau — assistant multi-étapes + upgrade différé (P4) (2026-07-05)

- **Assistant en 5 étapes** dans le drawer (Plan → Périodicité/durée → Paiement → Résumé/consentement →
  Confirmation) : stepper, navigation Retour/Suivant validée par étape, récap + référence de demande.
  i18n FR+EN.
- **Upgrade différé** (`effective = next_cycle`, proposé si plan payant en cours) : à l'approbation,
  `approveDeferred` encaisse et **planifie** le changement (`metadata['scheduled_change']`, demande
  `approved`) ; `RenewalService` l'**applique à l'échéance** (`applyScheduledChange`, demande `activated`,
  audit `billing.scheduled_change_applied`). `syncFromPayment` garde le différé non activé.
- **Tests** : `DeferredUpgradeTest` (planifié puis appliqué au renouvellement). Suite feature **30/30**.
  Assistant vérifié en navigateur (5 étapes jusqu'à la confirmation).

## [Non publié] — 🧾 RC-33 : Comptabilité — avoirs (notes de crédit) & application aux factures (P3.2) (2026-07-05)

Extension de la facturation (RC-30) : les **avoirs** réutilisent la table `invoices`
(`kind = credit_note`, numéro `AV-`).

- **Création depuis une facture émise** : l'avoir reprend les lignes de la facture et pointe vers elle
  (`credit_note_of_id`, traçabilité).
- **Émission** : numéro `AV-` + **écriture INVERSE** via le moteur d'imputation (`credit_note.issued`
  → débit **701** HT + débit **4431** TVA / crédit **411** client, journal **AV**).
- **Application aux factures** (`credit_note_applications`) : impute l'avoir à une facture émise pour en
  réduire le reste dû ; borné au reste applicable de l'avoir **et** au reste dû de la facture ;
  applications cumulables (une ligne par couple avoir↔facture). `remainingMinor()` d'une facture tient
  compte des paiements **et** des avoirs appliqués.
- **PDF** avoir (titre AVOIR, libellés adaptés). **Front** : `CreditNotesView`
  (`/accounting/credit-notes`) — liste, création depuis facture, émission, application, PDF. i18n FR/EN.
- **Migration** : `invoices.credit_note_of_id` + `invoices.credited_minor` + table
  `credit_note_applications`. **Tests** : `AccountingCreditNoteTest` (8) + `CreditNotesView.spec.ts` (3).

## [Non publié] — ⚠️ RC-32 : Mise à niveau — aperçu d'impact downgrade (P3) (2026-07-05)

- **Aperçu d'impact avant confirmation** (`DowngradeImpactService`) : modules retirés + quotas dépassés
  (`users/products/customers/warehouses/orders`) → `{usage, limit, excess}`. Endpoint
  `POST /api/me/subscription/downgrade-impact`. Aucune donnée supprimée — la restriction reste assurée
  par l'existant (module gating + `EnforceQuota`).
- `QuotaService` : ajout de `usage()` + `planLimit()` (additifs, sans toucher l'enforcement).
- **Front** : encart d'avertissement ambre dans le drawer (modules perdus + dépassements + message
  rassurant « données conservées »). i18n FR+EN.
- **Tests** : `DowngradeImpactTest` (2, critère #17). Vérifié en navigateur (pro → Découverte :
  « 4 utilisateurs pour 1 inclus, 3 en trop »). Suite feature **29/29**.

## [Non publié] — ✅ RC-31 : Mise à niveau — consentement + notifications (P2) (2026-07-05)

- **Consentement obligatoire** (`subscription_consents`) : trace immuable (texte, version, IP,
  user-agent, source, entité liée) captée à la soumission via `ConsentService`. `submitPayment` exige
  `consent => accepted` (422 sinon). Endpoint `GET /api/me/subscription/consent-text` (texte serveur).
  Front : case obligatoire dans le drawer, soumission bloquée sans coche. i18n FR+EN.
- **Notifications** (réutilisent l'outbox `NotificationService` existant, historisé, best-effort) :
  `billing.subscription_submitted` (tenant) + `billing.subscription_admin_new` (super-admins) à la
  soumission ; `billing.subscription_activated` / `billing.subscription_rejected` (tenant) selon
  l'approbation. Modèles globaux seedés par migration ; câblés dans le cycle de la demande.
- **Tests** : `ConsentTest` (3), `SubscriptionNotificationTest` (3). Suite feature **27/27**.
- Note : le centre in-app (cloche) reste couplé aux alertes marketplace — généralisation = P2b optionnel.

## [Non publié] — 🧾 RC-30 : Comptabilité — facturation client & allocations de paiement (P3) (2026-07-05)

Suite du module Comptabilité SYSCOHADA (après le référentiel P1 et les écritures/moteur P2) :

- **Factures client** (`invoices`/`invoice_lines`) : brouillon librement modifiable → **émission**
  (numéro `FA-` séquentiel, immuable). **TVA calculée côté serveur** par ligne (HT après remise en
  points de base → `Tax::amountFor`), totaux HT/TVA/TTC recomposés. Création possible **depuis une
  commande** (`fromOrder`).
- **Écritures automatiques** via le moteur d'imputation (outbox idempotent) : `invoice.issued` →
  débit **411** client (TTC) / crédit **701** (HT) + **4431** (TVA collectée) ; `payment.allocated` →
  débit trésorerie (caisse/banque/mobile) / crédit **411**.
- **Allocations de paiement N↔N** (`payment_allocations`) : un règlement imputé à une ou plusieurs
  factures, borné au reste dû **et** au disponible du paiement ; statut facture
  issued → partially_paid → paid.
- **PDF** de facture (DomPDF) : `GET /api/accounting/invoices/{id}/pdf`.
- **Front** : `InvoicesView` (`/accounting/invoices`, onglet par défaut) — liste, création à totaux en
  direct, émission, encaissement, lien PDF. i18n FR/EN.
- **Sécurité/traçabilité** : saisie sous `accounting.entries.create` (caissier 403) ; toute écriture
  garde le lien vers sa facture source (rejouable). **Migration** `invoices`/`invoice_lines`/
  `payment_allocations`. **Tests** : `AccountingInvoiceTest` (7) + `InvoicesView.spec.ts` (3).

## [Non publié] — 🗓️ RC-29 : Mise à niveau — durée multi-période, annuel ×12, promos auto (P0.1) (2026-07-05)

- **Durée** : payer N périodes d'avance (mensuel 1–12, annuel 1–5) ; total = tarif unitaire × durée.
  `changePlan(periods: N)` étend la période de N ; branchement dédié `approveMultiPeriod` (règlement
  sur le net autoritatif de la demande, sans proration). Chemin monoperiode **inchangé**.
- **Tarif annuel = ×12** (fin de la remise « 2 mois offerts ») — `PlansSeeder` ; badge d'économie de la
  page pricing masqué automatiquement quand `savings_pct == 0`. ⚠️ Re-seed requis.
- **Promotions « en cours » auto** (`PromotionService::activeFor`) appliquées sans code, limitées aux
  **périodes couvertes** par leur fenêtre de validité (pourcentage par période, montant fixe une fois).
  Devis : `promo.source` + `promo.covered_periods`.
- **Front** : sélecteur de durée + détail enrichi (unitaire → × durée → remise/badge auto → total), i18n.
- **Migration** : `subscription_change_requests.quantity`. **Tests** : `MultiPeriodQuoteTest` (5 cas).

## [Non publié] — 🔁 RC-28 : Mise à niveau de plan — demande & machine à états (P1) (2026-07-05)

Pivot **paiement-first → demande-first** (audit G4/G6), non cassant :

- **`SubscriptionChangeRequest`** (`subscription_change_requests`) : objet de premier plan portant le
  cycle de vie (`draft → submitted → pending_payment|pending_validation → approved → activated`,
  + `rejected/cancelled/expired/failed`). Transitions **gardées**, historique en `metadata`.
- **Snapshot immuable** du plan cible (`plan_snapshot`) + montants figés du devis P0 → une modif
  ultérieure du plan n'altère pas une demande existante.
- **Rattachement** : `manual_payments.change_request_id` (nullable, rétro-compatible). `approve/reject`
  synchronisent la demande (`activated/pending_payment/rejected`). Création **atomique** au submit.
- **Endpoints tenant** : `GET/POST /api/me/subscription/change-requests`, `{id}`, `{id}/submit`,
  `{id}/cancel` (isolation TenantScope → 404 cross-tenant).
- **Tests** : `ChangeRequestTest` (6 cas) — critères #12/#13/#14/#21 + garde de transition.

## [Non publié] — 🔒 RC-27 : Mise à niveau de plan — montant autoritatif (P0) (2026-07-05)

Refonte progressive du parcours de changement de plan (audit : `docs/audit/plan-upgrade-audit.md`).
**P0 — le montant à payer n'est plus une saisie client** :

- **Devis serveur-side** (`UpgradeQuoteService` + `UpgradeQuote`) : brut `plan_prices` du marché résolu
  → remise d'une promo **validée** → avoir de proration → **net à payer**. Nouvel endpoint
  `POST /api/me/subscription/calculate-upgrade` (lecture seule).
- **Durcissement du submit** `POST /api/me/manual-payments` : `amount_cents` devient optionnel ;
  absent → net autoritatif + devise du marché imposés. La **cible** du plan reste toujours dérivée
  serveur (un montant client ne peut plus falsifier le plan visé — critère #22).
- **Front (drawer d'upgrade)** : sélecteur de périodicité, encart **devis verrouillé** avec détail du
  calcul, revalidation promo, contrôle de taille de fichier (5 Mo), i18n FR+EN.
- **Tests** : `CalculateUpgradeTest` (7 cas) — critères #3/#4/#5/#6/#22.

## [Non publié] — 📒 RC-25/26 : Comptabilité — écritures + moteur d'imputation (P2) (2026-07-05)

Branche `feature/rc23-accounting-referential` (release `v1.0.0` → `rc.156`). Cœur métier du module
comptable, branché sur le POS :

- **Écritures en partie double** (`accounting_entries`/`_lines`) : brouillon → comptabilisation
  (numéro séquentiel par journal + rattachement période) → **extourne** (contre-écriture liée).
  Équilibre imposé, ≥ 2 lignes, un côté par ligne ; **immutabilité** d'une écriture postée ; refus
  en période verrouillée. Écran **Écritures** avec saisie manuelle et contrôle d'équilibre en direct.
- **Moteur d'imputation** (`ImputationEngine`) + **outbox idempotente** (`accounting_outbox`,
  unique par source) : le module Comptabilité **écoute** les événements POS (dépendance à sens
  unique). Règles SYSCOHADA seedées : vente POS (débit trésorerie par tender / crédit 701),
  remboursement (701 / trésorerie), écart de clôture (658/758), mouvements de caisse, paiements.
  Comptabilisation auto ou brouillon selon `auto_post`. Worker `accounting:process-outbox` (/5 min).
- **Une vente POS ⇒ exactement une écriture**, même en resync offline (idempotence `pos_reference`
  RC-22 × unicité outbox). Le POS reste inchangé pour un tenant sans module comptable (0 écriture).
- **Validé E2E sur la base locale** : une vente mixte (4 000 espèces + 4 000 mobile money) a produit
  l'écriture postée `VT26-000001` équilibrée (débit 571 Caisse 4 000 + 585 Mobile 4 000 = crédit 701
  Ventes 8 000), consultable dans l'écran Écritures.
- +13 tests backend (écritures + imputation) ; POS et suites existantes inchangées (34 verts).
  i18n FR/EN, docs tech + module.

## [Non publié] — 🧮 RC-23 : module Comptabilité — référentiel SYSCOHADA (P1) (2026-07-05)

Branche `feature/rc23-accounting-referential` (release `v1.0.0` → `rc.155`). Premier incrément du
module comptable (architecture : docs/architecture/comptabilite-syscohada.md) :

- **Nouveau module `Accounting`** (backend + frontend), gated `module:accounting` (seedé dans
  `erp_modules`, associé à tous les plans) — menu **Comptabilité** dans le SPA.
- **Référentiel** : classes SYSCOHADA 1–9 (globales), plan de comptes par tenant (38 comptes seedés,
  comptes *système* du moteur d'imputation indésactivables, création libre — classe dérivée du code),
  8 journaux (VT/AC/CA/BQ/OD/ST/AV/RG), taxes en points de base (TVA locale auto selon pays :
  UEMOA 18 %, CEMAC 19,25 %…), paramètres tenant (`default_accounts` symboliques `@cash`→571,
  `auto_post`), exercice courant + 12 périodes mensuelles.
- **Provisionnement idempotent** (`ChartOfAccountsProvisioner`) via `POST /api/accounting/provision`
  — assistant intégré à l'écran Plan comptable.
- **Périodes** : verrouillage (chef comptable) et **réouverture contrôlée** (permission dédiée
  `accounting.periods.reopen`, motif obligatoire, audit) — pattern PeriodLockService.
- **RBAC** : rôles `accountant` / `chief-accountant` / `accounting-viewer` / `auditor` +
  permissions `accounting.*` (séparation des pouvoirs).
- **Frontend** : 4 écrans (Plan comptable, Taxes, Exercices & périodes, Paramètres), i18n FR/EN.
- **Correctif transverse** : les dates PURES (casts `date`) ne se décalent plus d'un jour à
  l'affichage dans les fuseaux à l'ouest d'UTC (sérialisation `Y-m-d` + parsing local `toDate`).
- +8 tests backend, +3 specs front. Validé E2E en preview : menu → provisionnement (38 comptes) →
  verrouillage 2026-01 → réouverture refusée sans permission.

## [Non publié] — 🔒 RC-22 : idempotence checkout POS (P0 audit intégration) + plans compta (2026-07-05)

Branche `feature/rc22-pos-idempotency` (release `v1.0.0` → `rc.154`). Suite de l'audit
d'intégration POS/compta (2 revues parallèles + preuves E2E) :

- **Idempotence du checkout POS** (trou CRITIQUE A-1) : le POS génère un id client **avant** la
  tentative (`X-Idempotency-Key`), réutilisé tel quel par la file offline à chaque retry. Serveur :
  `orders.pos_reference` (unique par tenant) → une clé déjà vue renvoie la vente existante (même
  après clôture de session — resync du lendemain) ; course concurrente tranchée par la contrainte
  unique. Sans clé : comportement historique. **Fin du risque de double vente offline.** +3 tests.
- **Rapports d'audit** : `docs/architecture/audit-integration-pos-compta.md` (verdict : POS
  réellement branché, 8 trous hiérarchisés A-1..A-8) et **plan complet du module comptable
  SYSCOHADA** `docs/architecture/comptabilite-syscohada.md` (architecture, 24 tables, moteur
  d'imputation, facturation, livres, clôtures, sécurité, UX, roadmap P0→P5).
- Doc POS : section « Idempotence & synchronisation offline » (mapping des statuts
  pending_sync/synced/failed_sync ↔ états serveur, lien future écriture comptable).

## [Non publié] — ↩️ RC-21 : revue Retours (RMA) — front + backend (2026-07-05)

Branche `feature/rc21-returns-review` (release `v1.0.0` → `rc.153`). Inspection complète du module
Retours, validée E2E en preview sur la base locale (cycle créer → approuver → restocker réel).

- **[R-0 CRITIQUE]** `ReturnsView` appelait l'API **sans le préfixe `/api`** (baseURL vide) : l'écran
  Retours n'a **jamais fonctionné** (liste toujours vide, actions en 404). Réparé et validé en réel.
- **[R-1 HAUTE]** **Sur-retour borné** : quantité retournable = achetée − déjà demandée/retournée
  (retours non refusés de la même ligne). Avant : retour de 50 sur une ligne de 2 accepté → stock
  fantôme au restock + remboursement supérieur au payé. 422 explicite (« N restant(s) sur M »),
  message affiché dans le modal. +2 tests.
- **[R-2 HAUTE]** Retour possible uniquement sur commande **honorée** — un brouillon/confirmé n'a
  jamais décrémenté le stock (restock = stock fantôme). +1 test.
- **[R-3]** Création de retour placée sous **RBAC** `manager|admin|orders.manage` (un viewer pouvait
  en créer) ; la caisse garde son propre guard. +1 test.
- **[R-4]** Listes `condition` **harmonisées** entre Orders et POS (`resalable,damaged,defective,destroyed`).
- **[R-5]** Le front ne permettait **aucune création de retour hors caisse** : bouton « Retourner des
  articles » sur les commandes livrées (modal lignes/quantités/état/motif/résolution, erreurs serveur
  affichées), **pagination** de la liste des retours, résolution absente affichée « — ». i18n FR/EN.

Backend 43 tests retours/POS verts, front 43 specs vertes, typecheck OK. E2E : RET-000002
créé → approuvé (journal d'audit écrit) → restocké (stock 198→199) ; sur-retour refusé avec message.

## [Non publié] — 🧹 RC-20 : file BASSE (8 correctifs) + validation E2E sur base locale (2026-07-05)

Branche `feature/rc20-basse-fixes` (release `v1.0.0` → `rc.152`). Solde du backlog rc.147 (hors 2
hypothèses produit) + **première validation preview de bout en bout sur la base MySQL locale**.

**Correctifs**
- **[C-8]** Journaux d'audit réellement écrits (`return.approved`, `product.created`, `product.archived`) —
  les appels positionnels en désordre levaient un TypeError avalé.
- **[C-9/P-3]** Numéros `RET-`/`TRF-`/`SUP-` via un **`SequenceService` partagé** (séquence verrouillée
  `FOR UPDATE`, seed de continuité pour l'historique) — fin des courses `count()+1`.
- **[C-10]** `paymentService.record` envoie une **clé d'idempotence** (`X-Idempotency-Key`, UUID client).
- **[N-6]** Notifications : un message **déjà parti** n'est plus remboursé ni remis en `pending` si le
  marquage `sent` échoue (fin du double envoi potentiel).
- **[B-7]** `PromotionService::recordUse` verrouille la promo et re-vérifie `max_uses` en transaction.
- **[P-4]** Import produits : les doublons de SKU **intra-fichier** sont ignorés dès l'analyse.
- **[P-5]** `customer_id` validé au tenant dans `OrderService::create` (couvre commandes ET caisse) → 422.
- **[P-6]** Label « WooCommerce ».

**Environnement local / validation E2E (preview)**
- **Résolution du faux « blocage sandbox »** : un serveur d'un autre projet squattait le port 8000
  (423 `app_locked`/404). Port libéré → **API 100 % fonctionnelle en preview**.
- Base locale : `default_storage_engine=MyISAM` → 78 tables converties **InnoDB** + `engine=InnoDB`
  forcé dans `config/database.php` (l'app dépend des transactions/`lockForUpdate`). Migrations en
  retard appliquées, `DemoSeeder` rendu idempotent sous MySQL (`fiscal_periods` DATE).
- **Parcours validés en conditions réelles** : login → caisse Desktop (vente **paiement mixte**
  10 000 esp. + 6 000 MM, attendu 50 000→60 000), **ticket de caisse** complet (2 legs affichés),
  **mouvement de caisse** (sortie 5 000 → 55 000), POS mobile (session partagée), **Analyse
  d'inventaire** (ABC réel : A=7 produits 77,8 % du CA), commandes (recherche serveur → 1 résultat,
  **pagination 6 pages** naviguable). +1 correctif de style (cartes KPI insights, styles scopés).
- `launch.json` : configuration `backend` (php artisan serve :8000) ajoutée.

## [Non publié] — 🖨️ RC-19 : ticket de caisse — génération + impression (2026-07-05)

Branche `feature/rc19-pos-receipt` (release `v1.0.0` → `rc.151`). Suite du chantier caisse :

- **Backend** : `ReceiptService` + `GET /api/pos/orders/{orderId}/receipt` (rôles caisse, scope
  tenant → 404 hors tenant) — payload structuré : en-tête boutique (nom/adresse/téléphone/devise
  des settings), lignes, **tous les paiements** (splits RC-16 inclus, référence Mobile Money),
  totaux, caissier, session. +3 tests (`PosReceiptTest`).
- **Frontend** : composant `PosReceipt.vue` (rendu ticket **80 mm**, CSS unique aperçu+impression
  dans `receiptPrint.ts`) ; impression par **iframe cachée** (pas de popup). Caisse Desktop :
  bouton *Imprimer le ticket* + raccourci **F7** + réimpression de la dernière vente. POS mobile :
  bouton *Ticket* après une vente (réinitialisé à la clôture). i18n FR/EN (`posReceipt`). +2 specs.
- Docs tech + guide utilisateur mis à jour.

## [Non publié] — 🧰 RC-18 : file MOYENNE du backlog — 8 correctifs (2026-07-05)

Branche `feature/rc18-moyenne-fixes` (release `v1.0.0` → `rc.150`). Toute la file **MOYENNE** du backlog
rc.147 :

- **[D-3] Pool de licences** : les clés sont **libérées** à la révocation/retour (RMA) — elles redeviennent
  `available` et réassignables FIFO (fin de la fuite du pool et des fausses alertes `pool_exhausted`).
- **[M-4] Renouvellement** : `isFreePlan` lit le **prix localisé** (`PlanPrice` du marché de l'abonnement)
  avant les colonnes legacy — un plan gratuit en legacy mais payant sur sa grille passe bien `past_due`.
- **[M-5] Promo** : `POST /api/me/promo/apply` ne **consomme plus l'usage** (validation seule) ; l'usage est
  enregistré à l'activation du paiement — le paiement légitime n'est plus routé `needs_review`.
- **[C-3] Commandes (front)** : pagination réparée (normalisation du paginator plat en `{data, meta}`),
  reset page 1 à chaque changement de filtre.
- **[C-4] Commandes (API)** : filtres `search` (n° commande / nom client) et `from_date`/`to_date`
  désormais appliqués côté serveur.
- **[C-5] Retours (front)** : les actions approve/restock/reject affichent les erreurs (bandeau + message
  serveur) au lieu d'échouer en silence. i18n FR/EN.
- **[C-6] Lots/péremption** : à la confirmation d'une commande d'un produit suivi par lot, le **vendable
  exclut les lots périmés** encore comptés dans l'agrégat → plus de vente « à découvert » contre du stock
  périmé (422 explicite avec le disponible réel).
- **[C-7] Import groupé** : `quantity_after` de l'historique des mouvements n'ajoute plus la quantité
  **deux fois**.

+6 tests backend, i18n FR/EN, front vert (43 specs orders+i18n).

## [Non publié] — 🩹 RC-17 : correctifs HAUTE — avoirs réappliqués + rapports d'inventaire exposés (2026-07-05)

Branche `feature/rc17-billing-reports-fixes` (release `v1.0.0` → `rc.149`). Les deux bugs **HAUTE** du
backlog rc.147 :

- **[M-1] Ledger `tenant_credits` réappliqué** : les avoirs (trop-perçus) n'étaient **jamais consommés**
  (argent client perdu). Désormais, à l'approbation d'un paiement manuel, le solde d'avoirs (même devise)
  agit comme **acompte virtuel s'il permet de solder la cible** — puis il est **consommé** (ligne négative
  référencée au paiement, trace `ledger_credit_applied_minor` dans la metadata). Pas de consommation
  partielle en dépôt (même règle que la proration). `previewProration` déduit le ledger de l'assiette
  (le trop-perçu ne compte plus double). +4 tests (`BillingRulesTest`).
- **[M-2] Rapports d'inventaire exposés** : `abcClassification` (Pareto 80/15/5), `inventoryKpis`
  (DSI, rotation, fill rate, stock mort) et `stockReconciliation` (valorisation par catégorie) étaient
  développés+testés **sans route ni vue**. Nouvelles routes `GET /api/reports/abc|inventory-kpis|reconciliation`
  + onglet **Analyse d'inventaire** (`/reports/insights`) avec sélecteur de période, i18n FR/EN.
  +3 tests API, +3 specs front.

## [Non publié] — 🧾 RC-16 : caisse approfondie — fondation backend (split · mouvements · remboursement) (2026-07-05)

Branche `feature/rc16-pos` (release `v1.0.0` → `rc.148`). Première tranche du chantier **« caisse
approfondie »** : la base commune backend des futures caisses **Desktop** et **POS mobile**. Tout est
**rétrocompatible** — les 10 tests `PosSessionTest` restent verts sans modification.

- **Paiement mixte (split)** : `checkout` accepte désormais `payments: [{method, amount_cents, reference?}]`
  en plus du `method` unique historique. La somme des legs doit égaler exactement le total (sinon 422 +
  rollback intégral). Seule la part **espèces** alimente le fond de caisse attendu.
- **Mouvements de caisse** (nouvelle table `cash_movements` + modèle `CashMovement`) : entrées (pay-in) et
  sorties (pay-out) d'espèces hors vente — appoint, retrait, dépense. `expectedCashNow()` intègre le net
  des mouvements ; un pay-out ne peut excéder les espèces disponibles. Endpoints `GET …/movements` et
  `POST …/cash-movement`.
- **Remboursement au comptoir** : `POST …/refund` délègue au `OrderReturnService` (create → approve →
  restock : réintègre le stock revendable et **défait** sérialisés / garanties / accès digitaux) ; le leg
  **espèces** d'un remboursement est enregistré comme sortie de caisse (l'attendu baisse).
- **+7 tests** `PosAdvancedTest` (split + non-somme, pay-in/pay-out, plafond retrait, remboursement espèces
  & non-espèces, session close). Service front `posService.ts` + types étendus. Docs tech + utilisateur MAJ.

> **Suite du chantier** : `PosDesktopView.vue` (grille + panier + pavé de paiement + raccourcis clavier),
> `PosMobileView.vue` (tactile, Mobile Money, file offline), composable `usePosSession()` partagé — increments RC-17/RC-18.

## [Non publié] — 🐛 RC-15 : audit fonctionnel — bugs consignés + correctifs prioritaires (2026-07-05)

Branche `feature/rc15-qa-pos` (release `v1.0.0` → `rc.147`). Audit fonctionnel par 4 revues parallèles
(commerce, monétisation, plateforme, couverture de tests) → **~24 bugs consignés** dans
`docs/recette/rc15-bugs-backlog.md`. Les correctifs **HAUTE/MAJEUR** clairs sont traités ici ; le reste
est priorisé pour les incréments suivants.

- **[MAJEUR] Doublon email/code → 500** (Customers & Suppliers) : ajout d'une règle `unique` applicative
  scopée tenant → **422** propre au lieu d'une `QueryException`.
- **[MAJEUR] Marketplace : réouverture auto morte** : le listener excluait les listings `closed` et ne
  dispatchait jamais `reopen` → `is_auto_reopen_enabled` était inerte. Corrigé (inclut `closed`, branche
  reopen quand le stock repasse au-dessus du seuil).
- **[HAUTE] Inventory : `warehouse_id` ignoré au move-out/adjust** → décrément du **mauvais entrepôt** en
  multi-site. Résolution de l'entrepôt (comme au move-in) + `warehouse_id` ajouté à `AdjustStockRequest`.
- **[HAUTE] Transfert : write-off = double décrément** du stock source (les unités ont déjà quitté au
  ship). Le write-off devient **documentaire** (aucun ajustement de quantité).
- **+2 tests** (Customers doublon → 422 ; `StockTransferTest` réécrit : source reste 90, pas 88). Backend vert.

> **Backlog restant** (consigné, priorisé) : ledger `tenant_credits` en écriture seule, code mort Reports
> (ABC/KPI/réconciliation), clés de pool non libérées, pagination/ filtres commandes, ventes contre lots
> périmés, etc. + **chantier « caisse approfondie »** (POS split/remise/remboursement, Desktop + mobile).

## [Non publié] — 🧭 RC-14 : cohérence de l'onboarding initial (2026-07-05)

Branche `feature/rc14-onboarding-fixes` (release `v1.0.0` → `rc.146`). Suite à l'évaluation UX/UI de
l'onboarding : le parcours se termine sans erreur (état d'échec avec **Réessayer**), 3 incohérences
« onboarding ↔ modules » corrigées.

- **Entrepôt par défaut manquant** : `provision` créait un entrepôt uniquement si un nom était fourni,
  mais le front ne l'envoyait jamais → un tenant « needs_stock » repartait **sans aucun entrepôt** (la
  1ʳᵉ opération de stock échouait, `defaultWarehouseId` renvoyant null). Désormais un entrepôt par
  défaut (« Entrepôt principal ») est créé dès que `needs_stock` (idempotent).
- **Taille d'équipe non persistée** : l'étape 2 collectait la taille d'équipe sans jamais l'enregistrer.
  Le front envoie maintenant `nb_users` (fourchette solo/small/medium/large → 1/5/20/50).
- **Wizard reformulé en préférences** : les étapes 3–4 laissaient croire que la sélection *activait* des
  modules, alors que les modules dépendent du **plan** (`activatePlanModules`). Copie clarifiée (FR+EN) :
  « préférences pour personnaliser l'espace ; les modules disponibles dépendent de votre offre ».
- **+3 tests** `WorkspaceApiTest` (entrepôt créé si stock, aucun sinon, `nb_users` persisté). vue-tsc 0.

## [Non publié] — 🔐 RC-13 : Lot C (4/4) — 2FA par code email (F-4) — Lot C complet (2026-07-04)

Branche `feature/rc13-2fa` (release `v1.0.0` → `rc.145`). Dernier volet du Lot C : le backlog sécurité
comptes & accès (F-3/F-6/F-5/F-4) est **complet**.

### Backend
- **`users.two_factor_enabled`** + **`two_factor_codes`** : 2FA **opt-in par utilisateur**, code de
  connexion à 6 chiffres **haché**, court (10 min), borné à 5 tentatives.
- **`AuthService`** : `authenticate()` (vérifie les identifiants **sans** émettre de token) +
  `issueTokenFor()`. **`AuthController::login`** refactoré : si 2FA activée → **pas de token**, un code
  est envoyé (`TwoFactorCodeMail`) et la réponse porte `two_factor_required: true`.
- **`POST /api/auth/2fa/verify`** `{email, code}` (public, throttle 10/10 min) → délivre le token après
  le second facteur (avec audit `via: 2fa`). **`POST /api/me/2fa`** `{enabled}` : active/désactive.
  `UserResource` expose `two_factor_enabled`.
- **+6 tests** `TwoFactorTest` (login direct sans 2FA, activation, challenge email sans token, code
  valide → token, code faux, désactivation). `Mail::fake()`. Auth complet vert.

### Frontend
- **Login** : étape de **second facteur** (code email) quand `two_factor_required` — le store gère
  `login()` (renvoie `twoFactorRequired`) et `completeTwoFactor()`. **Profil** : interrupteur d'activation
  de la 2FA. i18n FR+EN (`auth.twoFactor.*`). vue-tsc 0.

> 🎉 **Lot C complet** : reset mot de passe (F-3), re-vérification email (F-6), invitations email (F-5),
> 2FA email (F-4). Reste du backlog sécurité issu de l'audit : néant (Lots A/B/D/E/C tous livrés).

## [Non publié] — 👥 RC-12 : Lot C (3/4) — invitations d'équipe par email (F-5) (2026-07-03)

Branche `feature/rc12-invitations` (release `v1.0.0` → `rc.144`). Troisième volet du Lot C.

### Backend
- **`user_invitations`** : code d'activation à 6 chiffres **haché**, expirable (7 jours), borné à 5
  tentatives, une invitation par utilisateur.
- **`POST /api/workspace/users`** (inviter) : ne renvoie **plus de mot de passe temporaire** dans la
  réponse API. Le membre est créé avec un mot de passe aléatoire inutilisable et reçoit un
  **email d'invitation** (`UserInvitationMail`, code + lien `/accept-invitation`) ; réponse
  `invitation_sent: true`.
- **`POST /api/auth/accept-invitation`** `{email, code, password}` (public, throttle 5/10 min) : le
  membre **choisit son mot de passe** avec le code reçu ; invitation non ré-acceptable.
- **+tests** : `WorkspaceApiTest` mis à jour (email au lieu de temp password) + `InvitationAcceptTest`
  (acceptation valide → login OK, code faux, non ré-acceptable). `Mail::fake()`.

### Frontend
- **Paramètres → Équipe** : la modale d'invitation confirme « email envoyé » (fini le mot de passe à
  recopier). Nouvelle page publique **`/accept-invitation`** (email pré-rempli depuis le lien, code +
  mot de passe). i18n FR+EN (`auth.invitation.*`, `settings.invite.emailSentHint`). vue-tsc 0.

> Reste du Lot C : 2FA par code email (F-4).

## [Non publié] — ✉️ RC-11 : Lot C (2/4) — re-vérification de l'email au changement (F-6) (2026-07-02)

Branche `feature/rc11-email-verify` (release `v1.0.0` → `rc.143`). Deuxième volet du Lot C.

### Backend
- **`email_change_requests`** : demande de changement d'email par utilisateur, code 6 chiffres **haché**,
  expirable (30 min), borné à 5 tentatives.
- **`PATCH /api/me/profile`** : le **nom** s'applique immédiatement, mais un **changement d'email** ne
  l'est plus directement — un code part à la **nouvelle** adresse (`EmailChangeCodeMail`) et la réponse
  renvoie `email_verification_required` + `pending_email`.
- **`POST /api/me/email/verify`** `{code}` : applique le nouvel email après vérification (re-contrôle
  d'unicité au moment de l'application). Empêche l'usurpation d'email / la faute de frappe (F-6).
- **+tests** : `UserProfileApiTest` mis à jour (changement d'email → vérification requise, appliqué
  seulement après le bon code ; nom immédiat + email en attente). `Mail::fake()`.

### Frontend
- **ProfileView** : après un changement d'email, un bloc de **saisie de code** apparaît pour confirmer
  la nouvelle adresse ; le nom est appliqué immédiatement. i18n FR+EN (`profile.emailVerify.*`). vue-tsc 0.

> Reste du Lot C : invitations par lien email (F-5), 2FA par code email (F-4).

## [Non publié] — 🔑 RC-10 : Lot C (1/2) — réinitialisation de mot de passe par email (F-3) (2026-07-02)

Branche `feature/rc10-auth-reset` (release `v1.0.0` → `rc.142`). Premier volet du Lot C sécurité
(comptes & accès), débloqué par le mailer applicatif (`config/mail.php`, `log` en dev).

### Backend
- **`password_reset_codes`** : code à 6 chiffres **haché**, expirable (30 min), borné en tentatives (5).
- **`PasswordResetService`** + `PasswordResetController` :
  - `POST /api/auth/forgot-password` `{email}` — envoie le code (**mailer natif**, `PasswordResetCodeMail`),
    réponse **générique** (anti-énumération), throttle 3/10 min.
  - `POST /api/auth/reset-password` `{email, code, password}` — vérifie code (haché, expiration,
    tentatives), applique le mot de passe et **révoque toutes les sessions** (tokens), purge le code.
    Throttle 5/10 min.
- **+6 tests** `PasswordResetTest` (envoi pour un compte connu, générique+silencieux pour un inconnu,
  reset valide → mot de passe changé + sessions révoquées + ancien token 401, code faux compté,
  code expiré, code brûlé au seuil). `Mail::fake()`.

### Frontend
- **`ForgotPasswordView`** (`/forgot-password`) : flux en 2 étapes (email → code + nouveau mot de passe),
  lien depuis la connexion (remplace l'ancien texte d'aide statique). i18n FR+EN (`auth.reset.*`).
  vue-tsc 0, garde i18n ✅.

> Reste du Lot C (à suivre) : re-vérification email au changement (F-6), invitations par lien email
> (F-5), 2FA (F-4).

## [Non publié] — 🛡️ RC-9 : durcissement sécurité — Lot D (plateforme) + Lot B (uploads) (2026-07-01)

Branche `feature/rc9-security-lot-db` (release `v1.0.0` → `rc.141`). Suite du backlog sécurité issu de
l'audit Phase 3.

### Lot D — durcissement plateforme
- **F-9** — plafond de débit **global** sur `api/*` : middleware `ApiRateLimit` (clé = utilisateur
  authentifié sinon IP, défaut **600/min**, `config/security.php` → `API_RATE_LIMIT`, en-têtes
  `X-RateLimit-*` / `Retry-After`). Global (pas seulement le groupe `api`) pour couvrir les routes de
  modules chargées via `loadRoutesFrom`. Désactivé en test (`phpunit.xml`).
- **F-10** — **en-têtes de sécurité** (`SecurityHeaders`) sur toutes les réponses : `X-Content-Type-
  Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `X-Permitted-Cross-Domain-Policies`,
  HSTS derrière HTTPS. **CORS restreint** : `config/cors.php` publié (origines via `CORS_ALLOWED_
  ORIGINS`, défaut origines de dev ; plus de `*`), API par jeton Bearer donc `supports_credentials=false`.
- **F-11** — le log d'une **signature de webhook invalide** ne divulgue plus le préfixe de la signature
  attendue (aidait à distinguer « secret faux » de « payload faux »).

### Lot B — sécurité des uploads digitaux
- **F-7** — upload d'asset digital : **liste blanche d'extensions** (`config/digital.php → upload.
  allowed_extensions` ; html/svg/js… exclus) + taille max configurable ; **nom de fichier assaini**
  (retrait de chemin et caractères douteux) ; **MIME dérivé du contenu** (`getMimeType`) et non de la
  valeur client spoofable.

### Tests
- **+4 tests** (`SecurityHardeningTest` : en-têtes + 429 global ; `DigitalAssetTest` : rejet d'extension
  non autorisée, assainissement du nom). Backend vert.

## [Non publié] — 🛡️ RC-8 : durcissement sécurité — Lot A (tokens portail) + Lot E (cohérence tenant) (2026-07-01)

Branche `feature/rc8-security-lot-ae` (release `v1.0.0` → `rc.140`). Premiers lots du backlog sécurité
issu de l'audit Phase 3 (quick wins sans dépendance).

### Lot A — cycle de vie des tokens portail
- **F-1** — `config/sanctum.php` publié : plafond global d'expiration (défaut **1 an**, `SANCTUM_TOKEN_
  EXPIRATION_MINUTES`) rattrapant tout token émis sans échéance ; le token portail est désormais émis
  avec une **expiration explicite de 30 j** (`createToken('portal', ['portal'], now()->addDays(30))`).
  Fin des tokens portail « à vie ».
- **F-2** — nouveau **`POST /api/portal/logout`** : révoque le token courant côté serveur
  (`currentAccessToken()->delete()`). Le `logout()` du front l'appelle (best-effort) avant de purger le
  `localStorage` — la déconnexion n'est plus purement cliente.

### Lot E — cohérence tenant / permissions
- **F-8** — le groupe de routes **ImportExport** reçoit le middleware `tenant`
  (`EnsureUserBelongsToTenant`), qui **pose le contexte d'équipe Spatie** (`setPermissionsTeamId`) :
  les gardes `role_or_permission:import_export.*` s'évaluent désormais dans le bon tenant.
- **F-12** — **normalisation de l'email `Customer`** (minuscules + trim) via mutateur, + migration de
  **backfill** des lignes existantes (`LOWER(TRIM(email))`). Fiabilise le rapprochement « mes achats »
  du portail (bug de casse, impact PostgreSQL).

### Tests
- **+4 tests** (`PortalSecurityTest` : expiration du token, révocation au logout ; `CustomerServiceTest` :
  normalisation email). Digital+Customers+ImportExport **verts**, front vue-tsc 0.

## [Non publié] — 🔒 Recette QA Phase 3 (rc.134-138) — 15 correctifs, verdict GO (2026-06-30)

Branche `feature/qa-recette-phase-3b` (release `v1.0.0` → `rc.139`). Deux revues indépendantes en
lecture seule (audit sécurité + revue adverse logique) sur RC-7B→RC-7F ; chaque finding vérifié dans
le code puis corrigé avec tests de régression. Détail : `docs/recette/recette-phase-3-rc134-138.md`.

### Sécurité
- **[CRITIQUE] Cloisonnement des tokens portail** : un `PortalAccount` partageant le guard `sanctum`
  pouvait atteindre `PATCH /api/me/profile` (route sans `tenant`) et changer son email sans
  re-vérification → vol des achats digitaux d'autrui. Correctif : middleware **global**
  `GuardPortalPrincipal` (token portail ⇒ `api/portal/*` uniquement, 403 ailleurs ; résolution directe
  du jeton pour couvrir les routes de modules hors groupe `api`) + ability `portal`.
- **[HAUTE] `TenantScope` fail-closed** : un principal authentifié sans `tenant_id` ne désactive plus
  le filtrage (sentinelle impossible) ; `EnsureTenantHasModule` refuse un principal sans tenant.
- **Portail** : `verify` exige le **mot de passe** (anti pré-hijack) ; code borné en tentatives
  (`verification_attempts`, brûlé au-delà de 5, `hash_equals`) ; pas de re-envoi tant qu'un code est
  valide (anti-bombardement / drain de crédits).
- **Webhook MoMo** : montant **obligatoire** (absent → `needs_review`, jamais de crédit aveugle).

### Logique métier
- **Retours digitaux** : la révocation au prorata ne compte plus que les retours **restockés** + le
  retour en cours (un retour approuvé puis rejeté ne sur-révoque plus, irréversiblement).
- **`no_credit` réarmé** : à la recharge, les envois bloqués faute de crédit repassent `pending`
  (sinon perdus à jamais — critique car `email` est facturé par défaut).
- **Recharge `needs_review`** : non auto-créditée par un webhook ultérieur (anti double crédit) et
  désormais **annulable** ; `amount_scale` configurable (XOF en unité majeure).
- Divers : `my-purchases` exclut les accès expirés ; révocation garde les exemplaires les plus anciens ;
  `credit()` clampé à 0 ; robustesse UI (rechargement après annulation, lien « déjà inscrit ? »).

### Tests
- **+12 tests** de régression (`PortalSecurityTest` ×5, `CommunicationCreditTest` +2,
  `RechargeWebhookTest` +4, `ReturnVoidSpecialTest` +1). Backend complet vert, front 276 + vue-tsc 0.

## [Non publié] — 📲 RC-7F : webhook Mobile Money — recharge automatique des crédits (2026-06-29)

Branche `feature/momo-webhook` (release `v1.0.0` → `rc.138`). Automatise la recharge RC-7E : plus
besoin d'intervention opérateur quand le paiement Mobile Money est confirmé.

### Backend
- **`credit_recharge_orders`** : commande de recharge au pack figé (crédits/prix/devise), sous
  **référence payable unique** `RCH-XXXXXXXX`. Statuts `pending` / `paid` / `cancelled` /
  `needs_review`.
- **`POST /api/webhooks/mobile-money`** (public, throttle 60/min) : signature **HMAC-SHA256 du corps
  brut** (secret `MOMO_WEBHOOK_SECRET`, comparaison temps constant) ; secret absent → **503** (webhook
  désactivé, jamais de crédit non signé) ; signature invalide → **401**. Champs du payload **mappés
  par config** (`field_map`, chemins pointés) → Orange Money / Wave / MTN MoMo sans code.
- **Confirmation idempotente** (`RechargeOrderService::confirmByReference`, verrou de ligne) : replay
  → `already_processed` (jamais 2 crédits) ; montant/devise divergents → **aucun crédit**, commande
  `needs_review` (payload en meta) ; statut non final ignoré ; commande annulée non payable ;
  référence inconnue ignorée sans crash.
- Endpoints tenant : `GET/POST /credits/orders`, `POST /credits/orders/{id}/cancel` (manager/admin).
- **+10 tests** `RechargeWebhookTest`. Notifications **30 ✅**.

### Frontend
- Modale de recharge : mode **Mobile Money (automatique)** par défaut — génère et affiche la
  **référence payable** + montant — ou **encaissement manuel** (RC-7E, secours). Tableau « Recharges
  Mobile Money » (statut, annulation d'une commande en attente). i18n FR+EN. +2 tests (4 au total sur
  le panneau), vue-tsc 0.

## [Non publié] — 💬 RC-7E : crédits de communication rechargeables par tenant × canal (2026-06-29)

Branche `feature/comm-credits` (release `v1.0.0` → `rc.137`). Item 1 de la Phase 3 + **nouvelle
exigence fondateur** : chaque tenant recharge son compte email / SMS / WhatsApp moyennant un montant
additionnel, et chaque envoi décompte le solde.

### Backend
- **`communication_credits`** (tenant × canal → `balance`) + **`communication_credit_movements`**
  (journal append-only : `recharge` / `send` / `refund` / `adjustment`, `delta`, `balance_after`,
  `reference`, `meta`).
- **`CommunicationCreditService`** : `debit()` **atomique** (verrou de ligne — jamais négatif, `false`
  si insuffisant), `credit()`, `recharge(packCode)` (packs `config/notifications.php`), `balances()`,
  `isMetered()`.
- **Décompte à l'envoi** dans `NotificationService::flush()` — modèle **réservation → remboursement** :
  on débite **avant** l'envoi, on **rembourse** si l'envoi échoue (aucun crédit perdu, aucun double
  décompte). Solde nul → statut outbox **`no_credit`** (terminal, sans nouvelle tentative), le flux
  métier n'est **jamais** interrompu. Configurable (`credits.enabled` + `metered_channels`).
- **Recharge par pack** (rail de paiement manuel) : `GET /credits`, `GET /credits/movements`,
  `POST /credits/recharge` (manager/admin). Packs éditables sans code ; pack inconnu → 422.
- **Agrégateur réel** : le transport `http_api` couvre tout fournisseur ; presets `aggregator_presets`
  (Termii, Orange SMS, WhatsApp Cloud) fournis dans `config/notifications.php`.
- **+12 tests** `CommunicationCreditTest` (débit/mouvement, insuffisance, recharge, pack inconnu,
  décompte au flush, blocage à zéro sans appel réseau, remboursement sur échec, API soldes/recharge,
  isolation tenant) ; `NotificationTest` mis à jour (crédit consommé/remboursé). Notifications **20 ✅**,
  backend complet vert.

### Frontend — onglet Paramètres → Notifications → **Crédits**
- Soldes par canal (badge facturé/offert), bouton **Recharger** (choix du pack + réf. de paiement),
  journal des mouvements ; statut **« Crédit épuisé »** dans le journal d'envoi. i18n FR+EN
  (`settings.notif.credit.*`). +3 tests (274 total), garde i18n ✅, vue-tsc 0.

## [Non publié] — 🎟️ RC-7D : un accès digital par exemplaire (2026-06-28)

Branche `feature/digital-per-unit` (release `v1.0.0` → `rc.136`). Item 2 de la Phase 3.
Aligne le digital sur les garanties **par exemplaire** (RC-6F) : une ligne de **qty N** accorde
**N droits d'accès** au lieu d'un seul.

### Backend
- **`digital_entitlements.unit_index`** (1..N) : rang de l'exemplaire dans sa ligne (existants = n°1).
- **Émission par exemplaire** (`DigitalService::issueForOrder`) : boucle sur la quantité, jeton (et clé
  de licence issue du **pool FIFO**) distincts par unité. **Idempotence par exemplaire** — un `fulfill`
  rejoué ne crée que les rangs manquants (donc rien s'ils existent déjà).
- **Révocation au prorata** (`revokeDownToActive`) : au restock d'un retour, on ne garde actifs que
  `quantité − cumul retourné` accès (les plus anciens d'abord). **Remplace** le « tout ou rien » porté
  par la ligne (RC-5H) : un retour partiel révoque autant d'accès que d'exemplaires rendus.
- **+4 tests** `PerUnitEntitlementTest` (qty 3 → 3 accès distincts + rangs 1/2/3 ; 3 licences → 3 clés de
  pool ; ré-émission = 0 doublon ; qty 1 = 1 accès inchangé) ; test RC-5H `ReturnVoidSpecialTest`
  **réécrit** en révocation proportionnelle. Digital+Orders+Notifications **99 ✅**.

### Frontend
- Panneau « Accès digital » de la commande : affiche **« Exemplaire n°k »** quand une ligne compte
  plusieurs accès (masqué si un seul). i18n FR+EN (`orders.detail.entitlementUnit`). +1 test. vue-tsc 0.

## [Non publié] — 🔐 RC-7C : comptes clients du portail digital (3ᵉ mode d'accès) (2026-06-28)

Branche `feature/portal-accounts` (release `v1.0.0` → `rc.135`). Item 4 de la Phase 3.
Le client final peut désormais se créer un **compte** (email + mot de passe) en plus du jeton et du
lien magique — les 3 modes cohabitent sur `/portal`.

### Backend
- **`portal_accounts`** : compte global (hors multi-tenant, rapproché par email). **Vérification par
  code obligatoire avant login** — le code part via le canal du/des vendeur(s) connaissant l'email
  (`portal.verify_code`), empêchant de revendiquer l'email d'autrui.
- **`PortalAccountController`** : `POST /register` (code envoyé, réponse générique anti-énumération),
  `POST /verify` (code + expiration 30 min), `POST /login` (refusé si non vérifié → token Sanctum du
  modèle `PortalAccount`, jamais un user tenant), `GET /my-purchases` (achats digitaux actifs
  **multi-vendeurs** avec liens magiques). Throttles : 3/10 min, 5/10 min, 10/min.
- **+5 tests** `PortalAccountTest` (code via canal vendeur + login refusé tant que non vérifié, code
  faux/expiré, login vérifié → liste des achats, `my-purchases` exige un token, email inconnu →
  générique sans envoi). Digital+Notifications **38 ✅**.

### Frontend — bloc « Mon compte » dans `/portal`
- Onglets **Se connecter / Créer un compte** ; inscription → saisie du **code** → login →
  liste des achats avec liens d'ouverture ; session persistée (token portail en `localStorage`,
  distinct de l'auth opérateur). Instance axios **dédiée** (pas de redirection login tenant sur 401).
  **i18n FR+EN** (`portal.account.*`). Garde i18n ✅, vue-tsc **0 erreur**, front **271 ✅**.

## [Non publié] — 🧼 RC-7B : résorption de la dette vue-tsc — 0 erreur (2026-06-27)

Branche `feature/batch-expiry-followups`… en réalité worktree isolé (release `v1.0.0` → `rc.134`).
Item 5 de la Phase 3. **180 lignes d'erreurs TypeScript → 0**, **sans changement de comportement**.

- Environnement : `node_modules/axios` corrompu (types absents) — réparé (≈109 erreurs fantômes).
- Familles corrigées : `ImportMeta.env` (`vite-env.d.ts`), `VitestUtils` (accolades dans 17 specs),
  doublons de clés i18n masqués au runtime (4), `label?` sur `CreateVariantPayload`, code mort (9),
  gardes de nullabilité, signatures de services, `PricingInterval`.
- **1 vrai bug attrapé par le type-check** : un toast passait un objet à `pushToast(message: string)`
  → affichait « [object Object] » au lieu du message de succès (corrigé).
- Vérifications : vue-tsc 0, vitest **271 ✅**, i18n gate ✅.

## [Non publié] — 🧹 RC-7A : suites de recette — démarque automatique des lots + FEFO des composants de kits (2026-06-27)

Branche `feature/batch-expiry-followups` (release `v1.0.0` → `rc.133`).
Lève deux **limites connues** du rapport de recette.

- **`inventory:expire-batches`** (cron quotidien 00:45) : les lots `active` à DLC dépassée passent
  **`expired`** + **alerte récapitulative** au tenant (template `inventory.batches_expired` — lots à
  retirer physiquement). Ils étaient déjà exclus de la vente (fix QA) ; le statut est désormais aligné.
- **FEFO à travers les kits** : un composant de kit suivi **par lot** consomme désormais ses lots en
  FEFO au fulfill (traçabilité complète kit → lot).
- **+2 tests** (commande de démarque + alerte ; kit à composant `batch` → FEFO). BatchFefo+Kit **15 ✅**.

## [Non publié] — 🧪 QA : recette complète rc.114→131 — 6 correctifs (2 sécurité, 4 logique) (2026-06-26)

Branche `feature/qa-recette-phase-2` (release `v1.0.0` → `rc.132`).
Campagne de recette (runs de référence + audit sécurité + revue adverse par agents) — rapport :
`docs/recette/recette-phase-1-2-rc114-131.md`. **Verdict : GO.**

### Sécurité
- **ReDoS (haute)** : `validation_regex` (RC-6D) est désormais **validée à la création** — compilation
  + sonde adverse (`safeRegexRule`), longueur ≤ 120 ; erreur PCRE à l'exécution = valeur rejetée.
- **Anti-énumération (moyenne)** : `POST /portal/digital/request-links` durci `5/min` → **3 / 10 min**.

### Corrections logiques (revue adverse)
- **Billing multi-devises (critique)** : l'abondement en place ET le reversal (RC-6G) filtrent
  désormais par **`market_code`** — un acompte EUR ne peut plus écraser/décrémenter un dépôt XOF.
- **Lot périmé (critique)** : `allocateFefo` (RC-6H) **exclut les lots périmés** de la vente (ils
  restent en stock pour démarque) — ils partaient en PREMIER par le tri FEFO.
- **Retour partiel digital (critique)** : l'accès n'est révoqué que lorsque le **cumul des retours
  approuvés couvre toute la ligne** (RC-5H révoquait l'accès entier dès le premier retour partiel).
- **Anti-spam** : alerte « pool épuisé » (RC-6E) dédupliquée **24 h par produit**.
- **SMTP sans hôte** : échec explicite immédiat (visible dans `last_error`) au lieu d'une tentative absurde.

### Tests
- **+3 tests de régression** (dépôt multi-marché jamais abondé en croisé, lot périmé jamais alloué,
  retour partiel digital préserve l'accès jusqu'au retour total). Suite backend complète **869 ✅**
  (2 skipped), front **271 ✅**, i18n gate ✅.

## [Non publié] — ✨ RC-6J : assistant de création produit (optionnel) — 🎉 clôture Phase 2 (2026-06-26)

Branche `feature/product-wizard` (release `v1.0.0` → `rc.131`).
Arbitrage fondateur C : **assistant optionnel** (n'impose rien, ne remplace pas le formulaire).

### Frontend — `ProductWizardModal` (bouton « ✨ Assistant », création uniquement)
- **4 questions métier** (audit §7.1) : *Que vendez-vous ?* (physique / déclinaisons / service /
  digital / kit) → *Suivi du stock ?* (globale / par unité / par lot — sauté pour service/digital) →
  *Garantie ?* → *Livraison ?* (options filtrées par nature).
- **« Appliquer au formulaire »** pré-remplit la carte « Type & politique » (RC-5K) : type, suivi,
  livraison, toggle variantes, et **suggère la première politique de garantie active** si souhaité.
  **i18n FR+EN** (`catalog.wizard.*`). Garde i18n ✅, vue-tsc ✅, front **271 ✅**.

### 🎉 Phase 2 complète (RC-6A → RC-6J, rc.123 → rc.131)
Notifications multi-canal + SPA · portail client (3 modes) · identifiants dynamiques (18 seedés) ·
pool de licences par plan · garanties+ (jours/années, par exemplaire, extensions) · billing 6 règles
configurables · lots FEFO · kits à nomenclature · assistant produit. Voir
`docs/decisions/phase-2-arbitrages.md` (suivi ✅ complet).

## [Non publié] — 🧩 RC-6I : kits/bundles — nomenclature + consommation des composants à la vente (2026-06-25)

Branche `feature/kits-bom` (release `v1.0.0` → `rc.130`).
Arbitrage fondateur H : le `product_type=kit` (annoncé depuis Sprint 17, jamais opérationnel) devient
un **kit virtuel à nomenclature**.

### Kits — la vente opère les COMPOSANTS
- **`kit_components`** : nomenclature (composant produit/variante + quantité par kit), remplacement
  idempotent via `PUT /api/catalog/products/{id}/components` (produit `kit` only → 422, un kit ne peut
  pas se contenir), lecture `GET …/components`.
- **`OrderService`** — un kit **avec nomenclature** est virtuel : `confirm` **réserve** le stock de
  chaque composant (qty ligne × qty composant, atomique — un composant insuffisant annule tout),
  `fulfill` **consomme** (release + moveOut référencé « Kit {sku} »), `cancel` **libère**. Un kit
  **sans** nomenclature reste un produit stocké classique (compat RC-5A).
- Garanties/digital par ligne (RC-5D/5E) continuent de s'appliquer au kit lui-même.

### Tests
- **+6 tests** `KitTest` (BOM refusée hors type kit, confirm réserve 3×(2+1), fulfill consomme,
  cancel libère, composant insuffisant → 422 atomique, kit sans BOM = produit standard).
  Catalog+Orders+Inventory **248 ✅**.

## [Non publié] — 📦 RC-6H : lots & péremption — réception par lot + consommation FEFO (2026-06-24)

Branche `feature/batch-fefo` (release `v1.0.0` → `rc.129`).
Arbitrage fondateur H : le `stock_tracking=batch` (valeur posée en RC-5A, scaffold `product_batches`
jamais exploité) devient **opérationnel de bout en bout**.

### Lots — du fournisseur à la vente
- **Réception par lot** (`POST /api/inventory/products/{id}/batches`, produit `batch` only → 422) :
  n° de lot (unique par produit), DLC/DLUO, quantité — + **miroir stock agrégé** (moveIn référencé
  `batch:{n°}`), CMUP au coût du lot.
- **Consommation FEFO au fulfill** (`BatchService::allocateFefo`, branché dans `OrderService`) :
  péremption la plus proche d'abord, lots **sans date en dernier**, lot vidé → `exhausted`. Verrou
  lecture ; best-effort de traçabilité (le stock agrégé a déjà validé la quantité — un drift
  historique de lots ne bloque jamais une vente).
- **Alerte péremption** : `GET /api/inventory/batches/expiring?days=30` (lots actifs, `days_left`).
- `GET /api/inventory/products/{id}/batches` : lots du produit triés FEFO.

### Tests
- **+6 tests** `BatchFefoTest` (réception + miroir, non-batch → 422, vente 7 sur 2 lots → le plus
  proche vidé/`exhausted` puis l'autre entamé, lots sans date consommés en dernier, fenêtre
  `expiring`, doublon de n° de lot → 422). Inventory+Orders **148 ✅**.

## [Non publié] — 💳 RC-6G : billing — les 6 règles reportées, toutes configurables (2026-06-23)

Branche `feature/billing-rules` (release `v1.0.0` → `rc.128`).
Arbitrage fondateur G : « tout implémenter, mais configurable » — chaque règle a son flag
(`config/billing.php → rules.*`, pilotable par .env, défaut **ON**).

### Les 6 règles (reliquats de la revue RC-1C/RC-2)
1. **`tenant_credits`** (ledger d'avoirs signé, par devise — un avoir ne franchit jamais une devise) :
   les trop-perçus y vivent désormais (plus de `metadata['overpaid_minor']`). `TenantCreditService`
   (balance / credit / consume borné au solde).
2. **Promo → cible nette** : une promo **validée** (dates, usages, plan) rend les cibles nettes
   (`Promotion::applyDiscount`) → le paiement promo est **résolu automatiquement** (matched/partial…)
   et l'usage est enregistré à l'activation ; promo invalide → `needs_review` comme avant.
3. **Sièges additionnels** : le matching reconnaît `base + k × extra_user_amount_minor`
   (**k ≤ 100**, uniquement sur l'**intervalle déclaré** — anti-faux-positifs), sièges tracés
   (`metadata['extra_users']`, `PaymentPeriodResult::$extraUsers`).
4. **Devise ↔ moyen strict** : un moyen de paiement déclaré au référentiel (`market_payment_methods`)
   pour une autre devise → approuvé **sans activation** (`needs_review`).
5. **Acompte abondé en place** : les tranches suivantes d'un échelonnement mettent à jour le
   `past_due` existant (fini la ligne `cancelled` par tranche).
6. **Rétro-action d'acompte** : rejeter un acompte **imputé non soldé** décrémente le cumul du
   `past_due` (un paiement d'un cycle **soldé** reste non rejetable).

### Tests
- **+8 tests** `BillingRulesTest` (promo nette activée + usage, promo invalide → review, 2 sièges
  détectés, devise↔moyen → review, 2 tranches → 1 seule ligne past_due abondée, reversal décrémente,
  cycle soldé protégé, consommation d'avoir bornée). Test RC-1C trop-perçu adapté au ledger.
  Billing **116 ✅**.

## [Non publié] — 🛡️ RC-6F : garanties+ — durées jours/années, contrat par exemplaire, extensions (2026-06-22)

Branche `feature/warranties-plus` (release `v1.0.0` → `rc.127`).
Arbitrage fondateur F : **oui aux trois** évolutions garanties.

### Garanties
- **Durées en jours / mois / années** : `warranty_policies.duration_unit` (défaut `month` = compat).
  `WarrantyPolicy::endsAtFrom()` calcule l'échéance selon l'unité (denrées → jours, immobilier → années).
- **⚠️ Changement de comportement — un contrat PAR EXEMPLAIRE** : une vente agrégée qty 3 émet
  désormais **3 contrats** (au lieu d'un par ligne) — chaque appareil a sa vie SAV (retour partiel,
  réclamation individuelle). Test RC-5D mis à jour en conséquence.
- **Extensions** (`POST /api/warranties/contracts/{id}/extend` `{duration, unit, reason}`,
  manager/admin) : prolonge l'échéance (base = échéance courante si couverte, sinon **maintenant**) ;
  un contrat **expiré redevient actif**, un contrat **void n'est jamais prolongeable** (422). Audit
  `warranty.extended` (avant/après, raison — ex. n° de commande de l'extension vendue).

### Tests
- **+6 tests** `WarrantyPlusTest` (90 jours, 2 ans, qty 3 → 3 contrats, extension + audit, void
  refusé, expiré → réactivé depuis maintenant). Warranties+Orders **77 ✅**.

## [Non publié] — 🔑 RC-6E : pool de clés de licence éditeur — politiques par plan (2026-06-21)

Branche `feature/license-pool` (release `v1.0.0` → `rc.126`).
Arbitrage fondateur E : comportement à épuisement **et** limites d'import **variables selon
l'abonnement du tenant**.

### Pool de clés (`license_pool_keys`)
- **Import** (`POST /api/digital/products/{id}/license-keys`, collage/CSV une clé par ligne) :
  doublons du lot et déjà présents **ignorés** (compte `imported`/`skipped`) ; taille maximale
  **par plan** (`config/digital.php` : starter 100, pro 1000, enterprise 5000).
- **Consommation FIFO à la vente** : la clé de l'entitlement vient du pool (verrou anti
  double-assignation, rattachée à l'accès pour la traçabilité éditeur).
- **Épuisement** (politique : surcharge tenant `settings['license_pool_exhaustion']` → config par plan
  → défaut `generate`) : `generate` = repli génération RC-5E + **alerte email** au tenant
  (`digital.pool_exhausted`, seulement si le pool a déjà servi) ; `block` = la livraison **échoue
  proprement** (`LicensePoolExhaustedException` → 422, la commande reste confirmée, rollback complet).
- `GET …/license-keys/summary` : disponibles/assignées + politique + limite d'import.

### Tests
- **+6 tests** `LicensePoolTest` (import + doublons ignorés, consommation FIFO + traçabilité,
  épuisement → génération par défaut, politique `block` → échec + rollback (commande confirmée),
  limite d'import par plan → 422, produit non-licence → 422). Digital+Orders+Notifications **89 ✅**.

## [Non publié] — 🧬 RC-6D : identifiants métier dynamiques — catalogue exhaustif configurable (2026-06-20)

Branche `feature/special-attributes` (release `v1.0.0` → `rc.125`).
Arbitrage fondateur D : sortir du `serial_type` libre. Normalisation, **validation (regex)** et
**unicité** des unités sérialisées sont désormais pilotées par des **définitions configurables sans
code** (audit produits-spéciaux §6.2).

### `special_attribute_definitions`
- **Catalogue global seedé (18 définitions)** : IMEI, IMEI 2, n° de série constructeur, **adresse
  MAC**, ICCID, IMSI, MSISDN, VIN, châssis, moteur, plaque, **compteur (eau/électricité)**, batterie,
  **UDI médical**, certificat, carte de garantie, clé boîte, **lot fabricant (non unique)** + `custom`.
  Chaque définition : stratégie de normalisation (`digits_only`/`alnum_upper`/`upper_trim`/`none`),
  regex de validation, `is_unique`, aide.
- **Définitions par tenant** : création/modification via `POST/PATCH /api/inventory/special-attributes`
  (les globales sont en lecture seule) ; résolution **tenant → globale** par code.

### Réception d'unités (RC-5B) pilotée par les définitions
- `InventoryUnitService` : normalisation par définition (MAC `aa:bb:cc…` ≡ `AABBCC…`), **rejet des
  valeurs invalides** (`InvalidSerialException` → 422 avec l'aide de la définition), unicité **désactivable**
  (`lot_number` partagé par plusieurs unités). Recherche `findBySerial` alignée. Types inconnus →
  comportement RC-5B inchangé (compat).
- Migration : l'index DB strict `UNIQUE(tenant, type, valeur)` devient un index simple — l'unicité des
  types uniques reste garantie par le contrôle transactionnel applicatif (`lockForUpdate`).

### Tests
- **+7 tests** `SpecialAttributeTest` (catalogue seedé listé, MAC normalisée/dédupliquée multi-formats,
  regex IMEI rejette les invalides, lot non unique partagé, définition custom créée+utilisée+regex,
  globales en lecture seule, type inconnu → fallback). Inventory+Orders **142 ✅**.

## [Non publié] — 🌐 RC-6C : portail client digital — jeton, lien magique, « mes achats » par email (2026-06-19)

Branche `feature/digital-client-portal` (release `v1.0.0` → `rc.124`).
Arbitrage fondateur B : **les trois modes d'accès**. Le client final accède à ses achats sans compte
opérateur.

### Les 3 modes d'accès
1. **Saisie de jeton** : page publique `/portal` — le client colle son jeton → produit, clé de licence
   (copiable), **liens de téléchargement signés** (15 min, régénérables).
2. **Lien magique** : l'email de livraison (`digital.delivery`) porte désormais `{{portal_link}}` —
   un clic ouvre le portail avec le jeton pré-chargé.
3. **« Mes achats » par email** : le client saisit son email → il reçoit, **par le canal de chaque
   vendeur concerné** (multi-tenant), la liste de ses achats actifs avec leurs liens magiques
   (template global `digital.portal_links`). Réponse toujours générique (anti-énumération).

### Backend
- **`PortalController`** (public, throttlé 20/min et 5/min) : `POST /api/portal/digital/access`
  (404 inconnu, 403 révoqué/expiré), `POST /api/portal/digital/request-links` (toujours 200).
- **`DigitalService::portalLink()`** (base `config('app.frontend_url')`, nouvelle clé FRONTEND_URL).
- Migration : template `digital.portal_links` + mise à jour de `digital.delivery`.

### Frontend
- **`DigitalPortalView`** (`/portal`, route publique) : saisie/auto-chargement du jeton, clé copiable,
  téléchargements, formulaire « retrouver mes achats ». **i18n FR+EN** (`portal.*`). vue-tsc ✅.

### Tests
- **+5 tests** `PortalTest` (accès public par jeton, révoqué → 403 / inconnu → 404, « mes achats » →
  liens magiques via canal tenant, email inconnu → réponse générique sans envoi, mail de livraison
  porte le lien magique). Digital+Notifications **27 ✅**, front **271 ✅**.

## [Non publié] — 📣 RC-6A/6B : notifications sortantes multi-canal + SPA de configuration (2026-06-18)

Branche `feature/notifications-core` (release `v1.0.0` → `rc.123`). **Ouvre la Phase 2** (arbitrages
fondateur du 2026-06-17 — cf. `docs/decisions/phase-2-arbitrages.md`). Nouveau module **Notifications**.

### Backend — infrastructure complète et dynamique
- **`notification_channels`** (par tenant) : email/SMS/WhatsApp × providers `smtp` / `http_api`
  (proxy agrégateur générique : URL + headers + payload template `{{to}}`/`{{message}}`/`{{from}}`…) /
  `log`. Config **chiffrée au repos**, `from_name`/`from_address` (nom d'expéditeur, sender ID, n° court),
  un canal **par défaut** par type.
- **`notification_templates`** : modèles `{{placeholders}}`, **globaux seedés (FR)** + **surcharge par
  tenant** (résolution tenant → global — fix `IN (…, NULL)` → `orWhereNull`).
- **`notification_outbox`** : messages rendus, envoi **asynchrone** (cron 5 min
  `notifications:flush-outbox`), retry borné (3) puis `failed`, journal consultable.
- **Émetteurs branchés (best-effort, jamais bloquants)** : RC-5J (rappel/échéance/suspension → email
  de facturation du tenant) et vente **digitale** (jeton + clé de licence → email du client).
- **Endpoints** `/api/notifications` : CRUD canaux (secrets jamais exposés — `config_keys` only ;
  PATCH partiel **conserve** les secrets), test d'envoi immédiat, templates (fusion + upsert de
  surcharge), outbox paginée.

### Frontend — SPA de configuration (Paramètres → Notifications)
- **`NotificationSettingsPanel`** (remplace le placeholder « bientôt ») : 3 onglets — **Canaux**
  (formulaire adaptatif SMTP / proxy API / log, test d'envoi, défaut par type), **Modèles** (éditeur
  sujet/corps avec hint des variables, badge Global/Personnalisé), **Journal** (statuts + erreurs).
  **i18n FR+EN** (`settings.notif.*`). Garde i18n ✅, vue-tsc ✅.

### Tests
- **+9 tests** `NotificationTest` (rendu global, surcharge tenant, noop sans canal, flush http_api
  succès/3 échecs→failed, secrets protégés/conservés, endpoint test, rappel RC-5J → outbox, vente
  digitale → email client, isolation tenant). Billing+Digital+Orders+Notifications **186 ✅**.
  Front **271 ✅**.

## [Non publié] — 🧾 RC-5K : formulaire produit — type & politique pilotables depuis l'UI (2026-06-17)

Branche `feature/catalog-product-policy-form` (release `v1.0.0` → `rc.122`).
Dernier maillon UX du chantier produits spéciaux : un opérateur peut désormais **créer un service, un
produit digital, un produit sérialisé ou sous garantie sans passer par l'API**.

### Frontend — carte « Type & politique » (ProductFormView)
- Sélecteurs **« Que vendez-vous ? »** (simple/service/digital/kit — `variable` dérivé du toggle
  variantes), **suivi du stock** (auto/quantité globale/par unité IMEI-VIN, masqué pour service/digital),
  **livraison** (options filtrées par type : download/license pour digital, manual/appointment pour
  service…), **garantie** (politiques actives du tenant).
- `''` = **auto** → champ non envoyé, le serveur dérive du type (défauts RC-5A). Changer de type
  réinitialise stock/livraison. Mode édition : politique rechargée depuis l'API.
- **Stock initial masqué** pour les types non stockables (service/digital).
- **i18n FR+EN** (`catalog.productForm.policy.*`). Types front complétés (`StockTracking`,
  `FulfillmentType`, `warranty_policy_id` dans `Product`/`CreateProductPayload`).

### Backend
- `CatalogController` (store/update) : validation **`warranty_policy_id`** (uuid + appartenance tenant,
  `null` pour détacher). `CatalogResource` expose `warranty_policy_id`.

### Tests
- **+3 tests** `ProductWarrantyPolicyApiTest` (création politique complète persistée + exposée ;
  politique d'un autre tenant → 422 ; détachement par null). Catalog **94 ✅**, front **271 ✅**,
  garde i18n + vue-tsc OK.

### 📋 Phase 2 — plan d'arbitrages
- Nouveau : `docs/decisions/phase-2-arbitrages.md` — consolide **toutes les questions nécessitant une
  décision fondateur** (canal de notifications, portail client digital, wizard produit, attributs
  dynamiques, pool de licences, extensions de garantie, reliquats billing, lots/kits) avec options,
  recommandations et ordre conseillé. **Rien de cette liste n'est lancé sans indications.**

## [Non publié] — 🔁 RC-5J : billing — renouvellement & relance (cron quotidien) (2026-06-17)

Branche `feature/billing-renewals` (release `v1.0.0` → `rc.121`).
Comble le risque documenté depuis RC-0 : **aucun job de renouvellement**. Paiements manuels → pas de
prélèvement auto : le job **rappelle, dégrade, puis suspend**.

### Billing — dunning en 3 passes (`billing:process-renewals`, quotidien 01:30)
- **`RenewalService`** :
  1. **Rappels** J-7/J-3/J-1 pour les plans payants arrivant à échéance — bucket le plus précis,
     **idempotent** par période (`metadata['renewal_reminders']`), audité (`billing.renewal_reminder`).
  2. **Échéance dépassée** (`active|trialing`) : plan **gratuit** → période **roulée** d'un intervalle
     (rappels remis à zéro) ; plan **payant** → **`past_due`** (accès maintenu pendant la grâce), audité.
  3. **Grâce expirée** (7 j) : `past_due` → **`suspended`** (`renewal_overdue`) via
     `SubscriptionService::suspend`. Les **acomptes échelonnés** (`current_period_end` null — période
     jamais démarrée) ne sont **jamais** suspendus.
- **Commande** `billing:process-renewals` (résumé chiffré) + **Schedule** quotidien 01:30
  `withoutOverlapping`. Statut tenant synchronisé (`subscription_status`).

### Tests
- **+7 tests** `RenewalTest` (payant échu → past_due + audit ; gratuit échu → roulé ; grâce dépassée →
  suspendu / en grâce → intact ; acompte échelonné épargné ; rappels par bucket **idempotents** ;
  gratuit sans rappel ; commande artisan). Billing **108 ✅**.

## [Non publié] — 📥 RC-5I : produits digitaux — fichiers privés + téléchargement par lien signé (2026-06-16)

Branche `feature/digital-assets-download` (release `v1.0.0` → `rc.120`).
Complète RC-5E : un produit digital porte désormais de **vrais fichiers** livrés au client par un **lien
signé et expirable**. Aucun chemin de fichier n'est jamais exposé.

### Digital — du fichier au client
- **`digital_assets`** : fichier **privé** rattaché à un produit digital (disque privé `local`, taille,
  mime, **checksum sha256**, version). Le `path` n'est **jamais** renvoyé en API.
- **`DigitalAssetService`** : `attach()` (upload), `forProduct()`, `signedLinksFor()` (liens
  `temporarySignedRoute` 15 min pour les assets actifs d'un entitlement accessible), `findActiveAsset()`.
- **`GET /api/digital/access/{token}`** renvoie désormais `download_urls` (liens signés) en plus de la clé.
- **`GET /api/digital/download/{token}/{asset}`** — route **signée (hors auth)** : le client n'est pas un
  user du tenant, la signature porte la capacité ; l'accessibilité de l'entitlement est **revérifiée**
  (la **révocation prime** même sur un lien déjà émis → 403). Tenant dérivé du jeton (globalement unique).
- **Endpoints assets** (manager/admin) : `POST/GET /api/digital/products/{id}/assets`.

### Frontend — fiche produit
- **`ProductShowPage`** : onglet **« Fichiers digitaux »** (visible pour les produits digital) — upload +
  liste (nom, taille, statut). **i18n FR+EN** (`catalog.productShow.digital.*`). Au passage, le type
  **`digital`** est ajouté au `ProductType` front (manquait) + libellé de badge.

### Tests
- **+6 tests** `DigitalAssetTest` (upload sur disque privé + checksum + `path` non exposé ; non-digital
  refusé ; lien signé télécharge le fichier ; révoqué → 403 ; non signé → 403 ; jeton ≠ produit de
  l'asset → 404). Digital+Orders **69 ✅**.
- Front **271 ✅**, garde i18n + vue-tsc OK.

## [Non publié] — ↩️ RC-5H : retour (RMA) défait les artefacts spéciaux — void garantie / révoque accès / unité retournée (2026-06-16)

Branche `feature/returns-void-special` (release `v1.0.0` → `rc.119`).
Ferme le trou de cohérence ouvert par RC-5D→F : un **retour** laissait la garantie active et l'accès
digital actif. (Le `cancel` n'était pas concerné : contrats/accès naissent au `fulfill`, et on n'annule
que `draft|confirmed`.)

### Au restock d'un retour (RMA)
- **`SerializedAllocationService::returnUnits()`** : les unités vendues de la ligne repassent `in_stock`
  (resalable, rattachements vente/garantie effacés → réutilisables) ou `returned` (sinon, conservées
  pour traçabilité).
- **`WarrantyService::voidForReturn()`** : les contrats de la ligne (ou des unités sérialisées
  concernées) passent `active → void`.
- **`DigitalService::revokeForOrderLine()`** : les accès digitaux de la ligne passent `active → revoked`.
- **`OrderReturnService::restock()`** orchestre les trois pour **chaque** ligne approuvée (toute
  condition) ; le réabondement de stock agrégé reste réservé aux lignes **resalable** et **stockables**
  (un produit `stock_tracking=none` n'a pas de stock — fix : plus de `firstOrFail` sur un retour digital).

### Frontend
- **Aucun changement** : la fiche commande reflète déjà les nouveaux états via les libellés existants
  (garantie « Annulée », accès « Révoqué », unité « Retournée » ; le bouton SAV ne s'affiche que sur
  garantie active).

### Tests
- **+4 tests** `ReturnVoidSpecialTest` (sérialisé resalable → in_stock + void + réabondement ;
  sérialisé non-resalable → returned sans réabondement ; digital → révoqué sans crash ; agrégé sous
  garantie → void par ligne). Orders **56 ✅**.

## [Non publié] — 📊 RC-5G : reporting produits spéciaux — valorisation unité/agrégé (2026-06-15)

Branche `feature/reports-special-products` (release `v1.0.0` → `rc.118`).
Sixième et dernier pas des **produits spéciaux** : le reporting. **Clôt le chantier RC-5A→G.**

### Reporting — valoriser juste
- **`ReportService::specialProducts()`** (+ `GET /api/reports/special-products`) — valorise le stock
  **agrégé/lot** au coût et le **sérialisé par UNITÉ en stock** (pas via le miroir agrégé → **pas de
  double-comptage**), **exclut** les services/digital (`stock_tracking=none`), et rappelle l'état
  garanties (contrats actifs / SAV ouverts) et digital (accès actifs). Scopable par entrepôt.

### Frontend — rapport de stock
- **`StockReportView`** : bloc **« Produits spéciaux »** (valeur totale, sérialisé in stock/réservé/vendu,
  garanties actives + SAV ouverts, accès digitaux, non stockables exclus). Chargé séparément (silencieux).
  **i18n FR+EN** (`reports.special.*`). Garde i18n ✅, vue-tsc ✅.

### Tests
- **+3 tests** `SpecialProductsReportTest` (valorisation mixte exacte agrégé+sérialisé+exclusions+garanties/SAV/digital,
  zéros sans données, auth requise). Suite backend complète **793 ✅** (2 skipped).
- **+1 test** front `reportService.spec`. Front **271 ✅**.

> 🎉 **Chantier produits spéciaux complet (RC-5A→G)** : policy stock/livraison · unités sérialisées
> IMEI/VIN · allocation commande↔unité↔client · garanties (politique→contrat) · SAV · digital
> (entitlements) · reporting. Hors périmètre restant : `void` auto garantie sur retour, portail
> téléchargement client (lien signé), pool de licences, extensions de garantie.

## [Non publié] — 🔧 RC-5F : SAV — réclamations rattachées au contrat de garantie (2026-06-15)

Branche `feature/warranty-claims-sav` (release `v1.0.0` → `rc.117`).
Cinquième pas des **produits spéciaux** : le SAV. **Clôt le chantier garanties** (politique → contrat →
réclamation). Étend le module **Warranties**.

### SAV — du contrat à la réclamation
- **`warranty_claims`** : réclamation rattachée à un contrat (reprend `customer_id` / `inventory_unit_id`),
  `reason` (defect/breakage/malfunction/other), `status`, `resolution`, `out_of_warranty`, diagnostic.
- **`WarrantyClaimService::open()`** — **gardé par la période** : contrat `void` → refus ; contrat
  **expiré** → refus **sauf `override=true`** (alors `out_of_warranty=true` + **audit**
  `warranty.claim.out_of_period_override`).
- **`transition()`** — `open → in_repair → resolved | replaced | rejected` ; les terminaux verrouillent
  (toute transition depuis un terminal → 422). Statut terminal → `resolution`/`resolved_at` posés.
- **Endpoints** (`/api/warranties`) : `POST /contracts/{id}/claims`, `POST /claims/{id}/transition`
  (manager/admin), `GET /contracts/{id}/claims`, `GET /orders/{id}/claims`.

### Frontend — fiche commande
- **`OrderDetailView`** : sur chaque garantie active du panneau **Garanties**, action **« Ouvrir un SAV »**
  (modale motif + description) et affichage du **statut de réclamation** quand elle existe. **i18n FR+EN**
  (`orders.detail.savOpen/savTitle/savReason/savReasons.*/claimStatus.*`). Garde i18n ✅, vue-tsc ✅.

### Tests
- **+8 tests** `WarrantyClaimTest` (ouverture contrat actif, parcours réparation→résolution, blocage
  terminal, expiré sans/avec override, `void` refusé, liste par commande, motif invalide 422, isolation
  tenant 404). Warranties **15 ✅**.
- **+2 tests** front `OrderDetailView.spec`. Front **270 ✅**.
- **Suite → RC-5G** : reporting produits spéciaux, `void` auto garantie sur retour.

## [Non publié] — 💾 RC-5E : produits digitaux — vente sans stock + droits d'accès (2026-06-14)

Branche `feature/digital-entitlements` (release `v1.0.0` → `rc.116`).
Quatrième pas des **produits spéciaux** : on vend de l'**immatériel** (ebook, logiciel, licence).
Nouveau module **Digital**.

### Prérequis levé — commande sans stock
- **`OrderService`** ne réserve/sort plus de stock pour les lignes **`stock_tracking=none`** (services
  **et** digital). Avant : confirmer une commande de service/digital échouait
  (`InsufficientStockException` sur un stock à 0). `Product::isStockable()` fait autorité.

### Digital — du catalogue à l'accès client
- **`digital_entitlements`** : droit d'accès accordé au client à la vente — `access_token` **opaque**
  (jamais un chemin de fichier), `license_key` si licence, `status` (active/revoked/expired), rattaché
  client/commande/ligne/produit.
- **`DigitalService::issueForOrder()`** branché dans **`OrderService::fulfill`** : accorde un accès par
  ligne `download`/`license` (clé générée si licence). **Idempotent**.
- **Endpoints** (`/api/digital`) : `GET /orders/{id}/entitlements` (**sans secret**),
  `GET /access/{token}` (révèle le secret si **actif**, **403** si révoqué/expiré, **404** cross-tenant),
  `POST /entitlements/{id}/revoke` (manager/admin).

### Frontend — fiche commande
- **`OrderDetailView`** : panneau **« Accès digital »** (produit, type download/licence, statut),
  affiché seulement si des accès existent. **i18n FR+EN** (`orders.detail.entitlementsTitle`,
  `entitlementType.*`, `entitlementStatus.*`). Garde i18n ✅, vue-tsc ✅.

### Tests
- **+7 tests** `DigitalEntitlementTest` (digital/service commandables sans stock, accès actif→révoqué→403,
  clé licence, isolation jeton 404, liste sans secret, physique → 0 accès).
  Orders+Digital+Warranties+Inventory+Catalog+Pos **246 ✅** (2 skipped).
- **+2 tests** front `OrderDetailView.spec`. Front **268 ✅**.
- **Suite → RC-5D-bis/RC-5F** : SAV (réclamations, void sur retour), reporting produits spéciaux.

## [Non publié] — 🛡️ RC-5D : garanties — politique produit + contrat généré à la vente (2026-06-13)

Branche `feature/warranties-contracts` (release `v1.0.0` → `rc.115`).
Troisième pas des **produits spéciaux** : la garantie devient un objet métier de premier ordre. Nouveau
module **Warranties**.

### Garanties — du catalogue au contrat
- **`warranty_policies`** : politique réutilisable (durée en **mois** + couverture), `is_active`.
- **`products.warranty_policy_id`** : on attache une politique à un produit.
- **`warranty_contracts`** : contrat **effectif**, généré à la vente, rattaché au client, à la ligne de
  commande, au produit/variante et — pour le sérialisé — à l'**unité vendue** (`inventory_unit_id` +
  snapshot `serial_value`). Période **figée** : `starts_at` (date de vente) + durée → `ends_at`.
- **`WarrantyService::issueForOrder()`** branché dans **`OrderService::fulfill`** (après la vente) :
  un contrat **par unité sérialisée** vendue (+ horodatage `warranty_started_at/ends_at` sur l'unité),
  sinon **un par ligne**. **Idempotent** (pas de réémission). Aucun produit sous politique → aucun contrat.
- **Endpoints** (`/api/warranties`, auth + tenant) : `GET/POST /policies`,
  `POST /products/{id}/policy` (attacher/détacher), `GET /orders/{id}` (contrats d'une commande, scopé
  tenant → 404 cross-tenant). Écritures manager/admin.

### Frontend — fiche commande
- **`OrderDetailView`** : nouveau panneau **« Garanties »** (politique, IMEI/VIN, échéance, statut),
  affiché seulement si des contrats existent, rechargé après le fulfill. **i18n FR+EN**
  (`orders.detail.warrantiesTitle`, `warrantyUntil`, `warrantyStatus.*`). Garde i18n ✅, vue-tsc ✅.

### Tests
- **+7 tests** `WarrantyTest` (contrat/unité sérialisée + client + date de fin, contrat/ligne agrégée,
  sans politique → 0, idempotence, API politiques + rattachement, liste contrats commande, isolation
  tenant 404). Orders+Warranties+Catalog+Inventory **229 ✅** (2 skipped).
- **+2 tests** front `OrderDetailView.spec` (panneau garanties + masquage). Front **266 ✅**.
- **Suite → RC-5D-bis/E** : SAV (réclamations, void sur retour), produits digitaux, reporting.

## [Non publié] — 🔗 RC-5C : lien commande ⇄ unité sérialisée ⇄ client — produits spéciaux (2026-06-12)

Branche `feature/orders-serialized-allocation` (release `v1.0.0` → `rc.114`).
Deuxième pas des **produits spéciaux** : on vend désormais des **unités précises** (IMEI/VIN), pas
seulement une quantité. Socle RC-5A (policy) + RC-5B (`inventory_units`).

### Commandes — réservation/vente d'unités précises
- **`SerializedAllocationService`** (module Inventory) — lien commande ⇄ unité ⇄ client :
  - `allocate()` au **confirm** : réserve des unités disponibles **FIFO** (date de réception),
    `in_stock → reserved`, rattachées à `order_id`/`order_line_id`. **Verrou lecture** → deux commandes
    concurrentes ne peuvent pas réserver la même unité (**anti double-vente**).
  - `markSold()` au **fulfill** : `reserved → sold` (`sold_at` horodaté) + **rattachement client**.
  - `release()` au **cancel** : `reserved → in_stock`, rattachements effacés.
- **`OrderService`** branche ces étapes pour toute ligne `stock_tracking=serialized`. L'allocation
  unitaire **fait autorité** : si les unités disponibles manquent → `InsufficientUnitsException` (422,
  message clair) **avant** le contrôle de stock agrégé. Le **miroir agrégé** (RC-5B) reste maintenu.
- **Endpoint** : `GET /api/orders/{id}/units` — unités rattachées à la commande (traçabilité vente/SAV),
  scopé tenant (404 pour une commande d'un autre tenant).

### Frontend — fiche commande
- **`OrderDetailView`** : nouveau panneau **« Unités sérialisées »** (IMEI/VIN + statut), affiché
  uniquement pour les commandes qui en portent, rechargé après chaque action. **i18n FR+EN**
  (`orders.detail.unitsTitle`, `orders.detail.unitStatus.*`). Garde i18n ✅, vue-tsc ✅.

### Tests
- **+9 tests** `SerializedAllocationTest` (confirm réserve + miroir, fulfill vend + client, cancel
  relâche, insuffisance → rollback atomique, anti double-vente concurrente, produit non sérialisé,
  endpoint 422, `GET /{id}/units` + isolation tenant). Orders+Inventory **131 ✅** (2 skipped).
- **+2 tests** front `OrderDetailView.spec` (liste des unités + masquage si aucune). Front **264 ✅**.
- **Suite → RC-5D/E** : garanties (politiques/contrats/SAV), produits digitaux, reporting.

## [Non publié] — 🔢 RC-5B : unités sérialisées (IMEI / VIN) — produits spéciaux (2026-06-12)

Branche `feature/inventory-serialized-units` (release `v1.0.0` → `rc.113`).
Premier pas concret des **produits spéciaux** (téléphones IMEI, véhicules VIN), sur le socle RC-5A.

### Inventaire — chaque unité physique tracée individuellement
- **Table `inventory_units`** : une ligne par unité (serial_type/value + **normalized_serial**, condition,
  statut de cycle de vie, entrepôt, rattachements commande/client/garantie réservés pour la suite).
  **Unicité PAR TENANT** : `UNIQUE(tenant_id, serial_type, normalized_serial)` — le même IMEI peut
  exister chez un autre tenant, jamais deux fois chez le même.
- **`SerialNormalizer`** : `imei` → chiffres seuls ; `vin` → alphanumérique majuscules ; sinon sans
  espaces/majuscules. `« 35-9123 45678/901.2 »` ≡ `« 359123456789012 »`.
- **`InventoryUnitService::registerMany`** (atomique : doublon dans la requête **ou** en base → tout
  annulé ; verrou lecture anti-course) + incrément du **stock agrégé** (+1/unité) pour cohérence.
- **Endpoints** : `POST /inventory/products/{id}/units` (produit `serialized` only → 422 sinon ;
  périmètre entrepôt → 403), `GET …/units` (liste scopée), `GET /inventory/units/search` (par IMEI/VIN
  normalisé).

### Tests
- **+8 tests** `SerializedUnitTest` (réception + stock agrégé, doublon tenant → 422, normalisation,
  rollback lot, unicité par-tenant, produit non-sérialisé → 422, recherche normalisée, isolation tenant).
  Inventory+Catalog **175 ✅**.
- **Suite → RC-5C/D/E** : lien commande ⇄ unité (réserver/vendre une unité précise), garanties, digital.

## [Non publié] — 🧮 RC-2C : aperçu du reliquat dans l'écran d'upgrade + i18n (2026-06-11)

Branche `feature/billing-proration-ui` (release `v1.0.0` → `rc.112`).
**Clôt le chantier Pricing de bout en bout** (périodicité + acompte + proration, backend → UI).

### Frontend — « ce que vous payez après reliquat »
- **`UpgradeView`** : pour chaque plan payant différent du plan courant, appel à
  `POST /me/subscription/preview-upgrade` à la périodicité sélectionnée ; si un reliquat s'applique,
  affichage sur la carte du **reliquat appliqué** (−montant) et du **net à payer**. Silencieux si pas
  de crédit. Recalcul au changement de périodicité.
- **i18n FR+EN** : `billing.prorationCredit`, `billing.prorationNet`. Garde i18n ✅, **vue-tsc** ✅,
  **262 tests** front ✅.

## [Non publié] — 💸 RC-2B : application du reliquat à l'upgrade (acompte virtuel) (2026-06-11)

Branche `feature/billing-proration-apply` (release `v1.0.0` → `rc.111`).
Le reliquat calculé (RC-2A) est désormais **réellement appliqué** au moment du paiement.

### Billing — le client paie le net, le crédit comble le reste
- **`ManualPaymentService::approve`** : sur un **vrai upgrade** (abonnement courant actif payé, plan ou
  périodicité différents), le reliquat agit comme un **acompte virtuel** → `cash + crédit = tarif` →
  abonnement activé. Le crédit n'est consommé **que s'il solde** réellement (sinon intact) ;
  `amount_paid_minor` = **cash réel** (le crédit n'enfle pas le CA).
- **`changePlan(..., ?ProrationResult)`** : trace l'avoir appliqué (`credit_applied_minor`) et reporté
  (`credit_minor`) dans `metadata` — **émis une seule fois** (garde `didCancel` : uniquement quand le
  courant est réellement annulé), persisté dès qu'il y a un avoir appliqué **ou** reporté (pas sur
  `eligible`, sinon perte d'un avoir reporté) — **correctifs revue adverse** (bloquants).
- **Renouvellement** (même plan + périodicité) / **premier achat** → aucune proration.

### Tests
- **+4 tests** `ProrationApplyTest` (upgrade paie le net → actif + crédit tracé ; renouvellement sans
  proration ; 1er achat sans courant ; idempotence). Billing+Platform **151 ✅** (aucune régression RC-1C).
- **Suite → RC-2C** : UI upgrade crédit/net/avoir (i18n FR+EN).

## [Non publié] — 🧾 RC-2A : ProrationCalculator (reliquat d'upgrade) + preview (2026-06-11)

Branche `feature/billing-proration-calculator` (release `v1.0.0` → `rc.110`).
Conçu + **durci par revue adverse** (2 bugs d'argent BLOQUANTS + 3 majeurs corrigés avant code).

### Billing — calcul du reliquat (lecture seule)
- **`ProrationCalculator`** (pur) + DTO `ProrationResult` : à un changement de plan, valorise le **temps
  non consommé** du plan en cours et l'impute sur le nouveau. **Modèle hybride** (décision produit) :
  crédit + avoir reporté **appliqués** au tarif → le client paie le **net** ; excédent (downgrade /
  crédit > tarif) → **avoir reporté**, jamais de cash.
- Correctifs revue adverse : **arithmétique entière** pour la fraction (déterministe, bornes exactes
  jour 1 / expiré), **assiette = payé − trop-perçu** (pas de double comptage RC-1C), **garde cross-devise**
  (un avoir ne franchit jamais une devise), avoir reporté imputé même quand le crédit de temps est nul.
- **`SubscriptionService::previewProration`** (lecture seule, marché résolu depuis la devise du courant) +
  **`POST /me/subscription/preview-upgrade`** : affiche crédit / net à payer / avoir reporté avant de
  confirmer. Aucune mutation — recalculé au commit (RC-2B).

### Tests
- **+20 tests** : `ProrationCalculatorTest` (17, matrice corrigée + cas ajoutés : assiette nette du
  trop-perçu, carried cross-devise non imputé) + `PreviewUpgradeTest` (3). Billing+Platform **147 ✅**.
- **Suite (→ RC-2B/C/D)** : application réelle au commit (paiement manuel), UI crédit/net/avoir (i18n).

## [Non publié] — 🟩 RC-4B : grille de saisie de stock multi-variantes (frontend) + i18n (2026-06-11)

Branche `feature/catalog-variant-stock-grid-ui` (release `v1.0.0` → `rc.109`).
Termine la réponse à « l'entrée de stock variantes pas optimale » : saisie best-ERP en un écran.

### Catalogue — grille variantes × entrepôts
- **`VariantStockGridDrawer`** : tableau **variantes (lignes) × entrepôts (colonnes)** avec une saisie
  de quantité par cellule + un **coût d'achat unitaire** optionnel par ligne (→ CMUP). Total
  « X unités à ajouter », envoi **groupé** vers `POST /inventory/deliveries` (1 ligne par cellule non
  nulle). Recharge le stock à la validation.
- Bouton **« Saisie en grille »** sur la fiche produit (`ProductShowPage`) pour les produits à
  variantes — fini le formulaire répété par déclinaison.
- `inventoryService` : `variantStockMatrix()` + `receiveDelivery()` (+ types).
- **i18n FR+EN** : `catalog.stockGrid.*`. Garde i18n ✅, **vue-tsc** ✅, **262 tests** front ✅.

## [Non publié] — 🧮 RC-4A : matrice d'entrée de stock variantes × entrepôts (backend) (2026-06-11)

Branche `feature/catalog-variant-stock-matrix` (release `v1.0.0` → `rc.108`).
Réponse à « l'entrée de stock sur les produits à variantes n'est pas optimale » — socle de la grille
best-ERP (front : RC-4B).

### Inventaire / Catalogue
- **`POST /inventory/deliveries`** accepte désormais **`warehouse_id` par ligne** (réception ciblée par
  site) + `unit_cost_cents` (CMUP). `InventoryService::receiveDelivery` route chaque ligne vers le bon
  entrepôt (via le `findOrCreate(warehouse_id)` de RC-3A). **Périmètre d'accès** : un opérateur restreint
  ne peut réceptionner que dans ses sites autorisés (sinon **403**).
- **`GET /catalog/products/{id}/variant-stock-matrix`** : matrice variantes × entrepôts accessibles
  (cellule = quantité / dispo / CMUP) pour peupler la grille de saisie. **422** si produit non stockable.

### Tests
- **+4 tests** `VariantStockMatrixTest` (matrice listée, batch routé par entrepôt + CMUP, 403 hors
  périmètre, 422 service). Catalog+Inventory **167 ✅**.

## [Non publié] — 🗓️ RC-1D : toggle Mensuel/Annuel (landing + upgrade) + i18n (2026-06-11)

Branche `feature/pricing-interval-frontend` (release `v1.0.0` → `rc.107`).
Rend visible côté utilisateur toute la périodicité backend (RC-1A→C).

### Frontend — choix de la périodicité
- **`LandingView`** (page publique) et **`UpgradeView`** (module) : nouveau **toggle Mensuel / Annuel**
  qui recharge les tarifs (`fetchPublicPricing({ interval })`). Sur l'annuel : **équivalent mensuel**
  (« ≈ X / mois ») + **badge d'économie** (`savings_pct`) par plan, pastille « 2 mois offerts ».
- **`publicPricingService`** : param `interval` + types `PricingInterval`, `interval` racine et champs
  d'économie (`monthly_equivalent_minor`, `savings_amount_minor`, `savings_pct`) sur le prix.
- **i18n FR+EN** : `billing.perYear`, `billingMonthly/Annual`, `annualSavePill`, `annualEquivalent`,
  `saveBadge`, `annualBillingIn`, `billingPeriod` (UpgradeView ; la landing reste FR, hors périmètre garde).

### Qualité
- Garde i18n (parité FR/EN + pas de FR en dur dans les vues de module) ✅, **vue-tsc** ✅,
  **262 tests** front ✅.

## [Non publié] — 🔎 RC-1C : détection de périodicité & acompte échelonné (PaymentPeriodResolver) (2026-06-11)

Branche `feature/pricing-period-detection` (release `v1.0.0` → `rc.106`).
Conçu + **durci par revue adverse multi-agents** (workflow : 2 bugs d'argent BLOQUANTS corrigés avant code).

### Billing — le système détecte la périodicité payée et met à jour l'abonnement
- **`PaymentPeriodResolver`** (pur) + DTO `PaymentPeriodResult` + support `Markets` (devise↔marché↔exposant).
  À l'approbation d'un paiement manuel, la périodicité (mensuel/annuel) est **détectée du montant** vs les
  prix du plan/marché, **tolérance ±1 %** (bruit mobile money/FX), annuel testé avant mensuel.
- **Acompte échelonné** : versements partiels → abonnement **`past_due`** (période non démarrée, modules non
  activés, reste dû tracé) ; ils **s'accumulent** (clé stable `tenant+plan+market`, cumul des seuls acomptes
  non soldés) ; au solde → **`active`**, `current_period_start` repris du **1er acompte**, cycle clôturé
  (`settled`). Un **renouvellement** repart de zéro (jamais compté comme avoir).
- **Trop-perçu** uniquement au-delà de la **plus grande** cible → avoir mergé dans
  `subscriptions.metadata['overpaid_minor']`. **Zone morte** mensuel↔annuel = acompte vers l'annuel.
  **Promo → `needs_review`**, **devise inconnue → `unmatched`** (approuvé sans activation auto).
- **Garde-fou d'idempotence** (`approve()` ne traite qu'un paiement `pending`) — corrige un double
  `changePlan` latent. **`changePlan(..., bool $settle, ?periodStart)`** : `active`|`past_due`, période
  démarrée seulement au solde. `manual_payments` enrichi (migration additive : `market_code`,
  `declared/detected_interval`, `target/remaining_due/overpaid_minor`, `resolution_status`, `applied_at`).
- API `POST /api/me/manual-payments` accepte `market_code` (hint validé vs devise) + `interval` déclaré.

### Tests
- **+28 tests** : `PaymentPeriodResolverTest` (18, matrice corrigée : tolérance≠trop-perçu, overpaid
  seulement au-delà de la plus grande cible, zone morte→annuel, free/unmatched/promo, cumul) +
  `ManualPaymentDetectionTest` (10, bout-en-bout : full/partial/2-acomptes/idempotent/overpaid/renouvellement/
  unmatched/promo). Billing+Platform **127 ✅**.
- **Hors périmètre (→ RC-2)** : abondement d'acompte en place, cible nette après promo, sièges sup.,
  table d'avoirs dédiée, proration d'upgrade avant fin.

## [Non publié] — 🏷️ RC-5A : politique produit stock/livraison (socle produits spéciaux) (2026-06-10)

Branche `feature/catalog-product-stock-policy` (release `v1.0.0` → `rc.105`).
Fondation P0 des **produits spéciaux** (services non stockables, digital, sérialisé IMEI/VIN, garanties — RC-5B→E).

### Catalogue — politique serveur explicite (3 axes orthogonaux)
- Nouvelles colonnes `products.stock_tracking` (`none|aggregate|batch|serialized`, défaut `aggregate`) et
  `fulfillment_type` (`none|manual|delivery|download|license|appointment`, défaut `delivery`) — distinctes
  de `product_type` (nature commerciale). Index `(tenant_id, stock_tracking)`.
- **Data-migration** : les services existants → `none`/`manual` (non stockables). Autres produits
  inchangés (`aggregate`/`delivery`).
- **`Product`** : constantes `STOCK_TRACKING_*` / `FULFILLMENT_*`, type `digital` ; hook `booted()` qui
  **dérive** la politique du type à la création (service/digital → `none`) sur **tous** les chemins
  (API, duplication, seeders) ; `isStockable()` **fait autorité** (un service/digital n'est jamais
  stockable, même avec une donnée héritée `aggregate`) ; `isDigital()`, `isSerialized()`.
- **API** `POST/PATCH /catalog/products` accepte `product_type` (+ `digital`), `stock_tracking`,
  `fulfillment_type` (whitelist). `CatalogResource` expose `stock_tracking`, `fulfillment_type`,
  `is_stockable`, `is_serialized`.

### Tests
- **+6 tests** `ProductStockPolicyTest` (défauts par type, non-stockable service/digital, sérialisé
  honoré, autorité du type sur donnée héritée, `none` explicite). Catalog+Orders+Inventory+Reports **261 ✅**.

## [Non publié] — 🗓️ RC-1B : abonnement périodique (changePlan interval → +1 mois / +1 an) (2026-06-10)

Branche `feature/pricing-changeplan-interval` (release `v1.0.0` → `rc.104`).

### Billing — `SubscriptionService::changePlan`
- Nouveau paramètre **`interval`** (`monthly`|`yearly`, défaut mensuel, valeur hors whitelist →
  mensuel) : la **fin de période** est calculée `now()->addMonth()` (mensuel) ou `now()->addYear()`
  (annuel), au lieu d'un `addMonth()` **codé en dur**. La **périodicité est persistée** sur le nouvel
  abonnement (colonne `interval` du socle RC-0).
- **`AdminTenantController::changePlan`** accepte un `interval` optionnel (`in:monthly,yearly`).
- L'approbation d'un paiement manuel reste mensuelle par défaut — la **détection** de la périodicité
  d'après le montant encaissé arrive en RC-1C.

### Tests
- **+3 tests** `SubscriptionServiceTest` : défaut mensuel (+1 mois) ; annuel (+1 an + `interval`
  persisté) ; interval inconnu → repli mensuel. Billing + Platform **99 ✅**.

## [Non publié] — 💳 RC-1A : API pricing publique — périodicité annuelle + économie (2026-06-10)

Branche `feature/pricing-interval-public-api` (release `v1.0.0` → `rc.103`).
Débloque le toggle Mensuel/Annuel de la landing et de l'écran d'upgrade (RC-1D).

### Billing — `GET /api/public/pricing`
- **Whitelist `interval`** : accepte `monthly` **et `yearly`** (avant : `yearly` était silencieusement
  ramené au mensuel) ; toute autre valeur (`weekly`…) retombe sur `monthly`. La réponse expose
  désormais `interval` à la racine.
- **Économie annuelle** : sur `interval=yearly`, chaque prix porte `monthly_equivalent_minor`
  (`round(base/12)`), `savings_amount_minor` (`12×mensuel − annuel`, plancher 0) et `savings_pct`.
  Calculé à partir du **mensuel réel du marché** (pas d'un ratio figé). Plan gratuit → 0 sans
  économie fictive. En mensuel, ces champs sont absents.

### Tests
- `PublicPricingApiTest` : test « unsupported interval » **inversé** (yearly = supporté, renvoie
  l'annuel + l'économie) ; **+2 tests** (plan gratuit annuel à 0 ; périodicité hors whitelist
  `weekly` → mensuel, sans champ d'économie). Billing **46 ✅**.

## [Non publié] — 📦 RC-3A : socle stock multi-entrepôt (warehouse_id dans la clé de résolution) (2026-06-10)

Branche `feature/inventory-stock-foundation` (release `v1.0.0` → `rc.102`).
Prérequis des chantiers **grille de stock par variante** (RC-4) et **produits spéciaux** (RC-5).

### Inventaire — bug fondation corrigé
- **`StockService::findOrCreate`** intègre désormais **`warehouse_id`** dans sa clé de résolution
  (l'index unique DB est `tenant+warehouse+product+variant`). Avant : le param était ignoré → la
  méthode pouvait renvoyer le stock d'un **autre entrepôt** ou créer une ligne **`NULL` orpheline**
  (invisible des listes filtrées par site).
  - `warehouseId` fourni → ligne dans cet entrepôt ; omis → **entrepôt par défaut** du tenant
    (`is_default`, sinon le plus ancien) ; tenant sans entrepôt → ligne `NULL` (compat mono-site).
  - Nouveau `StockService::defaultWarehouseId(tenantId)`.
- **Entrée de stock manuelle** (`POST /inventory/stock/{id}/move-in`) accepte `warehouse_id`
  (vérifié : appartenance tenant + périmètre `WarehouseScope` → 404/403) et `unit_cost_cents`
  (alimente le **CMUP** perpétuel). `MoveStockRequest` étendu.
- **`CatalogController::initialStock`** : `warehouse_id` passé **dans** `findOrCreate` (au lieu d'un
  `update()` post-hoc qui pouvait violer la contrainte unique / déplacer une ligne existante) +
  garde d'appartenance tenant de l'entrepôt.

### Tests
- **+4 tests** `StockWarehouseFoundationTest` : une ligne distincte par entrepôt pour un même SKU
  (idempotent), résolution de l'entrepôt par défaut, repli `NULL` sans entrepôt, CMUP par entrepôt.
- Suites Inventory + Orders + Catalog vertes (228 ✅ / 2 skipped). Aucune régression.

## [Non publié] — 💰 RC-0 : socle Billing périodicité (prix annuels + colonnes abonnement) (2026-06-10)

Branche `feature/billing-rc0-foundation` (release `v1.0.0` → `rc.101`).

### Billing — fondation périodicité (mensuel/annuel)
- **`PlansSeeder`** : ajout des **prix annuels** (`interval='yearly'`, `base_amount_minor` = mensuel ×10,
  `extra_user` ×10) pour **tous les plans × marchés** (la colonne `plan_prices.interval` + l'unique
  `(plan_id, market_code, interval)` existaient déjà). 0 reste 0 (plan gratuit). Idempotent.
- **`subscriptions`** : nouvelles colonnes `interval` (défaut `monthly`), `currency`, `market_code`,
  `amount_paid_minor` → l'abonnement porte sa **périodicité** et la **trace du paiement** qui l'a
  activé (détection périodicité + proration à venir). Modèle `Subscription` (+ constantes `INTERVAL_*`).
- **Socle partagé** par les chantiers Périodicité (RC-1) et Proration (RC-2) — livré une seule fois.

### Plan de build
- Nouveau **`docs/decisions/pricing-catalog-build.md`** : plan vivant des 4 sous-chantiers (pricing
  périodicité/proration + stock variantes + produits spéciaux), défauts produit adoptés, bugs fondation
  débusqués (`StockService::findOrCreate` ignore `warehouse_id` ; l'API pricing force le mensuel), suivi par RC.

### Tests
- **+2 tests** `PlanYearlyPricingTest` (chaque mensuel a son annuel = ×10 ; l'abonnement porte `interval`,
  défaut mensuel). Backend **682** (680 ✅ / 2 skipped). DemoSeeder (full seed) vert.

## [Non publié] — 🛡️ UX-07 : garde anti-perte sur l'onboarding (2026-06-09)

Branche `feature/ux07-onboarding-guard` (release `v1.0.0` → `rc.100`).

### UX (gate UX/UI P1 — UX-07 complété)
- **`OnboardingView`** : câblage de `useUnsavedChanges` (déjà en place sur `ProductFormView` et
  `OrderCreateView`). La garde anti-perte (prompt `beforeunload` + confirmation `onBeforeRouteLeave`)
  s'active **dès que l'utilisateur a commencé l'assistant** (étape > 1, type d'entreprise ou nom saisi)
  et se **lève après un provisioning réussi** (pour ne pas bloquer la redirection finale vers le
  tableau de bord). Message i18n `onboarding.leaveConfirm` (FR+EN).
- **Gate UX/UI P1 — UX-07 : ✅** (les 3 formulaires critiques sont désormais protégés).

### Tests
- Frontend **262** ✅ · `vue-tsc` propre · `npm run i18n:check` vert (parité FR/EN, `OnboardingView` scannée).

## [Non publié] — ✅ Test : suite backend 100 % verte (fix `ImportModuleTest` adresse) (2026-06-09)

Branche `fix/import-address-test` (release `v1.0.0` → `rc.99`).

### Tests
- **`ImportModuleTest::customer_import_stores_…`** : l'assertion attendait l'adresse client importée
  sous forme de **string**, alors que `Customer.address` est une **colonne json `{street,city,zip,country}`**
  (cast `array`) — l'import alimente correctement le champ `street`. Assertion alignée sur le modèle
  canonique (+ test renommé `…_structured_address_…`). **Aucun changement de code de production.**
- Résultat : **backend 680 → 678 ✅ / 2 skipped / 0 échec** (suite entièrement verte).

## [Non publié] — 🐛 Fix : onboarding étape 6 — URL de provisioning (2026-06-09)

Branche `fix/onboarding-provision-url` (release `v1.0.0` → `rc.98`).

### Correctif
- **`OnboardingView`** : l'appel de provisioning ciblait `'/workspace/provision'` (sans préfixe `/api`),
  donc partait vers le serveur Vite (`localhost:5173/workspace/provision`) → **404** et « Une erreur est
  survenue » à l'étape 6. Corrigé en **`'/api/workspace/provision'`** (route backend
  `WorkspaceController::provision`, conforme à toutes les autres requêtes `/api/...`). Bug pré-existant.
- Scan complet du front : **aucune autre requête** ne manquait le préfixe `/api`.

## [Non publié] — 💳 P6 : sélecteur de paiement par marché au checkout commercial (frontend) (2026-06-09)

Branche `feature/p6-commercial-selector` (release `v1.0.0` → `rc.97`).

### UX (paiements) — P6-2 commercial complet (front + back)
- **`OrderDetailView`** — modal « Enregistrer un paiement » : le sélecteur de méthode propose désormais
  les **moyens spécifiques du marché** (Wave/Orange Money/MTN MoMo/M-Pesa/virement/carte…) avec leur
  **mode** (validation manuelle / sur devis), récupérés via `fetchPublicPaymentMethods`. À la validation,
  le moyen est posté en **`provider`** (le backend en dérive la catégorie canonique `Payment.method` —
  rc.96). **Dégradation gracieuse** : si l'API est injoignable, le `<select>` canonique d'origine reste
  affiché et fonctionnel (compat totale). Réutilise l'i18n `billing.payMethods.*` (aucune nouvelle clé).
- `RecordPaymentPayload` : `method` rendu optionnel + champ `provider`.

### Tests
- Frontend **262** ✅ (OrderDetailView spec inclus, repli gracieux) · `vue-tsc` propre · `npm run i18n:check` vert.

### Bilan P6
- **P6-1→P6-4 livrés** : socle marché↔moyens, affichage abonnement + commercial (front+back), infra
  passerelle + webhooks (inerte), adaptateur de référence Flutterwave. **Seule l'activation d'un PSP réel
  reste une décision fondateur** (clé + flag). DoD « chaque devise = un flux ou une mention » satisfait.

## [Non publié] — 💳 P6 : checkout commercial — moyen spécifique par marché (backend) (2026-06-09)

Branche `feature/p6-commercial-provider` (release `v1.0.0` → `rc.96`).

### Paiements — enregistrement d'un moyen spécifique
- **`PaymentMethodCatalog`** : pont `provider` (moyen marché : wave/orange_money/mtn_money/mpesa/
  bank_transfer/card/cash) → **catégorie canonique `Payment.method`** (mobile_money/transfer/card/cash).
- **`POST /api/payments`** accepte désormais un champ optionnel **`provider`** (alternatif à `method`) :
  il dérive la catégorie canonique `Payment.method` **et** trace le provider en `reference` (si vide).
  L'enum `Payment.method` est **inchangé** ; la compat ascendante (`method` direct) est préservée.
  Permet à tout checkout commercial d'offrir un moyen précis par marché sans churn de schéma.

### Périmètre / suite
- Backend prêt. **Reste** : sélecteur de paiement frontend dans `PosView`/commande, consommant
  `/api/public/payment-methods` et postant `provider` (UI dense — incrément ultérieur).

### Tests
- **+5 tests** `PaymentProviderTest` (provider→catégorie + traçage, référence explicite conservée,
  `method` legacy, ni l'un ni l'autre → 422, mapping du catalogue). Backend **680** (677 ✅ / 2 skipped /
  1 échec pré-existant hors périmètre). Payments suite 50 ✅.

## [Non publié] — 💳 P6-4 : adaptateur de référence Flutterwave (inerte) (2026-06-09)

Branche `feature/p6-4-flutterwave-ref` (release `v1.0.0` → `rc.95`).

### Paiements — premier adaptateur PSP (référence, AUCUN appel réseau réel)
- **`FlutterwaveGateway implements PaymentGateway`** (couverture panafricaine : cartes + Mobile Money
  Nigeria/Ghana/Kenya/UEMOA…) : `initiate` (`POST /v3/payments` → intention + lien de redirection),
  `verify` (`/v3/transactions/verify_by_reference` → statut canonique succeeded/failed/pending),
  `refund` (`POST /v3/transactions/{ref}/refund`). Lit `base_url`/`secret_key` depuis `config/billing.php`.
- **Inerte par défaut** : enregistré dans `PaymentGatewayManager`, mais `get('flutterwave')` reste **refusé
  tant que `gateways_enabled=false`** et n'appelle rien tant que la `secret_key` n'est pas renseignée.
- **Activation (décision fondateur)** : renseigner `FLUTTERWAVE_SECRET_KEY` + `PAYMENT_GATEWAYS_ENABLED=true`
  + le secret webhook, puis basculer le `mode` du marché `manual`→`auto`. ⚠️ Vérifier le contrat exact
  contre la doc Flutterwave v3 avant production. Variante **Stripe/Paystack** = même patron.

### Tests
- **+5 tests** `FlutterwaveGatewayTest` (HTTP mocké via `Http::fake`/`fakeSequence`) : résolution par le
  manager (flag on), `initiate` pending+lien, échec prestataire, mapping de statut, remboursement.
  Backend **675** (672 ✅ / 2 skipped / 1 échec pré-existant hors périmètre).

## [Non publié] — 💳 P6-3 : infra passerelle de paiement + webhooks (inerte, post-1.0) (2026-06-09)

Branche `feature/p6-3-gateway-infra` (release `v1.0.0` → `rc.94`).

### Paiements — fondation PSP (AUCUN rail réel actif)
- **`config/billing.php`** : secrets webhooks par prestataire (vide ⇒ refusé) + **feature flag
  `gateways_enabled` (false par défaut)**. Corrige le code mort signalé par le cadrage (le middleware
  `VerifyWebhookSignature` lisait une config inexistante).
- **Abstraction `PaymentGateway`** (`code`/`initiate`/`verify`/`refund`) + **`ManualGateway`** (référence,
  approche A — aucun appel externe) + **`PaymentGatewayManager`** : `manual` toujours dispo ; tout rail
  réel **refusé tant que le flag est false**.
- **Webhook signé** : `PaymentWebhookController` (no-op, accuse réception) + routes
  `POST /api/webhooks/payments/{provider}` **enregistrées uniquement si le flag est actif** (hors auth,
  protégées par `webhook.signature`). Défaut sûr : route absente (404) tant que désactivé.

### P6-4 (intégration PSP réelle) — bloqué décision fondateur
- L'abstraction + l'adaptateur de référence sont en place. Une intégration **live** (Flutterwave/Paystack/
  Stripe…) exige des **décisions fondateur** (PSP cible, marché prioritaire, comptes marchands) et des
  **secrets** non disponibles → **non implémentable en code à ce stade**. Le socle est prêt à l'accueillir.

### Tests
- **+9 tests** : `PaymentGatewayTest` (manuel toujours dispo, rail réel refusé flag off, inconnu rejeté,
  manuel sans appel externe) · `PaymentWebhookInfraTest` (signature valide/invalide/replay/non-configuré,
  route absente quand désactivé). Backend **670** (667 ✅ / 2 skipped / 1 échec pré-existant hors périmètre).
  Payments suite 40 ✅.

## [Non publié] — 💳 P6-2 : affichage des moyens de paiement par marché (checkout abonnement) (2026-06-09)

Branche `feature/p6-2-checkout-display` (release `v1.0.0` → `rc.93`).

### UX (paiements) — moitié visible du DoD
- **`UpgradeView`** : nouvelle section **« Moyens de paiement disponibles »** alimentée par l'API P6-1
  (`fetchPublicPaymentMethods`), affichant chaque moyen du marché avec un **badge de mode** —
  *Validation manuelle* (`manual`) / *Sur devis* (`quote`) / *En ligne* (`auto`, futur). Remplace la
  mention éditoriale en dur par des données structurées (source de vérité = l'API). Dégradation
  gracieuse (section masquée) si l'API est injoignable. Rechargé au changement de marché.
- Service `fetchPublicPaymentMethods` (raw fetch public, même patron que `fetchPublicPricing`).
- i18n `billing.payMethods.*` (FR+EN) : titres, modes, libellés de méthodes (Wave/Orange Money/MTN
  MoMo/M-Pesa/virement/carte/espèces).

### Périmètre / suite
- Couvre le **checkout abonnement** (UpgradeView). Le **checkout commercial** (sélecteurs POS/commande +
  mapping fournisseur→`Payment.method`) reste un incrément ultérieur (touche des enums validés par tests).

### Tests
- **+2 tests** `publicPricingService.spec` (fetch payment-methods, dégradation). Frontend **262** ✅ ·
  `vue-tsc` propre · `npm run i18n:check` vert (parité FR/EN, `UpgradeView` scannée).

## [Non publié] — 💳 P6-1 : socle moyens de paiement par marché (zéro PSP) (2026-06-09)

Branche `feature/p6-1-payment-methods` (release `v1.0.0` → `rc.92`).

### Paiements (P6-1 — premier incrément, NO-GO respecté)
- **Table `market_payment_methods`** (calquée sur `plan_prices`, référence plateforme) : `market_code`,
  `country_code` (override, inutilisé au départ), `currency`, `method`, **`mode` = `auto`/`manual`/`quote`**,
  `is_active`, `display_order`. Modèle `MarketPaymentMethod`.
- **Seeder** des **10 marchés** (mêmes codes/devises que `PublicPricingController::MARKETS` — pas de 4ᵉ
  source de vérité) — **tout en `manual`/`quote`, AUCUN rail PSP réel** (NO-GO commercial respecté).
  Idempotent, branché dans `DatabaseSeeder`.
- **`GET /api/public/payment-methods?market=…|country=…`** : résout le marché comme `/public/pricing`
  (repli `global`) et renvoie les moyens + `has_auto` (faux à ce stade). **Matérialise le DoD** : chaque
  devise renvoie ≥1 moyen (flux manuel OU mention sur devis).
- L'endpoint réutilise `MARKETS`/`resolveMarket` du contrôleur pricing — source de vérité unique.

### Tests
- **+5 tests** `PublicPaymentMethodsTest` (chaque marché ≥1 moyen dans sa devise · `waemu`→wave+orange_money ·
  résolution par pays · repli `global`/USD · **aucun `auto` à ce stade**). Backend **661 tests**
  (658 ✅ / 2 skipped / 1 échec pré-existant hors périmètre). Billing suite 44 ✅.

### Suite
- P6-2 : brancher le checkout déclaratif (sélecteurs depuis l'API, mention manuel/sur-devis) + admin + i18n.

## [Non publié] — 🧬 Catalogue : duplication produit/catégorie — wizard frontend (P1, complet) (2026-06-09)

Branche `feature/catalog-duplication-wizard` (release `v1.0.0` → `rc.90`).

### UX (catalogue) — fonctionnalité P1 désormais complète (backend rc.89 + frontend rc.90)
- **`ProductDuplicationWizard.vue`** (modal `BaseModal`) : récupère l'aperçu serveur (sans persistance) et
  affiche, avant confirmation, le nom de la copie, le nombre de variantes/attributs, et des **tags
  catégorisés** « Régénéré » (SKU, code interne, slug…) / « Vidé » (barcode, GTIN) / « Non copié »
  (stock, mouvements, séries, garanties…). Confirmation → crée la copie en **brouillon**.
- Actions **« Dupliquer »** ajoutées dans `ProductShowPage` (→ redirige vers le nouveau produit) et
  `CategoryListView` (→ recharge la liste). `productService.duplicate*` (produit & catégorie, preview + create).
- Nouveau namespace i18n `catalog.duplicate.*` (FR+EN) — labels d'actions, intro, et map `field.*` des
  codes serveur. Le composant est entièrement bilingue (DoD i18n).

### Tests
- **+3 tests** `productService.spec` (duplicate-preview / duplicate produit, duplicate catégorie).
  Frontend **260** ✅ · `vue-tsc` propre · `npm run i18n:check` vert (parité FR/EN).

### Gate catalogue
- **Duplication produit/catégorie assistée : LIVRÉE** (politique serveur §5bis + endpoints + wizard + 11 tests).

## [Non publié] — 🧬 Catalogue : duplication produit/catégorie — backend (P1) (2026-06-09)

Branche `feature/catalog-duplication-backend` (release `v1.0.0` → `rc.89`).

### Catalogue (gate P1 — fonctionnalité absente jusqu'ici)
- **`ProductDuplicationService`** (politique serveur opposable §5bis) : le backend est source de vérité.
  - **Régénère** `sku` + `internal_barcode` (via `CatalogService`), les `sku` de variantes, le `slug` de catégorie.
  - **Vide** `barcode` + `gtin` ; **force** `status` à `draft` ; nom suffixé « (copie) ».
  - **Copie** la config catalogue (prix, coût, description, type, catégorie, fournisseur) + les **attributs/valeurs**
    (remap `product_id`) + les **variantes** (pivot attribut↔valeur re-lié).
  - **Ne copie JAMAIS** stock, mouvements, lots, séries/IMEI/VIN, garanties, licences. **Transactionnel** (rollback total).
- **Endpoints** (gardes RBAC `manager|admin|products.*`, isolation tenant, quota produits sur la création) :
  `POST /api/catalog/products/{id}/duplicate-preview` · `/duplicate` · `categories/{id}/duplicate-preview` · `/duplicate`.
  Audit : action `product.duplicated` (n'altère pas l'historique source).

### Tests
- **+8 tests** `ProductDuplicationTest` (preview sans persistance, régénération identifiants + status draft, variantes
  SKU régénérés/barcode vidé, attributs/valeurs + pivot re-lié, **non-copie stock/mouvements**, catégorie nœud-seul
  slug régénéré, **RBAC member → 403**, **isolation tenant → 404**). Suite Catalogue **81 ✅** (215 assertions).
  Backend **656 tests** (653 ✅ / 2 skipped / 1 échec pré-existant hors périmètre).

### Suite
- Frontend `ProductDuplicationWizard.vue` + actions « Dupliquer » (`ProductShowPage`/`CategoryListView`) → **rc.90**.

## [Non publié] — 🔒 Sécurité : warehouse-scoping des GET unitaires (Sprint 20) (2026-06-09)

Branche `feature/sec-warehouse-scoping` (release `v1.0.0` → `rc.86`).

### Sécurité (bloquant Go/No-Go levé)
- **Fuite par UUID connu fermée** : les accès à une ressource unique (`GET /api/orders/{id}`,
  `GET /api/payments/{id}`, `GET /api/orders/{orderId}/payments`) étaient **tenant-scopés mais pas
  warehouse-scopés** — un opérateur restreint à l'agence A pouvait ouvrir une commande/paiement de
  l'agence B en devinant/connaissant son UUID. Désormais le scoping par agence (`user_warehouses`)
  s'applique aussi aux ressources unitaires, à l'identique des listes (`index`/`paginate`).
- `OrderService::findById()` et `PaymentService::findOrFail()` acceptent un paramètre
  `?array $warehouseIds` (null = admin/manager non restreints ; `[]` = refus). Les contrôleurs passent
  `WarehouseScope::resolve($user, null)`. Les mutations `confirm`/`fulfill`/`cancel` (déjà gated
  `orders.manage`) sont aussi scopées par défense en profondeur (un rôle custom restreint resterait
  borné à ses agences). Réponse **404** (jamais de fuite d'existence), cohérente avec le reste.

### Tests
- **+4 tests** dans `WarehouseAccessScopingTest` (HTTP) : un opérateur restreint ne peut PAS afficher
  la commande / le paiement / les paiements de commande d'un autre site (404) mais voit les siens
  (200) ; un manager voit tout. Backend **648 tests** (645 ✅ / 2 skipped) après ajout.
- ⚠️ 1 échec **pré-existant et hors périmètre** : `ImportModuleTest::customer_import_stores_address_string_and_notes`
  (normalisation d'adresse client string vs array, issu du travail concurrent `1dd3af3`) — reproduit en
  isolation, sans rapport avec ce correctif. Suivi séparément.

## [Non publié] — 🎉 i18n : module Customers — COUVERTURE 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-customers` (release `v1.0.0` → `rc.85`).

### UX (i18n)
- **Module Customers internationalisé** (verrou de session concurrente levé) : `CustomerListView`
  (liste, recherche, table + `data-label` mobiles, modal créer/éditer, pagination) et
  `CustomerDetailView` (fiche, mode édition + adresse, commandes liées, sidebar stats, actions).
- Nouveau namespace `customers.*` (FR+EN). Mutualise massivement `common.*` (email/phone/notes/
  actions/status/amount/date/pageOf/previous/next/cancel/edit/update/create/saving) et réutilise
  `orders.status.*` pour les statuts de commande. `confirm()` de suppression et erreurs via `t()`.

### 🎉🎉 Jalon — couverture i18n 100 %
- **48/48 vues de module internationalisées FR+EN.** L'`ALLOWLIST` de la garde CI est désormais
  **vide** : l'anti-régression scanne toutes les vues sans exception. Le maintien à 100 % est garanti
  par la Definition of Done (toute vue naît bilingue) + la garde.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **48 ✅ / 0 🟡 / 0 ⬜ (100 %)**.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert (allowlist vide).

## [Non publié] — i18n : Inventaire restant — objectif traduisible atteint (UX-13) (2026-06-09)

Branche `feature/ux-i18n-inventory-rest` (release `v1.0.0` → `rc.84`).

### UX (i18n)
- **Inventaire à 100 %** : `BatchDeliveryView` (réception multi-produits : référence, recherche
  produit, quantités, validation, succès/erreur) et `MovementHistoryView` (carte résumé stock,
  filtre par type, timeline des mouvements, pagination).
- Extension `inventory.*` : `delivery.*` (formulaire de réception, pluriels `{count} article(s)`,
  message de succès `{count} mouvement(s)`) et `history.*` (titre, types `in/out/adjustment/return`,
  motifs `delivery/sale/return/loss/count/manual`, états vides). Réutilise les libellés stock
  existants (`quantity/reserved/available/lowThreshold/lowStock/backToStock`) et `common.*`
  (pageOf/previous/next/saving/delete). Maps `typeLabel`/`reasonLabel` converties via `t()`.

### 🎉 Jalon — objectif i18n traduisible atteint
- **46/48 vues internationalisées FR+EN (96 %).** Les 2 seules vues restantes (`CustomerListView`,
  `CustomerDetailView`) sont **exclues** (module Customers sous session concurrente). L'`ALLOWLIST`
  de la garde CI ne contient plus que ces 2 vues. Clôture à 100 % conditionnée à la levée du verrou.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **46 ✅ / 0 🟡 / 2 ⬜ (exclues)** (96 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Admin secondaire (UX-13) (2026-06-09)

Branche `feature/ux-i18n-admin-secondary` (release `v1.0.0` → `rc.83`).

### UX (i18n)
- **Admin secondaire internationalisé à 100 %** : `AdminDashboardView` (KPIs tenants/utilisateurs/
  modules, abonnements par statut, répartition par plan, derniers tenants, activité récente),
  `AuditLogView` (table du journal d'audit + pagination), `ModuleListView` (cartes modules, statuts,
  activations, actions afficher/masquer + sélecteur de statut).
- Extension du namespace `admin.*` : `dash.*` (KPIs, colonnes, libellés), `audit.*` (colonnes, vide),
  `modules.*` (activations, core, afficher/masquer, vide) et `moduleStatus.*` (active/beta/coming_soon/
  maintenance/disabled). Réutilise `admin.tenantStatus`, `billing.subStatus` et `common.*`
  (pageOf/previous/next/date/status/name/loading).
- Pluriels factorisés en `{count} activation(s)` ; statuts d'abonnement/tenant/module rendus via `$t`.
  Ratchet : 3 vues retirées de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **44 ✅ / 0 🟡 / 4 ⬜** (92 %).
  Reste **2 vues traduisibles** (Inventaire : `BatchDeliveryView`, `MovementHistoryView`) + 2 Customers exclues.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : module Billing (UX-13) (2026-06-09)

Branche `feature/ux-i18n-billing` (release `v1.0.0` → `rc.82`).

### UX (i18n)
- **Module Billing internationalisé à 100 %** : `BillingView` (abonnement, prix mensuel/annuel,
  essai/renouvellement, bandeaux essai & suspension, jauges d'usage utilisateurs/produits/commandes,
  table des paiements manuels) et `UpgradeView` (sélecteur de devise, 4 plans
  Découverte/Essentiel/Croissance/Business avec quotas et fonctionnalités, prix localisés, CTA).
- Nouveau namespace `billing.*` (FR+EN) : libellés de plan/quota/prix, maps `subStatus`/`method`/
  `payStatus`, `plan.{code}.{name,desc,f2..f5}`, note de bas de page. Mutualise `common.currencyName`
  et les libellés génériques `common.*` (date/amount/status/notes/loading/retry).
- Données de plans (`name`/`description`/`features`) et maps de statut converties via `t()` pour rester
  réactives au changement de langue. Corrige au passage 2 libellés FR sans accent (`Recommande`,
  `Illimite`) que la garde ne détectait pas. Ratchet : 2 vues retirées de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **41 ✅ / 0 🟡 / 7 ⬜** (85 %).
  Reste **5 vues traduisibles** (Admin secondaire ×3, Inventaire ×2) + 2 Customers exclues.

### Tests
- Frontend **257** inchangé (dont `UpgradeView.spec` — rendu FR identique) · `vue-tsc` propre ·
  `npm run i18n:check` vert.

## [Non publié] — i18n : Onboarding + namespace partagé geo.timezone.* (UX-13) (2026-06-09)

Branche `feature/ux-i18n-onboarding` (release `v1.0.0` → `rc.81`).

### UX (i18n)
- **`OnboardingView`** internationalisé (assistant 6 étapes : type d'entreprise, taille d'équipe,
  modules, besoins opérationnels, infos entreprise, confirmation). Nouveau namespace `onboarding.*`
  (FR+EN) : titres/sous-titres d'étapes, maps `businessType` (8), `teamSize` (4), `module` (7),
  `needs` (5), récap « Ce qui sera configuré », écrans provisionnement/erreur/succès, boutons de
  navigation et messages d'erreur.
- **Namespace partagé `geo.timezone.*`** (15 fuseaux, FR+EN) ajouté à `geo.*`. La vue **réutilise**
  `geo.country` (29 pays) et `common.currencyName` (17 devises) déjà mutualisés en rc.80 — zéro
  duplication des données pays/devise/fuseau.
- Les tableaux de données (`businessTypes`/`teamSizes`/`allModules`) convertis en `computed` via `t()`
  pour rester réactifs au changement de langue ; selects pays/devise/fuseau liés à `geo.*`/`common.*`.
  Paramètre local `t` (dans `selectedTypeName`) renommé `bt` pour ne plus masquer la fonction i18n `t`.
- Ratchet : `OnboardingView` retirée de l'`ALLOWLIST` de la garde.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **39 ✅ / 0 🟡 / 9 ⬜** (81 %).
  Reste **7 vues traduisibles** (Billing ×2, Admin secondaire ×3, Inventaire ×2) + 2 Customers exclues.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Paramètres + namespace partagé geo.* (UX-13) (2026-06-09)

Branche `feature/ux-i18n-settings` (release `v1.0.0` → `rc.80`).

### UX (i18n)
- **`SettingsView`** internationalisé (gros écran : 6 onglets — Entreprise, Équipe, Rôles, Abonnement,
  Intégrations, Notifications — + 4 modals : invitation, accès entrepôts, accès temporaire, mise à
  niveau/preuve de paiement). Nouveau namespace `settings.*` (FR+EN) avec sous-objets company/team/
  billing/invite/warehouse/temp/upgrade + maps de rôles, statuts d'abonnement et durées de session.
- **Namespace partagé `geo.*`** (pays par code ISO, 30 entrées) + **`common.currencyName` étendu**
  (+10 devises) — réutilisables par `OnboardingView` (rc.81).
- Constantes/maps FR (tabs, roleLabel, subStatusLabel) converties via `t()`. Ratchet : vue retirée de
  l'`ALLOWLIST`. La garde a débusqué 2 placeholders accentués oubliés (corrigés).

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **38 ✅ / 0 🟡 / 10 ⬜** (79 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Marketplace (UX-13) (2026-06-09)

Branche `feature/ux-i18n-marketplace` (release `v1.0.0` → `rc.79`).

### UX (i18n)
- **`MarketplaceListingsView`** internationalisé : en-tête, bannière d'alertes, onglets
  (connexions/alertes), table (plateforme, statut sync, fermeture auto), liste d'alertes,
  **modal créer/éditer** (plateforme, ID externe, URL, seuil, switches auto-close/reopen/price-sync)
  + confirmation de suppression & erreurs. Nouveau namespace `marketplace.*` (FR+EN, statuts de sync) ;
  ajout `common.yes`/`common.no` ; réutilise `common.*`, `reports.viewAlerts`, `catalog.productForm.optional`.
- Ratchet : vue retirée de l'`ALLOWLIST` (la garde a confirmé en débusquant un reliquat oublié).

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **37 ✅ / 0 🟡 / 11 ⬜** (77 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Import/Export — Assistant — module 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-import-wizard` (release `v1.0.0` → `rc.78`). **Module Import/Export entièrement internationalisé (2/2 vues).**

### UX (i18n)
- **`ImportWizardView`** internationalisé (assistant 5 étapes) : choix type & mode, téléchargement
  modèle (colonnes par entité), upload fichier, **mapping des colonnes** (champs ERP par entité),
  **prévisualisation/approbation** (stats, filtres de lignes, table, résumé, résultat) + erreurs.
  Étend `importExport.wizard.*` (steps, entityType, modeOpt, templateCols, fields, rowFilter, action,
  rowStatus) ; consts FR `entityTypes`/`importModes`/`templateColumns`/`availableFields`/`rowFilters`
  converties en `computed` via `t()`.
- Ratchet : vue retirée de l'`ALLOWLIST` → **module Import/Export complet**.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **36 ✅ / 0 🟡 / 12 ⬜** (75 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Import/Export — Historique (UX-13) (2026-06-09)

Branche `feature/ux-i18n-import-history` (release `v1.0.0` → `rc.77`).

### UX (i18n)
- **`ImportHistoryView`** internationalisé : en-tête (compteur, nouvel import, modèles/export par
  entité), filtres (type/statut), table, pagination, **modal de détail** (stats, résumé, méta) +
  confirmation d'annulation & erreurs. Nouveau namespace `importExport.*` (FR+EN) avec maps
  partagées **entity / mode / modeShort / status** (remplacent les constantes FR `ENTITY_LABELS`/
  `MODE_LABELS`/`STATUS_LABELS` via helpers `t()`) + `history.*`.
- Ratchet : vue retirée de l'`ALLOWLIST`. (`ImportWizardView` → prochaine RC, réutilisera les maps.)

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **35 ✅ / 0 🟡 / 13 ⬜** (73 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Point de vente (POS) (UX-13) (2026-06-09)

Branche `feature/ux-i18n-pos` (release `v1.0.0` → `rc.76`).

### UX (i18n)
- **`PosView`** internationalisé : ouverture de session (fond de caisse), terminal (recherche/scan,
  panier, moyen de paiement, encaissement), sélecteur de variante, **clôture & rapprochement**
  (espèces attendues/comptées, écart) + toasts & erreurs. Nouveau namespace `pos.*` (FR+EN) ;
  réutilise `common.total`/`cancel`/`loading`, `payments.method`/`methodLabel`.
- Ratchet : vue retirée de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **34 ✅ / 0 🟡 / 14 ⬜** (71 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Ventes Création de commande — module 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-orders-create` (release `v1.0.0` → `rc.75`). **Module Ventes entièrement internationalisé (4/4 vues).**

### UX (i18n)
- **`OrderCreateView`** internationalisé : sélecteur client (autocomplétion), lignes d'articles
  (picker produit/variante, prix, quantité, totaux), note, total & actions + validations & erreur de
  création. Namespace `orders.create.*` ; réutilise `orders.new`/`colItems`, `common.*`,
  `catalog.productForm.optional`.
- Ratchet : vue retirée de l'`ALLOWLIST` → **module Ventes (Liste/Création/Fiche/Retours) complet**.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **33 ✅ / 0 🟡 / 15 ⬜** (69 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Ventes Fiche commande & Retours (UX-13) (2026-06-09)

Branche `feature/ux-i18n-orders-detail-returns` (release `v1.0.0` → `rc.74`).

### UX (i18n)
- **`OrderDetailView`** internationalisé : en-tête (statut), table des lignes, méta (dates), panneau
  **Paiements** (solde, liste, modal d'enregistrement) + panneau **Livraison** + actions
  (confirmer/livrer/annuler) & erreurs. Namespace `orders.detail.*` ; réutilise `orders.status`,
  `payments.method`/`colMethod`/`colReference`, `deliveries.status`, `common.*`.
- **`ReturnsView`** internationalisé : en-tête, filtre statut, table (motif/résolution/statut), actions
  (approuver/remettre en stock/refuser) + modal de refus. Namespace `orders.returns.*` (+ libellés
  reason/resolution/status).
- **`common.total`** ajouté (mutualisé). Ratchet : 2 vues retirées de l'`ALLOWLIST`.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **32 ✅ / 0 🟡 / 16 ⬜** (67 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Catalogue Fiche produit & Étiquettes — module 100 % (UX-13) (2026-06-09)

Branche `feature/ux-i18n-catalog-show-label` (release `v1.0.0` → `rc.73`). **Module Catalogue entièrement internationalisé (7/7 vues).**

### UX (i18n)
- **`ProductShowPage`** internationalisé (vue dense ~1360 lignes) : en-tête (type/statut/actions), 4 onglets
  (Vue d'ensemble, Variantes, Stock, Prix), cartes identification/prix, résumé & détail stock, table des
  variantes & mouvements, **2 drawers** (entrée stock, ajustement) + validations & erreurs. Namespace
  `catalog.productShow.*` ; réutilise `common.*`, `inventory.*`, `catalog.*`. Paramètres locaux `t`
  (typeLabel/mvtTypeLabel) renommés `type` pour ne plus masquer la fonction i18n.
- **`LabelPrintView`** internationalisé : sélection produits, configuration (format, options, copies),
  résumé & impression. Namespace `catalog.labelPrint.*`.
- **Ratchet garde** : les 2 vues retirées de l'`ALLOWLIST`. La garde a confirmé l'absence de texte FR
  accentué résiduel sur ces 2 vues denses.

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **30 ✅ / 0 🟡 / 18 ⬜** (62 %). Catalogue 100 %.

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : Catalogue Attributs & Déclinaisons (UX-13) (2026-06-09)

Branche `feature/ux-i18n-catalog-attrs-variants` (release `v1.0.0` → `rc.72`).

### UX (i18n)
- **`AttributesView`** internationalisé : en-tête, état vide, cartes produit (compteur de déclinaisons,
  voir la fiche), aide « aucun attribut ». Namespace `catalog.attributes.*`.
- **`VariantsView`** internationalisé : en-tête (sous-titre paramétré), filtres (recherche, statut),
  état vide, table (colonnes, voir), pagination (`common.pageOf`). Namespace `catalog.variants.*` ;
  réutilise `common.*` + `catalog.colPrice/colCategory/status/noResults`. Libellé partagé
  `catalog.viewProductsList`.
- **Ratchet garde** : les 2 vues retirées de l'`ALLOWLIST` du gate i18n (désormais protégées).

### Avancement
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) : **28 ✅ / 0 🟡 / 20 ⬜** (58 %).

### Tests
- Frontend **257** inchangé · `vue-tsc` propre · `npm run i18n:check` vert.

## [Non publié] — i18n : garde CI dure (parité + texte FR en dur) (UX-13) (2026-06-09)

Branche `feature/ux-i18n-guard` (release `v1.0.0` → `rc.71`). **La Definition of Done i18n est désormais opposable automatiquement.**

### Gouvernance i18n (garde automatisée)
- **`npm run i18n:check`** + spec `src/i18n/__tests__/i18n-coverage.guard.spec.ts` — **gate CI dur**
  (ajouté à `ci-feature.yml` job *quality* et `ci-develop.yml` *Build Frontend*, + exécuté dans la
  suite vitest). Contrôles : **(a)** parité des clés `messages.fr`/`messages.en` ; **(b)** détection de
  **texte FR en dur** (caractère accenté) dans les `<template>` des vues de module hors **allowlist
  ratchet** (22 vues non traduites, à réduire) ; **(c)** anti-bitrot de l'allowlist.
- `messages` est désormais **exporté** depuis `i18n/index.ts` (pour la garde).
- **Reliquat débusqué immédiatement** par la garde : `WarehouseView` (libellés de devises en dur) →
  corrigé via le nouveau **`common.currencyName.*`** mutualisé (`WarehouseView` + `ProductFormView`,
  `catalog.productForm.currencyName` supprimé — déduplication).

### Tests
- Frontend **257** (254 + 3 specs de garde) · `vue-tsc` propre.

## [Non publié] — i18n : pagination unifiée + 11 vues partielles soldées (UX-13) (2026-06-09)

Branche `feature/ux-i18n-pagination` (release `v1.0.0` → `rc.70`). **Les 26 vues câblées i18n sont désormais toutes complètes** (0 reliquat connu).

### UX (i18n)
- **`common.pageOf`** (`Page {current} / {total}`) + **`common.deleteFailed`** introduits ; `admin.pageOf`
  promu vers `common` (doublon supprimé, `ManualPaymentView` migrée).
- **Pagination unifiée** via `common.pageOf` sur 9 vues : Promotion, Tenant, Supplier, Payment, Order,
  Delivery, Stock, Produits (l'audit avait manqué Produits/Stock — d'où le besoin de la garde CI).
- **`DeliveryListView`** : « ← Précédent / Suivant → » → `common.previous`/`next`.
- **`CountryRuleListView`** : `'Suppression impossible.'` → `common.deleteFailed` ; **`StockListView`** :
  `'Une erreur est survenue.'` → `common.genericError`.
- **`StockTransferView`** : confirmation d'expédition traduite (`inventory.shipTransfer*`/`ship`) ;
  paramètre local `t` renommé `tr` pour ne plus masquer la fonction i18n.
- **`StockReportView`** : « (top 10) » → `reports.top10`.

### Gouvernance i18n
- Tracker [`i18n-coverage.md`](docs/recette/i18n-coverage.md) mis à jour : **26 ✅ / 0 🟡 / 22 ⬜**.
  Prochaine brique : garde automatisée (gate CI dur, ratchet avec allowlist).

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : formulaire produit + tracker de couverture (UX-13) (2026-06-09)

Branche `feature/ux-i18n-catalog-productform` (release `v1.0.0` → `rc.69`).

### UX (i18n)
- **`ProductFormView`** internationalisé (plus grosse vue de l'app) : infos générales (SKU/code-barres/
  GTIN + aides), prix de base (devise, marge, stock initial), **builder d'axes de variation** (axes,
  valeurs, aperçu combinaisons, génération), table des déclinaisons, colonne latérale (statut,
  catégorie, étiquettes, expédition), **modal de désactivation de variante** (transfert/sortie/
  conservation) + validations & erreurs. Nouveau namespace `catalog.productForm.*` (FR+EN), incluant
  noms d'axes, libellés de devises et hints de statut. Notes d'inventaire **persistées** laissées en
  FR (source de vérité, exclues de l'i18n UI).

### Gouvernance i18n
- **Tracker vivant `docs/recette/i18n-coverage.md`** — décompte **exhaustif des 48 vues** (✅ complet /
  🟡 partiel / ⬜ à faire), issu d'un audit multi-agents. État réel : **15 ✅ / 11 🟡 / 22 ⬜**.
- **Definition of Done i18n** formalisée : toute vue créée/modifiée doit livrer FR+EN (parité de clés,
  zéro chaîne en dur) dans le même changement. Référencée depuis `i18n.md` et l'état-des-lieux.
- Découverte : motif **pagination « Page X / Y »** codé en dur dans 6 vues → quick win `common.pageOf`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Admin Plans & Paiements manuels (UX-13) (2026-06-09)

Branche `feature/ux-i18n-admin3` (release `v1.0.0` → `rc.68`). **Fin de l'internationalisation du back-office Admin.**

### UX (i18n)
- **`PlanListView`** internationalisé : cartes plan (visibilité Public/Privé, prix `/ mois`/Gratuit,
  limites usagers/produits/commandes/essai), **modal d'édition des limites** (libellés Nom, utilisateurs
  inclus, jours d'essai, produits, commandes, clients, boutiques, entrepôts, imports, appels API,
  stockage ; cases Actif/Public) + erreur d'enregistrement. Étend `admin.*` ; réutilise `common.*`.
- **`ManualPaymentView`** internationalisé : onglets de statut (En attente/Approuvés/Rejetés/Tous +
  badge), table (colonnes, méthode dont Virement, statuts), pagination, actions Approuver/Rejeter,
  **modal de rejet** (motif) + confirmation d'approbation & messages d'erreur. Étend `admin.*` ;
  réutilise `common.*` (amount, date, status, actions, view, previous/next, cancel, genericError).

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Admin Règles pays (UX-13) (2026-06-08)

Branche `feature/ux-i18n-countryrule` (release `v1.0.0` → `rc.67`).

### UX (i18n)
- **`CountryRuleListView`** internationalisé : en-tête, états (chargement/erreur/vide), table
  (colonnes, statuts Bloqué/Actif/Inactif, approbation, plans), modal créer/éditer (code pays,
  devise, fuseau, plans autorisés, cases) + erreurs & confirmation de suppression. Étend `admin.*`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Admin Promotions (UX-13) (2026-06-08)

Branche `feature/ux-i18n-admin2` (release `v1.0.0` → `rc.66`).

### UX (i18n)
- **`PromotionListView`** internationalisé : toolbar (compteur), table (colonnes, type de remise,
  statut, actions), pagination, et **modal créer/éditer** (code, description, type/valeur de remise,
  plans applicables, validité, utilisations max, activation) + validations & confirmation de
  suppression. Étend `admin.*` ; réutilise `common.*`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : fiche tenant (back-office Admin) (UX-13) (2026-06-08)

Branche `feature/ux-i18n-tenantdetail` (release `v1.0.0` → `rc.65`).

### UX (i18n)
- **`TenantDetailView`** internationalisé : en-tête (statut via `statusLabel`, suspendre/réactiver),
  cartes **Informations** + **Abonnement** (changement de plan), **Utilisateurs**, **Modules ERP**
  (activer/désactiver, Core). Étend `admin.*` ; réutilise `common.*` (name, email, status, createdAt,
  active/inactive, loading).

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : back-office Admin — liste des tenants (UX-13) (2026-06-08)

Branche `feature/ux-i18n-tenants` (release `v1.0.0` → `rc.64`).

### UX (i18n)
- **`TenantListView`** (back-office super-admin) internationalisé : filtres (recherche, statut, plan),
  table (colonnes, statut traduit via `statusLabel`), actions (Détails/Suspendre/Réactiver),
  pagination, confirmation de suspension. Nouveau namespace `admin.*` ; réutilise `common.*`
  (allStatuses, status, createdAt, actions, previous/next).
- *(`TenantDetailView` — 434 lignes — prévu à l'itération suivante.)*

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : profil utilisateur traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-profile` (release `v1.0.0` → `rc.63`).

### UX (i18n)
- **`ProfileView`** internationalisé : carte identité (rôles, super-admin), **infos personnelles**,
  **changement de mot de passe** (indicateur de force ×4), **sessions actives** (révocation +
  confirmation). Nouveau namespace `profile.*` (rôles + niveaux de force imbriqués) ; réutilise
  `common.*` et `auth.emailLabel`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : Dashboard traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-dashboard` (release `v1.0.0` → `rc.62`).

### UX (i18n)
- **`DashboardView`** internationalisé : sous-titre, **cartes KPI** (×4 + tendance « % vs hier »),
  graphiques (CA, commandes récentes, top produits), bannière d'essai, **section modules** (badges
  Actif/Bientôt/Inactif), **actions rapides**. Nouveau namespace `dashboard.*` ; réutilise
  `orders.status` (libellés de commande), `inventory.units`, `common.*`, `nav.dashboard`.

### Tests
- Frontend **254** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : module Auth traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-auth` (release `v1.0.0` → `rc.61`).

### UX (i18n)
- **`LoginView`** (connexion, mot de passe oublié, message d'expiration de session, erreurs
  401/403/réseau) et **`RegisterView`** (création d'espace, force du mot de passe ×4, CGU,
  validations) internationalisés.
- Nouveau namespace **`auth.*`** (FR + EN) : libellés, placeholders, messages d'erreur, niveaux de
  force (`strength`).

### Tests
- Frontend **254** (+1 : namespace `auth` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — i18n : périodes fiscales — module Inventaire 100 % traduit (UX-13) (2026-06-08)

Branche `feature/ux-i18n-fiscal` (release `v1.0.0` → `rc.60`).

### UX (i18n)
- **`FiscalPeriodView` (Périodes fiscales)** internationalisé : en-tête, cartes (types ×3, statuts ×3,
  intégrité, actions), volet **création** et volet **verrouillage irréversible** (avertissement +
  raison). `typeLabel`/`statusLabel` via `t()` (`fiscalType` / `fiscalStatus`).
- **🎯 Module Inventaire 100 % traduit** (6 vues : Entrepôts, Stock, Alertes, Ajustements, Transferts,
  Périodes — namespace `inventory.*`).

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : transferts inter-entrepôts traduits FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-transfer` (release `v1.0.0` → `rc.59`).

### UX (i18n)
- **`StockTransferView` (Transferts inter-entrepôts)** internationalisé : en-tête + compteur, filtre
  de statut, table (colonnes, statuts ×8, actions Expédier/Réceptionner/Résoudre), et **3 volets**
  (création : entrepôts/notes/lignes ; réception : quantités reçues ; résolution de litige :
  résolution + raison). `statusLabel` via `t()` (`transferStatus`).
- Étend `inventory.*` (transferts) ; réutilise `common.*` (status, actions, notes, quantity,
  allStatuses, cancel, saving, confirm).

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : ajustements de stock traduits FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-stockadj` (release `v1.0.0` → `rc.58`).

### UX (i18n)
- **`StockAdjustmentView` (Ajustements de stock)** internationalisé : en-tête, file d'attente +
  historique (colonnes, statuts), volets **création** (article, quantité, motif, note) et **rejet**
  (motif). Libellés de **motif (8)** et de **statut (4)** via `t()` (`adjReason` / `adjStatus`) ;
  erreurs runtime traduites.
- Étend `inventory.*` (ajustements) ; `REASON_LABELS` du service n'est plus consommé par la vue.

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : alertes de stock traduites FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-stock2` (release `v1.0.0` → `rc.57`).

### UX (i18n)
- **`StockAlertsView` (Alertes stock bas)** internationalisé : en-tête + compteur, bannière
  d'urgence, cartes d'alerte (badges, disponible vs seuil, barre de progression « % du seuil » /
  « Rupture »), et **volet de réapprovisionnement** (infos stock, quantité + « unités », raisons
  fournisseur, référence, note, pied). Erreur runtime via `common.genericError`.
- Étend `inventory.*` (alertes : titre, seuils, réappro, `restockReason`).

### Tests
- Frontend **253** inchangé · `vue-tsc` propre.

## [Non publié] — i18n : liste Stock traduite FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-stocklist` (release `v1.0.0` → `rc.56`).

### UX (i18n)
- **`StockListView` (Stock)** internationalisé : en-tête + compteur, actions (réception, alertes),
  filtres (recherche / entrepôt / catégorie / stock bas), barre KPI entrepôt, table (colonnes,
  badges Stock bas/OK), actions de ligne (entrée/sortie/ajuster/historique), pagination, et **volet
  entrée/sortie/ajustement** (titre dynamique, infos stock, quantité + suffixe « unités », indices
  Après/Ajustement, raisons, référence, note, pied). Titre du volet via `t()`.
- Étend le namespace **`inventory.*`** (clés `kpi`, `modalTitle`, `reasonOpt`) ; réutilise `common.*`
  (product, quantity, status, actions, note, previous/next, allWarehouses).

### Tests
- Frontend **253** (assertions `inventory` enrichies : stock + raison + interpolation) · `vue-tsc`
  propre · `StockListView.spec` verte (FR inchangé).

## [Non publié] — i18n : module Entrepôts traduit FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-inventory` (release `v1.0.0` → `rc.55`).

### UX (i18n)
- **`WarehouseView` (Entrepôts & Boutiques)** internationalisé : titre + compteur, bouton, état vide,
  cartes (badges type/défaut/actif/en ligne, actions), et **volet créer/éditer** (libellés,
  placeholders, types, devise, bascules, pied) ; messages runtime via `t()` (badge de type,
  validation « nom et code requis », erreur d'enregistrement).
- Nouveau namespace **`inventory.*`** (FR + EN) avec clés imbriquées `typeOption`/`typeBadge` ;
  réutilise `common.*` (name, phone, active/inactive, edit, cancel, saving, update, create).

### Tests
- Frontend **253** (+1 : namespace `inventory` dans `i18n.spec` — titre + clé imbriquée FR/EN) ·
  `vue-tsc` propre · `WarehouseView.spec` verte (FR inchangé).

## [Non publié] — Refonte UI : reliquat (Marketplace + alert() → toasts) + docs (2026-06-08)

Branche `feature/ux-reliquat-docs` (release `v1.0.0` → `rc.54`).

### UX — fin de la refonte
- **`MarketplaceListingsView`** : sous-titre = « plateforme · ID externe » à l'édition (dernier volet
  sans contexte d'en-tête).
- **`alert()` natifs supprimés** (7 occurrences / 6 vues) → **toasts** non bloquants via `pushToast`
  (`useNotifications`, sévérité `error`) : `SupplierListView` (suppression échouée), `ProductShowPage`
  (impression), `SettingsView` (×2 : rôle / activation membre), `LabelPrintView` (génération),
  `ImportWizardView` & `ImportHistoryView` (annulation). `pushToast` est désormais exporté directement.
- **🎯 Refonte « Side-Drawer » 100 % terminée** : volets latéraux (rc.46), confirmations centrées
  (rc.47-48), polish par module (rc.49-53), reliquat (rc.54). **Plus aucun `confirm()`/`alert()`
  bloquant** côté vues.

### Docs
- **`go-no-go-v1.0.0.md` actualisé** (gelé rc.1 → **rc.54**) : compteurs de tests, section UX (design
  system, Side-Drawer, i18n, confirmations/toasts), zones, checklist. + `etat-des-lieux`,
  `ux-design-system`, CHANGELOG.

### Tests
- Frontend **252** inchangé · `vue-tsc` propre · `pushToast` déjà couvert (`useNotifications.spec`).

## [Non publié] — Refonte UI : polish des volets — Inventaire (Transfert & Période) (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-inventory` (release `v1.0.0` → `rc.53`).

### UX — polish volets (Phase 3)
- **`StockTransferView`** (réception, résolution de litige) et **`FiscalPeriodView`** (verrouillage) :
  numéro de transfert / nom de période déplacés du **titre** vers le **sous-titre** (titre = action
  seule), pour s'aligner sur le pattern « titre = action, sous-titre = entité » des autres volets.
- *(Volets de création « Nouveau transfert / Nouvelle période » : pas de sous-titre — aucune entité
  existante. `StockAdjustmentView` : création OK ; rejet laissé tel quel — pas de champ article fiable
  pour un sous-titre.)*

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · specs vertes.

> **Phase 3 quasi terminée** — modules phares polis (Stock, Paiements, Admin, Fournisseurs, Entrepôts,
> Livraisons, Inventaire) ; POS déjà conforme. Reliquat (Marketplace, Import, page Produit) déjà
> conforme via la fondation rc.46 ou hors périmètre « volet ».

## [Non publié] — Refonte UI : polish des volets — Entrepôts & Livraisons (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-pos` (release `v1.0.0` → `rc.52`).

### UX — polish volets (Phase 3)
- **`WarehouseView`** : titre « Modifier l'emplacement » (au lieu de « Modifier » nu) + **sous-titre**
  = nom de l'entrepôt à l'édition.
- **`DeliveryListView`** : **sous-titre** = référence commande sur le volet « Signaler un échec »
  (`failModal.orderRef` capturé à l'ouverture).
- *(`PosView` déjà conforme : devise en suffixe via `.pos-amount-input` / `.pos-currency` sur le fond
  de caisse et la clôture — pattern précurseur de `.input-affix` ; aucun changement nécessaire.)*

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · specs vertes.

## [Non publié] — Refonte UI : polish des volets — Admin & Fournisseurs (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-admin` (release `v1.0.0` → `rc.51`).

### UX — polish volets (Phase 3)
- **`PromotionListView`** : champ **valeur de remise** avec suffixe **`%`** dynamique (`.input-affix`,
  affiché quand le type = pourcentage) ; **sous-titre** = code promo à l'édition.
- **`SupplierListView`** : **sous-titre** = nom du fournisseur à l'édition (contexte d'en-tête).
- *(`PlanListView` édite des limites — pas de prix dans le modal — et `CountryRuleListView` des
  codes/devise ISO : aucun champ montant, donc pas d'affixe ; leurs titres portent déjà le contexte.)*

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · specs vertes.

## [Non publié] — Refonte UI : polish des volets — module Paiements (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-payments` (release `v1.0.0` → `rc.50`).

### UX — polish volets (Phase 3, module Paiements)
- **`OrderDetailView` (enregistrer un paiement)** : contexte « Commande N° · Reste … » remonté en
  **sous-titre** d'en-tête ; **devise de la commande affichée en suffixe** du champ montant via
  `.input-affix` (remplace la boîte devise en lecture seule séparée) — calqué sur la capture de
  référence (montant … XAF).
- **`ManualPaymentView` (rejet)** : nom du tenant remonté en **sous-titre** d'en-tête.
- **`PaymentListView`** : champ montant + sélecteur de devise désormais **accolés** (nouveau pattern
  réutilisable **`.input-group`**), lus comme un seul contrôle.

### Tests
- Frontend **252** inchangé (polish visuel) · `vue-tsc` propre · `OrderDetailView`/`PaymentListView`
  specs vertes (titres/flux inchangés).

## [Non publié] — Refonte UI : polish des volets — module Stock (Phase 3) (2026-06-08)

Branche `feature/ux-drawer-stock` (release `v1.0.0` → `rc.49`).

### UX — polish volets (Phase 3, module Stock)
- **Sous-titre contextuel dans l'en-tête** : `StockListView` (entrée/sortie/ajustement) et
  `StockAlertsView` (réapprovisionnement) affichent désormais « produit · SKU » via la prop
  `subtitle` de `BaseModal` (au lieu d'un `<p>` en haut du corps) — calqué sur la capture de référence.
- **Suffixe d'unité dans le champ** : nouveau pattern réutilisable **`.input-affix`** (suffixe
  collé à droite, à l'intérieur de l'input) ; appliqué aux champs quantité (« unités »). Prêt pour
  les suffixes **devise (XAF)** des modules monétaires à venir.
- Bloc info-stock conservé en **contexte grisé** (`--gray-50`).

### Tests
- Frontend **252** inchangé (polish visuel ; `StockListView.spec` toujours verte — le titre du
  volet est inchangé, le sous-titre reste dans le dialogue) · `vue-tsc` propre.

## [Non publié] — Refonte UI : confirmations centrées — vague 2 (fin) (2026-06-08)

Branche `feature/ux-confirm-wave2` (release `v1.0.0` → `rc.48`).

### UX — confirmations (suite)
- Migration `confirm()` natif → `useConfirm()` **terminée** sur les vues. Vague 2 (10 fichiers,
  11 points) : `MarketplaceListingsView` (suppression connexion), `ImportWizardView` +
  `ImportHistoryView` (annulation import), `TenantListView` + `TenantDetailView` (suspension),
  `ManualPaymentView` (approbation), `SettingsView` (activation/désactivation membre),
  `StockTransferView` (expédition), `ReturnsView` (approbation + remise en stock), `ProfileView`
  (révocation de session). Boutons `danger` pour les actions destructives, primaires pour les
  validations positives.
- **Plus aucun `confirm()` bloquant côté vues** (hors `CustomerDetailView` — module Clients en
  session concurrente, `orderService.confirm()` = méthode API, `useUnsavedChanges` = garde de
  navigation hors composant).

### Tests
- Frontend **252** inchangé (migration mécanique ; primitif déjà couvert rc.47) · `vue-tsc` propre ·
  aucune spec impactée (les flux `confirm()` de la vague 2 ne sont pas exercés en test).

## [Non publié] — Refonte UI : confirmations centrées (ConfirmDialog / useConfirm) — vague 1 (2026-06-08)

Branche `feature/ux-confirm-dialog` (release `v1.0.0` → `rc.47`).

### UX — confirmations
- Nouveau **`ConfirmDialog.vue`** (boîte **centrée** via `BaseModal variant="center"`) + composable
  **`useConfirm()`** : `await confirm({ title, message, danger?, … }) → Promise<boolean>`. Host monté
  une seule fois dans `App.vue` ; toute fermeture (croix/Échap/clic-extérieur) vaut annulation ;
  bouton **rouge** en mode `danger`. Remplace les `window.confirm()` natifs par une boîte cohérente
  avec le design system (mêmes boutons, angles, polices).
- **Vague 1** — migrés : `CategoryListView`, `SupplierListView` (suppression), `PaymentListView`
  (annulation), `OrderDetailView` (annulation de paiement), `CountryRuleListView`, `PromotionListView`
  (suppression), `RolesPanel` (suppression de rôle). *(Les `confirm()` des services/composables —
  `orderService`, `useUnsavedChanges` — restent inchangés : hors composant.)*

### Correctif i18n
- `common.update` / `common.description` **manquaient en anglais** (introduits FR-only en rc.45) →
  l'UI EN affichait le fallback français. Ajoutés ; nouvelle clé `common.confirm` (FR/EN).

### Tests
- Frontend **252** (+4 : `ConfirmDialog.spec` — ouverture/validation/annulation/libellé défaut ;
  `PaymentListView` & `OrderDetailView` spec migrées vers un mock `useConfirm` ; garde-fou i18n EN)
  · `vue-tsc` propre.

## [Non publié] — Refonte UI : fondation Side-Drawer (BaseModal) (2026-06-08)

Branche `feature/ux-drawer-foundation` (release `v1.0.0` → `rc.46`).

### UX — refonte « volet latéral »
- **`BaseModal` devient un Side-Drawer par défaut** (`variant="drawer"`) : volet plein écran
  (100vh) sur le flanc **droit**, largeur fixe selon `size` (**sm 400 / md 460 / lg 520 px**),
  voile sombre, fond blanc, slide-in (respecte `prefers-reduced-motion`), corps défilant et
  **pied collé en bas**. Les **~25 vues** consommatrices basculent en volet **sans changement de
  code** (API rétro-compatible).
- Nouveau **`variant="center"`** (boîte centrée arrondie) réservé aux confirmations critiques
  (socle de la Phase 2 `ConfirmDialog`/`useConfirm`).
- En-tête enrichi : prop **`subtitle`** + slot **`#subtitle`** (contexte type « produit · SKU »),
  croix de fermeture plus fine et grise.

### Correctif (bug latent)
- `.modal-overlay` / `.modal` n'étaient **définis nulle part** → les volets rendaient sans voile
  ni positionnement. Le chrome est désormais centralisé dans `main.css` (source unique) ;
  `.modal-backdrop` / `.modal-box` morts supprimés.

### Tests
- Frontend **248** (+4 : variant drawer par défaut, variant center + taille, sous-titre présent/absent
  dans `ui.spec`) · `vue-tsc` propre · aucune régression sur les vues à modale.

## [Non publié] — i18n : liste Catégories traduite FR/EN (UX-13) (2026-06-08)

Branche `feature/ux-i18n-categories` (release `v1.0.0` → `rc.45`).

### UX
- **Liste Catégories (`CategoryListView`)** internationalisée : titre + compteur, bouton « Nouvelle
  catégorie », état vide (+ aide), colonnes (Nom/Parent/Ordre/Statut/Actions), badge Active/Inactive,
  actions de ligne, et **modale créer/éditer** (titre, libellés, placeholders, bascule de statut, pied
  Annuler/Créer/Mettre à jour) ; messages runtime (validation « nom requis », confirmation de
  suppression interpolée) via `t()`.
- Enrichit le namespace **`catalog.*`** (sous-ensemble catégories) + ajoute `common.description` /
  `common.update` mutualisés.

### Tests
- Frontend **244** (+1 : sous-namespace catégories dans `i18n.spec` — titre + confirmation interpolée
  FR/EN) · `vue-tsc` propre.

## [Non publié] — i18n : liste Produits traduite FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-products` (release `v1.0.0` → `rc.44`).

### UX
- **Liste Produits (`ProductListView`)** internationalisée : titre, filtres (recherche/statut/catégorie),
  barre de sélection (impression lots), colonnes, statuts, badges de variantes, actions de ligne,
  pagination, états vide/recherche. Nouveau namespace `catalog.*` (`status`) + `common.*` réutilisés.
- **Correctif tests** : sous jsdom la locale par défaut serait `en` (navigator.language) → `test-setup.ts`
  force désormais `fr` avant chaque test (les specs anglaises basculent puis restaurent).

### Tests
- Frontend **243** (+1 : namespace `catalog` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal TERMINÉE (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave17` (release `v1.0.0` → `rc.43`).

### UX
- **Dernière vague (UX-03, 17ᵉ)** — `SettingsView` : **4 modales** (invitation d'un membre, accès
  entrepôts, accès temporaire auto-expirant, demande de mise à niveau / preuve de paiement) migrées
  vers `<BaseModal>` ; chrome `.modal-*` dupliqué retiré (corps via `.settings-modal-body`).
- **🎯 Migration `BaseModal` 100 % terminée** : **33 modales / 24 vues**, **plus aucune modale
  ad-hoc** dans le code (`grep modal-overlay|modal-backdrop` = 0). Chrome unifié (overlay,
  focus-trap, Échap, clic-extérieur, en-tête/fermeture) sur toute l'application.

### Tests
- `v-focus-trap` enregistré globalement dans `test-setup.ts` (plus de warning, specs allégées).
  Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 16ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave16` (release `v1.0.0` → `rc.42`).

### UX
- **Modales unifiées (UX-03, 16ᵉ vague)** — `PosView` (**2 modales** : choix de déclinaison +
  clôture de caisse) migrées vers `<BaseModal>` ; chrome `.pos-modal-*` retiré (les contrôles tactiles
  `pos-btn`/`pos-input` et les `data-test` sont conservés). **29 modales / 23 vues migrées au total**
  (ne reste que `SettingsView`). 
### Tests
- `PosView.spec` adapté (teleport stub + `v-focus-trap`). Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 15ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave15` (release `v1.0.0` → `rc.41`).

### UX
- **Modale unifiée (UX-03, 15ᵉ vague)** — `ProductFormView` (gestion du stock à la désactivation
  d'une variante : transférer / sortir / conserver) migrée vers `<BaseModal>` ; styles `.deact-*`
  de chrome retirés. **27 modales / 22 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 14ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave14` (release `v1.0.0` → `rc.40`).

### UX
- **Modale unifiée (UX-03, 14ᵉ vague)** — `StockListView` (entrée / sortie / ajustement de stock)
  migrée vers `<BaseModal>` ; sous-titre (produit · SKU) déplacé dans le corps. **26 modales /
  21 vues migrées au total.**

### Tests
- `StockListView.spec` adapté (BaseModal `role="dialog"` + `v-focus-trap`). Frontend **242** au vert ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 13ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave13` (release `v1.0.0` → `rc.39`).

### UX
- **Modale unifiée (UX-03, 13ᵉ vague)** — `OrderDetailView` (enregistrer un paiement sur une
  commande) migrée vers `<BaseModal>` ; sous-titre (n° commande + reste à payer) déplacé dans le
  corps. **25 modales / 20 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 12ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave12` (release `v1.0.0` → `rc.38`).

### UX
- **Modale unifiée (UX-03, 12ᵉ vague)** — `MarketplaceListingsView` (connexion/édition d'une listing
  marketplace) migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés (corps via
  `.mp-modal-body`). **24 modales / 19 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 11ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave11` (release `v1.0.0` → `rc.37`).

### UX
- **Modale unifiée (UX-03, 11ᵉ vague)** — `PromotionListView` (création/édition de promotion, admin)
  migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés (corps via `.promo-modal-body`).
  **23 modales / 18 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 10ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-importhistory` (release `v1.0.0` → `rc.36`).

### UX
- **Modale unifiée (UX-03, 10ᵉ vague)** — `ImportHistoryView` (détail d'une session d'import)
  migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés (corps via `.import-detail-body`,
  sous-titre déplacé dans le corps). **22 modales / 17 vues migrées au total.**

### Tests
- Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 9ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave9` (release `v1.0.0` → `rc.35`).

### UX
- **Modale unifiée (UX-03, 9ᵉ vague)** — `RolesPanel` (création/édition de rôle personnalisé,
  Paramètres) migrée vers `<BaseModal>` ; chrome/`v-focus-trap` manuels remplacés, styles `.modal-*`
  dupliqués retirés (corps via `.roles-modal-body`). **21 modales / 16 vues migrées au total.**

### Tests
- `RolesPanel.spec` adapté (teleport stub + `v-focus-trap`). Frontend **242** au vert · `vue-tsc` propre.

## [Non publié] — i18n : liste Commandes traduite FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-orders-list` (release `v1.0.0` → `rc.34`).

### UX
- **Liste Commandes (`OrderListView`)** internationalisée : titre, onglets de statut, filtres
  (recherche/dates/entrepôt), colonnes (+ `data-label` cartes mobiles), statuts, pagination, états
  vide/erreur. Nouveau namespace `orders.*` (`tab`/`status`) + `common.view` mutualisé. Bascule
  FR ↔ EN en direct. (Reste `OrderCreateView`/`OrderDetailView` pour compléter le module.)

### Tests
- Frontend **242** (+1 : namespace `orders` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 8ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave8` (release `v1.0.0` → `rc.33`).

### UX
- **Modales unifiées (UX-03, 8ᵉ vague)** — `StockTransferView` (**3 modales** : création de transfert,
  réception, résolution de litige) migrées vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés.
  **20 modales / 15 vues migrées au total.**

### Tests
- Frontend **241** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 7ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave7` (release `v1.0.0` → `rc.32`).

### UX
- **Modale unifiée (UX-03, 7ᵉ vague)** — `ManualPaymentView` (rejet de paiement manuel, admin)
  migrée vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés. **17 modales / 14 vues migrées.**

### Tests
- Frontend **241** au vert · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 6ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave6` (release `v1.0.0` → `rc.31`).

### UX
- **Modales unifiées (UX-03, 6ᵉ vague)** — `FiscalPeriodView` (**2 modales** : création + verrouillage
  irréversible) et `SupplierDetailView` (confirmation de suppression) migrées vers `<BaseModal>`
  (styles `.modal-*` dupliqués retirés). Le module Fournisseurs est désormais **100 % BaseModal**.
  **16 modales / 13 vues migrées au total.**

### Tests
- `SupplierDetailView.spec` adapté (teleport stub + `v-focus-trap`) pour la modale BaseModal.
  Frontend **241** au vert · `vue-tsc` propre.

## [Non publié] — i18n : module Rapports traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-reports` (release `v1.0.0` → `rc.30`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Rapports (`SalesReportView` + `StockReportView`)** — 4ᵉ module entièrement
  internationalisé : titres, KPI, périodes (court + long), graphiques, tableaux, listes de moyens
  de paiement / mouvements, messages d'erreur. Nouveau namespace `reports.*` (avec sous-tables
  `period`/`periodLong`/`movement`) ; réutilise `payments.method.*` et `common.*` (allWarehouses,
  product, quantity). Bascule FR ↔ EN en direct.

### Tests
- Frontend **241** (+1 : namespace `reports` dans `i18n.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 5ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave5` (release `v1.0.0` → `rc.29`).

### UX
- **Modales unifiées (UX-03, 5ᵉ vague)** — `PlanListView` (édition des limites de plan) et
  `StockAlertsView` (réapprovisionnement) migrées vers `<BaseModal>` (toutes deux utilisaient les
  classes globales `.modal-*`, rien à nettoyer ; sous-titre de StockAlerts déplacé dans le corps).
  **13 modales / 11 vues migrées au total.**

### Tests
- Frontend **240** au vert · `vue-tsc` propre (contrat `BaseModal` couvert par 4 specs existantes).

## [Non publié] — i18n : module Paiements traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-payments` (release `v1.0.0` → `rc.28`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Paiements (`PaymentListView`)** — 3ᵉ module entièrement internationalisé : toutes les
  chaînes via `$t` / `t()` (filtres, moyens de paiement, colonnes — y compris les `data-label`
  des cartes mobiles, total, pagination, modale, message de confirmation d'annulation). Nouveau
  namespace `payments.*` (dont `payments.method.*`) + libellés `common.*` mutualisés (date, amount,
  note, previous, next). Bascule FR ↔ EN en direct.

### Tests
- Frontend **240** (+1 : re-rendu EN dans `PaymentListView.spec.ts`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 4ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave4` (release `v1.0.0` → `rc.27`).

### UX
- **Modales unifiées (UX-03, 4ᵉ vague)** — `ReturnsView` (refus de retour) et `CountryRuleListView`
  (création/édition de règle pays) migrées vers `<BaseModal>` ; styles `.modal-*` dupliqués retirés
  (ReturnsView). **11 modales / 9 vues migrées au total.**

### Tests
- Frontend **239** au vert · `vue-tsc` propre (contrat `BaseModal` couvert par 4 specs existantes).

## [Non publié] — i18n + BaseModal : module Livraisons (UX-13 + UX-03) (2026-06-07)

Branche `feature/ux-i18n-deliveries` (release `v1.0.0` → `rc.26`).
Voir `docs/modules/i18n.md`, `docs/modules/ux-design-system.md`.

### UX
- **Module Livraisons finalisé (`DeliveryListView`)** — 2ᵉ module entièrement internationalisé :
  toutes les chaînes via `$t`/`t()` (statuts, colonnes, actions, modales), nouveau namespace
  `deliveries.*` + `common.allStatuses` mutualisé ; bascule FR ↔ EN en direct.
- Ses **2 modales** (nouvelle livraison + signalement d'échec) migrées vers `<BaseModal>`
  (focus-trap, Échap, clic-extérieur). Total cumulé : **9 modales / 7 vues** sur `<BaseModal>`.

### Tests
- Frontend **239** (+3 : `DeliveryListView.spec.ts` — liste + `role="dialog"` `aria-modal` +
  re-rendu EN au changement de langue) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 3ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave3` (release `v1.0.0` → `rc.25`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03, 3ᵉ vague)** — `WarehouseView` (création/édition d'emplacement) et
  `StockAdjustmentView` (**2 modales** : nouvelle demande + rejet) migrées de leur modale ad-hoc
  vers `<BaseModal>` (overlay, `Teleport`, focus-trap, Échap, clic-extérieur, en-tête/fermeture
  cohérents ; styles `.modal-*` dupliqués supprimés). **7 modales / 6 vues migrées au total.**

### Tests
- Frontend **236** (+2 : `WarehouseView.spec.ts` — liste + ouverture d'un `role="dialog"`
  `aria-modal`) · `vue-tsc` propre.

## [Non publié] — i18n : module Fournisseurs traduit FR/EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-suppliers` (release `v1.0.0` → `rc.24`).
Voir `docs/modules/i18n.md`.

### UX
- **Module Fournisseurs entièrement internationalisé (UX-13)** — `SupplierListView` et
  `SupplierDetailView` : toutes les chaînes visibles passent par `$t` (template) / `t()` (script :
  `confirm`/`alert`, fallback d'erreur). Nouveau namespace `suppliers.*` + libellés génériques
  mutualisés sous `common.*` (name, email, phone, status, actions, notes, active/inactive,
  createdAt/updatedAt, saving, retry, genericError) pour réemploi par les prochains modules.
  Le sélecteur de langue bascule l'UI Fournisseurs en direct (FR ↔ EN).

### Tests
- `i18n` enregistré globalement dans `test-setup.ts` (`$t` disponible à chaque montage) ; specs
  changeant la locale la restaurent (isolation).
- Frontend **234** (+ namespace `suppliers` dans `i18n.spec`, re-rendu EN dans
  `SupplierListView.spec`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 2ᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-wave2` (release `v1.0.0` → `rc.23`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03, 2ᵉ vague)** — `SupplierListView` (création/édition fournisseur) et
  `CategoryListView` (création/édition catégorie) migrées de leur modale ad-hoc vers `<BaseModal>`
  (overlay, `Teleport`, focus-trap, Échap, clic-extérieur, en-tête/fermeture cohérents ; styles
  `.modal-*` dupliqués supprimés). Pour le formulaire fournisseur (vrai `<form>`), le `<form id>`
  reste dans le slot par défaut et le bouton du `#footer` y est lié par l'attribut `form="…"`
  (validation native + Entrée préservées). 4 modales migrées au total (2 vagues).

### Tests
- Frontend **230** (+2 : `SupplierListView.spec.ts` — liste + ouverture d'un `role="dialog"`
  `aria-modal` avec bouton `form="supplier-form"`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : fondation i18n FR + EN (UX-13) (2026-06-07)

Branche `feature/ux-i18n-foundation` (release `v1.0.0` → `rc.22`).
Voir `docs/modules/i18n.md`.

### UX
- **Fondation i18n (UX-13)** — noyau d'internationalisation **léger et sans dépendance**
  (`src/i18n/index.ts`) qui imite l'API `t()` de vue-i18n (migration future facilitée) :
  langues **FR + EN**, clés en chemin pointé + interpolation `{param}`, fallback FR puis clé brute.
  `$t` exposé globalement (typé pour `vue-tsc`), composable `useI18n()`. **Sélecteur de langue**
  (`LanguageSwitcher`, barre supérieure) avec **persistance** (`localStorage` — non sensible,
  contrairement au token d'auth) et mise à jour de `<html lang>`. Pilote : `NotFoundView` migrée.
  La migration des chaînes du reste de l'app est incrémentale (français = source de vérité).

### Tests
- Frontend **227** (+6 : `i18n.spec.ts` — traduction/interpolation/fallback/persistance +
  `LanguageSwitcher`) · `vue-tsc` propre.

## [Non publié] — Polish UX P2 : composant Icon (UX-11) (2026-06-07)

Branche `feature/ux-icon-component` (release `v1.0.0` → `rc.21`).
Voir `docs/modules/ux-design-system.md` (§ Composants).

### UX
- **Composant `Icon` (UX-11)** — `shared/ui/Icon.vue` centralise les petites icônes ligne
  redessinées à la main un peu partout : `<Icon name="plus" :size="14" />`. Registre **statique**
  de primitives SVG (whitelist, **pas de `v-html`**), grille 16×16, `currentColor` ; décoratif par
  défaut (`aria-hidden`), `title` → `role="img"`. Noms : plus, search, view, edit, close, trash,
  check, download, filter, chevron-left/right. Adopté sur **Produits** (bouton + recherche) et
  **Paiements** ; industrialisation incrémentale du reste des SVG inline.

### Tests
- Frontend **221** (+5 : `Icon.spec.ts` — primitives par nom, a11y décoratif/`title`, taille) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : filtres persistés dans l'URL (UX-12) (2026-06-07)

Branche `feature/ux-url-filters` (release `v1.0.0` → `rc.20`).
Voir `docs/modules/ux-design-system.md` (§ Filtres de liste persistés).

### UX
- **Filtres dans l'URL (UX-12)** — nouveau composable `useUrlFilters` : un objet de filtres
  réactif est synchronisé avec la query string. Les filtres **survivent au rafraîchissement et
  au bouton Précédent**, et une liste filtrée devient **partageable par URL**. Valeurs vides /
  par défaut omises (URL propre), types coercés, `router.replace` (pas de pollution d'historique).
  Adopté sur **Produits** et **Paiements** ; adoption incrémentale du reste.

### Tests
- Frontend **216** (+3 : `useUrlFilters.spec.ts` — hydratation, miroir, clés inconnues) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : migration BaseModal — 1ʳᵉ vague (UX-03) (2026-06-07)

Branche `feature/ux-basemodal-migration` (release `v1.0.0` → `rc.19`).
Voir `docs/modules/ux-design-system.md` (§ Migration des modales).

### UX
- **Modales unifiées (UX-03)** — `CustomerListView` (création/édition client) et `PaymentListView`
  (enregistrement de paiement) migrées de leur modale ad-hoc vers le composant partagé
  `<BaseModal>` : overlay, `Teleport`, **focus-trap**, Échap, clic-extérieur, en-tête et bouton
  de fermeture cohérents. Chrome local et styles `.modal-*` dupliqués supprimés. La fermeture est
  liée via `@update:model-value` pour préserver les effets de bord (réinitialisation du formulaire).
  Reste l'adoption incrémentale (~22 vues).

### Tests
- Frontend **213** (+1 : `PaymentListView.spec.ts` ouvre un `role="dialog"` `aria-modal`) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : cartes mobiles (UX-06) (2026-06-07)

Branche `feature/ux-mobile-cards` (release `v1.0.0` → `rc.18`).
Voir `docs/modules/ux-design-system.md` (§ Tableaux responsives).

### UX
- **Cartes empilées sur mobile (UX-06)** — nouveau pattern CSS-only `.data-table--cards` :
  sous **640px**, chaque ligne de tableau devient une **carte** ; chaque cellule porte
  `data-label="<colonne>"` (libellé affiché via `::before`), l'identité prend `.cell-primary`
  (titre pleine largeur) et les actions `.cell-actions`. Comme une carte a de la place
  verticale, les colonnes masquées par `.hide-mobile` y **réapparaissent** en lignes libellées.
  Le défilement horizontal reste le comportement par défaut. Adopté sur **Commandes, Clients,
  Paiements** ; adoption incrémentale du reste des listes (contrat documenté + testé).

### Tests
- Frontend **212** (+1 : contrat « card-stacking » dans `PaymentListView.spec.ts`) ·
  `vue-tsc` propre.

## [Non publié] — Polish UX P2 : feedback action (403) + page 404 (2026-06-07)

Branche `feature/ux-action-feedback` (release `v1.0.0` → `rc.17`).
Voir `docs/modules/ux-design-system.md`, `docs/recette/etat-des-lieux-v1.0.0.md`.

### UX
- **403 jamais silencieux (UX-10)** — le client API émet `api:forbidden` (message du backend) ;
  `useNotifications` l'écoute (une fois, au montage) et le remonte en **toast d'erreur** via la
  nouvelle fonction `pushToast(message, severity = 'error')`. Une action refusée par
  rôle / permission / module est désormais visible (auparavant : échec muet). Les toasts client
  portent `type: 'client'` (libellé « Accès refusé »).
- **Page 404 design-system (UX-14)** — `NotFoundView` (catch-all) reconstruite sur `StateBlock`
  (`empty`) + `BaseButton` (retour tableau de bord), cohérente avec `/unavailable` (402).

### Tests
- Frontend **211** (+4 : `composables/__tests__/useNotifications.spec.ts`) · `vue-tsc` propre ·
  `npm audit` 0 vulnérabilité.

## [Non publié] — Remédiation audit sécurité (2026-06-07)

Implémente l'intégralité des gates de l'audit sécurité (branche
`feature/security-audit-remediation`). Voir `docs/security/security-remediation-tests.md`,
`docs/modules/rbac.md`.

### Sécurité
- **Gating module fail-closed** — `module:<code>` étendu à **tous** les modules métier
  (catalog, inventory, orders, customers, payments + delivery/suppliers/import_export/
  reports) ; un tenant sans le module (ou sans aucune ligne `tenant_modules`) est **refusé**
  (un menu masqué n'est pas un contrôle d'accès). `dashboard` (cœur) reste actif.
- **Permissions métier sur les créations** — `POST` clients/paiements/commandes gardés par
  `role_or_permission:manager|admin|<module>.create` → un `viewer` ne peut plus créer.
- **Hiérarchie des rôles** — autorité centrale `RoleHierarchy` : un `manager` ne peut plus
  inviter, attribuer ni accorder temporairement le rôle `manager` (anti-escalade latérale).
- **Isolation multitenant** — le `parent_id` d'une catégorie doit appartenir au tenant courant.
- **Preuves de paiement privées** — stockées sur le disque privé (plus le disque public) ;
  payload tenant sans `proof_url` ; téléchargement admin via **URL signée courte**.
- **Chaîne d'audit vérifiable** — empreinte d'intégrité calculée via un payload canonique
  **partagé** entre création et vérification (`created_at` épinglé, `ts` unix s) →
  `verify-chain` valide réellement une chaîne propre.
- **Frontend** — token Bearer **en mémoire seule** (plus de `localStorage`/`sessionStorage`,
  legacy purgé) ; `v-html` sur les SVG de modules remplacé par un composant `ModuleIcon`
  (whitelist statique). Dépendances : `composer audit` / `npm audit` = 0 vulnérabilité.

### Tests
- Backend **638** (636 passés, 2 skipped) — `SecurityRemediationTest` (16) + `ModuleGatingTest`
  durci verts. Frontend **191** (gates `frontendSecurity` + `auth` verts) · `vue-tsc` propre.

### En attente
- Audit UX/UI (`docs/ux-ui/audit-ux-ui-approfondi.md`, P0/P1) et cahiers catalogue
  (produits spéciaux + duplication) — sessions dédiées, arbitrage produit requis.

## [1.0.0-rc.4] — 2026-06-06 (RBAC Phase B2 — rôles custom + permissions fines)

Achève le programme RBAC **A + B2 + C**. La Phase **B2** ajoute des rôles configurables
par tenant **et** applique réellement les permissions fines sur les écritures sensibles.

### Ajouté
- **RBAC B2.2 — application des permissions** : 15 groupes de routes d'écriture sensibles
  migrés de `role:manager|admin` → `role_or_permission:manager|admin|<perm-granulaire>`
  (Catalog, Customers, Suppliers, Inventory ×6, Orders ×2, Marketplace, Delivery,
  Import/Export, Reports) + void paiement gardé dans le contrôleur via `payments.delete`.
  Les permissions choisies sont **exclusives à admin/manager** → le comportement des rôles
  de base est **strictement préservé** (un `member` reste bloqué) ; un **rôle custom**
  porteur de la permission **passe la garde**.
- **3 permissions de gestion** ajoutées au seeder (admin/manager, accordables) pour les
  routes de cycle de vie sans permission CRUD dédiée : `orders.manage`, `delivery.manage`,
  `marketplace.manage`.
- **RBAC B2.3 — UI Paramètres → Rôles** (admin) : `RolesPanel.vue` + `roleService` —
  lister les rôles (base en lecture seule + custom éditables), créer/éditer un rôle custom
  (permissions `grantable` **regroupées par module**), supprimer. Les sélecteurs de rôle
  (onglet Équipe + invitation) incluent désormais les **rôles personnalisés**.

### Sécurité
- `role_or_permission` s'appuie sur `Gate::before` (Spatie) qui **avale**
  `PermissionDoesNotExist` → une route peut référencer une permission non seedée sans
  jamais transformer un `403` en `500` (robustesse des ~600 tests existants).

### Docs
- `docs/modules/rbac.md` (architecture 4 couches + **table route→permission**),
  `docs/user/roles.md` (guide admin), audit `docs/recette/rbac-acl-audit-v1.0.0.md`
  (B2 → **livré**), `docs/plan.md`.

### Reste avant GO ferme v1.0.0
- Recette finale + décision P6 (approche A recommandée) + CI install propre.
- En perspective : invitations email + login 2FA email.

### Tests
- Backend **622** (620 passés, 2 skipped) — dont `PermissionEnforcementTest` (9).
- Frontend **189** (+`roleService` ×5, `RolesPanel` ×3) · `vue-tsc` propre.

## [1.0.0-rc.3] — 2026-06-06 (RBAC durci)

Incrément depuis rc.1 (rc.2 = UI gaps ; rc.3 = RBAC A+C). Programme RBAC **A+B2+C**
décidé ; **A et C livrés**, **B2 (rôles custom + permissions fines) en session dédiée**.

### Ajouté
- **RBAC Phase A — gating module dynamique** : middleware `module:<code>` (data-driven) ;
  retirer un module à un tenant **bloque réellement ses routes backend** pour TOUS les
  utilisateurs (admins inclus), plus seulement les menus. Fail-open pour tenants non
  provisionnés. Modules gatés : `reports`, `suppliers`, `import_export`, `delivery`.
- **RBAC Phase C — accès temporaires auto-expirants** : `temporary_access_grants` +
  commande planifiée `access:revoke-expired` (chaque minute → expiration **sans action
  manuelle**) + UI « Accès temp. » (Paramètres → Équipe). `admin` non grantable temporairement.
- **UI gaps comblés (rc.2)** : Ajustements de stock (Stock → Ajustements) + édition des
  limites de plan (admin).

### Audits
- `docs/recette/rbac-acl-audit-v1.0.0.md` (5 exigences ↔ état + plan A/B2/C) ;
  `docs/recette/route-audit-v1.0.0.md` (réconciliation routes front↔back, 0 lien cassé).

### Reste avant GO ferme v1.0.0
- **RBAC Phase B2** : rôles custom par tenant (bornés par le plan) + migration
  `role:`→`permission:` des routes sensibles (session dédiée — blast-radius).
- Recette finale + décision P6 (approche A recommandée) + CI install propre.
- En perspective : invitations email + login 2FA email.

### Tests
- Backend **607** (605 passés, 2 skipped) · frontend **181** · `vue-tsc` propre.

## [1.0.0-rc.1] — 2026-06-06 (candidat production)

Release candidate figée depuis `develop` après v0.8.0. Décision : voir
[Go/No-Go v1.0.0](docs/recette/go-no-go-v1.0.0.md) (**GO conditionnel**).

### Ajouté
- **Landing & Upgrade localisés (P4/P5)** — la page tarifs publique **et** l'écran
  d'upgrade connecté consomment `GET /api/public/pricing` (source backend unique) ;
  plus aucun prix contractuel codé en dur côté frontend ; sélecteur pays/devise.
- **Multi-sites (Sprint 20)** — filtre par entrepôt sur Commandes, Paiements et
  Rapports (ventes/stock) ; **scoping d'accès par agence** (`user_warehouses`) :
  un membre non-manager assigné à des sites ne voit que leurs données (isolation
  testée end-to-end) ; assignation via Paramètres → Équipe (modale « Sites »).
- **Admin Règles pays (Sprint 21)** — CRUD super-admin des `CountryRule`
  (devise/fuseau/approbation/blocage/plans par pays).

### Modifié
- Convention de filtre entrepôt des services de liste passée en tableau
  (`?array $warehouseIds`) pour porter à la fois le filtre UI et la restriction d'accès.

### Supprimé
- Code mort : `CreateDeliveryRequest` / `UpdateDeliveryRequest` (règles vides,
  validation faite inline dans le contrôleur) ; artefacts `video_frames/*.png`.

### Tests
- Backend **597** (595 passés, 2 skipped MySQL-only), frontend **175**, `vue-tsc` propre,
  `vite build` OK. +`WarehouseScopeTest`, `WarehouseAccessScopingTest` (isolation HTTP),
  `AdminCountryRuleTest`, services pricing/reports/warehouses.

### À acter avant GO ferme
- Décision P6 (paiements locaux — approche A « manuel » recommandée, déjà fonctionnelle).
- Zones d'ombre : scoping d'accès limité aux **listes** (GET ressource-unique tenant-scopé).
- Branche par défaut GitHub `master` → `main`.

## [0.8.0] — 2026-06-05 (MVP consolidé, pré-1.0)

Première release consolidée depuis `v0.7.0` : MVP complet, audité et durci pour
de premiers clients réels.

### Ajouté
- **POS Web MVP** — module Caisse : sessions de caisse (ouverture/clôture avec
  rapprochement d'écart), encaissement (panier, scan, déclinaisons), atomique.
- **Pricing localisé** — `plan_prices` (10 marchés) + `plan_limits` ; API publique
  `GET /api/public/pricing` ; limites de plan éditables (super-admin).
- **Géolocalisation RGPD-safe** — `GET /api/public/geo` (headers edge/CDN, aucun
  appel tiers), repli locale navigateur.
- Couverture de tests : backend **570** (0 incomplete), frontend **154**
  (couverture 38.7 %), dont smoke tests admin back-office et tests sécurité
  (anti-escalade quotas, enforcement des sièges).
- **Démo exhaustive** — `DemoSeeder` couvre désormais TOUS les modules MVP
  (déclinaisons, mouvements de stock, entrepôts, POS, retours, marketplace,
  promotions, paiement manuel, périodes fiscales, imports, ajustements,
  transferts inter-entrepôts), idempotent et vérifié par `DemoSeederTest`.

### Corrigé
- **Sièges** : les plans payants ne plafonnent plus les utilisateurs
  (`max_users = null`) — fin de l'impasse « Business bloqué à 10 ».
- **Landing** : scroll restauré (verrou de viewport scopé aux shells app/admin).
- **Harnais de test** : `phpunit.xml memory_limit` (la suite OOM-ait à 128 Mo) ;
  `/** @test */` → `#[Test]` (PHPUnit 12) ; suffixes testsuite.

### Sécurité
- Sync (Phase 3) masqué derrière `FEATURE_SYNC` (off par défaut).
- Re-vérification approfondie : isolation multitenant, RBAC, intégrité — RAS.

### Nettoyage
- 32 fichiers stubs générés morts purgés (dont migrations parasites
  `paymentss`/`customerss`).

---

## [0.7.0] — Sprint 7A
- Billing SaaS, Admin back-office, Security, Marketplace (340 tests).

## [0.1.0-alpha.1]
- Bootstrap initial (Laravel 11 + système modulaire).
