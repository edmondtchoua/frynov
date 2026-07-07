# Module — Changement de plan (mise à niveau)

Refonte progressive du parcours de mise à niveau d'abonnement. Voir l'audit fondateur :
[docs/audit/plan-upgrade-audit.md](../audit/plan-upgrade-audit.md).

Le socle d'abonnement (plans, prix multi-marché, proration, avoirs, promos, paiements manuels,
validation admin, quotas, audit) est **déjà mature**. Ce chantier **comble des manques** sans casser
l'existant : montant autoritatif, entité de demande, consentement, notifications, aperçu d'impact,
UX en assistant. Roadmap : P0 → P5 (voir l'audit §5).

---

## P0 — Montant autoritatif serveur ✅

**Problème fermé (audit G3/G10, critère d'acceptation #22)** : le montant à payer était une saisie
libre pré-remplie côté client. Un utilisateur pouvait donc soumettre n'importe quelle valeur.

### Devis calculé serveur-side

`UpgradeQuoteService::quote()` (service PUR, aucune écriture) compose trois sources déjà éprouvées :

1. **Brut** — `Plan::priceForMarket($market, $interval)` (`plan_prices`), marché résolu serveur-side
   (abonnement courant → devise canonique → hint UI validé → `global`) ;
2. **Remise promo** — `PromotionService::validate()` + `Promotion::applyDiscount()` ; une promo
   invalide est renvoyée avec `valid=false` et **n'entre pas** dans le total ;
3. **Avoir de proration** — `SubscriptionService::previewProration()`, borné au brut après promo.

Le résultat immuable {@see `App\Modules\Billing\Services\UpgradeQuote`} :
`base_gross_minor → promo → applied_credit → net_payable_minor`. Montants en unités **mineures**
(XOF/XAF exposant 0).

### Endpoints

| Méthode | Route | Rôle |
|---|---|---|
| `POST` | `/api/me/subscription/calculate-upgrade` | Devis autoritatif (lecture seule) : brut, promo, proration, net |
| `POST` | `/api/me/manual-payments` | Soumission — `amount_cents` devient **optionnel** |

**Durcissement du submit** : `amount_cents` est désormais nullable. Absent → le backend impose le
**net autoritatif** du devis et la **devise** du marché résolu (parcours verrouillé standard). Présent
(acompte mobile money) → la valeur est stockée telle quelle, mais la **cible** (`target_amount_minor`)
reste dérivée des `plan_prices` serveur : un montant client ne peut pas falsifier le plan visé.

### Frontend

- `frontend/src/services/subscriptionService.ts` — `calculateUpgrade()` typé.
- `SettingsView.vue` (drawer de mise à niveau) : sélecteur de **périodicité** (mensuel/annuel), champ
  montant remplacé par un **encart devis verrouillé** avec détail (prix → remise → avoir → total),
  revalidation promo au blur, **contrôle de taille de fichier** 5 Mo, garde anti-course sur le devis.
  À la soumission, le front **n'envoie plus** `amount_cents`.

### Tests

`backend/tests/Feature/Billing/CalculateUpgradeTest.php` (7 cas) — couvre les critères d'acceptation
#3 (recalcul au plan), #4 (au changement de périodicité), #5 (promo valide), #6 (promo invalide),
#22 (montant client non falsifiable), plus l'auth requise et la cible serveur-authoritative.

### Limites connues (traitées plus tard)

- L'assistant multi-étapes complet (6 étapes dans le drawer) arrive au palier UX (P4).
- Combinaison promo + proration : lignes affichées séparément, net planché à 0 — le règlement exact
  reste géré à l'approbation par `PaymentPeriodResolver` (inchangé).

---

## P1 — Demande de changement, machine à états & snapshot ✅

**Problème fermé (audit G4/G6)** : le système était **paiement-first** — le changement de plan
n'existait pas comme objet ; tout était déduit d'un `ManualPayment` + approbation admin.

### Entité `SubscriptionChangeRequest`

Table `subscription_change_requests` (migration `2026_07_05_000001`). Objet de premier plan qui porte
le cycle de vie ; un `manual_payments.change_request_id` (nullable, rétro-compatible) rattache le
paiement comme **pièce**.

**Machine à états** (transitions gardées dans le modèle) :

```
draft → submitted → pending_payment | pending_validation → approved → activated
        (+ sorties : rejected / cancelled / expired / failed)
```

Toute transition hors table `TRANSITIONS` est refusée (`DomainException`). L'historique est journalisé
dans `metadata['transitions']` (`from/to/at/by/note`).

### Snapshot immuable

`plan_snapshot` (JSON) fige à la création : code/nom/description du plan cible, intervalle, marché,
devise, brut/net, quotas (`plan_limits`), features, modules inclus. **Une modification ultérieure du
plan n'altère jamais une demande existante.** Les montants (`base_gross_minor`, `promo_discount_minor`,
`proration_credit_minor`, `net_payable_minor`) figent le devis autoritatif P0.

### Intégration au flux paiement (non cassante)

- `BillingController::submitPayment` crée **atomiquement** (transaction) la demande
  (`openForPayment` → `pending_validation`) puis le paiement rattaché.
- `ManualPaymentService::approve/reject` appellent `syncFromPayment()` : `matched/overpaid/free/settled`
  → `activated` ; `partial` → `pending_payment` ; `needs_review/unmatched` → reste `pending_validation` ;
  refus → `rejected`. Tolérant et idempotent (les paiements legacy sans demande sont ignorés).

### Endpoints tenant

| Méthode | Route |
|---|---|
| `GET`  | `/api/me/subscription/change-requests` |
| `POST` | `/api/me/subscription/change-requests` (brouillon) |
| `GET`  | `/api/me/subscription/change-requests/{id}` |
| `POST` | `/api/me/subscription/change-requests/{id}/submit` |
| `POST` | `/api/me/subscription/change-requests/{id}/cancel` |

Isolation : `TenantScope` fail-closed → 404 pour la demande d'un autre tenant (anti-IDOR).

### Tests

`backend/tests/Feature/Billing/ChangeRequestTest.php` (6 cas) : création liée à la soumission,
approbation → activation (critères #12/#13/#14), refus → rejet, brouillon→soumis→annulé, transition
interdite refusée, isolation tenant (#21).

### Reste à faire (P2+)

- UI tenant de l'historique des demandes (livrée avec l'assistant, P4).
- Snapshot **de l'abonnement** au moment de la souscription (table dédiée) si besoin au-delà du snapshot
  de plan porté par la demande.

---

## P0.1 — Durée multi-période, tarif annuel ×12, promotions automatiques ✅

Extension du devis P0 (décisions produit validées).

### Durée (payer N périodes d'avance)

`quote(tenant, plan, interval, quantity, promoCode, marketHint)` — `quantity` borné : **mensuel 1–12**,
**annuel 1–{@see UpgradeQuoteService::MAX_YEARS}=5** (borne API `max:12`, affinée serveur par intervalle).
`subtotal = unitGross × quantity` ; `net = subtotal − promo − proration`.

**Règlement de N périodes** : `SubscriptionService::changePlan(..., periods: N)` étend la période de N
(`+N mois` / `+N ans`). À l'approbation, un paiement rattaché à une demande de `quantity > 1` passe par
`ManualPaymentService::approveMultiPeriod` : règlement sur le **net autoritatif** de la demande (pas de
détection de périodicité — l'intervalle et la quantité sont connus), N périodes accordées, trop-perçu
crédité au ledger. **Proration réservée à `quantity == 1`** (le prépaiement en gros ne se combine pas
avec le reliquat mi-cycle) → le chemin monoperiode existant reste **inchangé** (zéro régression).

### Tarif annuel = ×12

`PlansSeeder` : `yearly = monthly × 12` (auparavant ×10 « 2 mois offerts »). Décision produit : plus de
remise annuelle implicite. La page pricing publique masque déjà son badge d'économie quand
`savings_pct == 0` (aucun « 0 % » affiché). **Nécessite `php artisan db:seed --class=PlansSeeder`** sur
les environnements existants.

### Promotions « en cours » automatiques

`PromotionService::activeFor()` sélectionne la meilleure promotion active, valide, applicable au plan et
non utilisée par le tenant — **sans code**. Un code saisi reste prioritaire. La remise est limitée aux
**périodes couvertes** par la fenêtre de validité (`valid_from`/`valid_until`) : pourcentage → par
période couverte ; montant fixe → remise unique. Le devis expose `promo.source` (`code`/`auto`) et
`promo.covered_periods`.

### Frontend

`SettingsView.vue` : sélecteur de durée (mois/années), détail enrichi (prix unitaire → × durée →
remise [+ badge « auto » + périodes couvertes] → avoir → total). `quantity` transmis au devis et au
submit. i18n FR+EN.

### Migration

La colonne `subscription_change_requests.quantity` est ajoutée dans la migration P1 (branche non
publiée) → `php artisan migrate` sur les environnements existants.

### Tests

`MultiPeriodQuoteTest` (5 cas) : total × durée, bornes par intervalle (+ refus 422 au-delà), promo
« en cours » auto, couverture partielle de la promo, activation de N périodes à l'approbation.

### Limite documentée

Le prépaiement multi-période (`quantity > 1`) n'ouvre pas de proration ni la mécanique d'acomptes fine
du monoperiode ; un versement partiel reste `partial` (sans activation) jusqu'au solde.

---

## P2 — Consentement + Notifications ✅

### Consentement (Phase 7)

Table `subscription_consents` (migration `2026_07_05_000002`) + modèle immuable
{@see `App\Modules\Billing\Models\SubscriptionConsent`}. Champs : `tenant_id`, `user_id`, `action_type`,
`consent_text`, `consent_version`, `accepted_at`, `ip_address`, `user_agent`, `source`
(`platform`/`admin`/`email`/`phone`/`document`/`other`), `related_entity_type`+`related_entity_id`.

`ConsentService` centralise le **texte + version** (source de vérité) : `recordPlatform()` (case cochée,
IP/user-agent captés depuis la requête, source `platform`) et `recordManual()` (accord hors plateforme
saisi par un admin). Le texte servi au front l'est verbatim via `GET /api/me/subscription/consent-text`.

**Obligatoire** : `submitPayment` valide `consent => accepted` (422 sinon) et enregistre le consentement
**atomiquement** avec la demande + le paiement, lié à la `SubscriptionChangeRequest`.

Front : case obligatoire dans le drawer affichant le texte serveur ; soumission bloquée sans coche ;
`consent` + `consent_version` envoyés. i18n FR+EN.

### Notifications (Phase 6) — réutilise l'outbox existant

Pas de système parallèle : `SubscriptionNotifier` s'appuie sur `NotificationService::notify()`
(module Notifications → `notification_outbox`, **historisé**, expédié par le cron
`notifications:flush-outbox`). Best-effort (jamais bloquant).

Événements couverts (modèles globaux seedés par la migration `2026_07_05_000003`, canal email, locale fr) :

| Transition | Modèle | Destinataire |
|---|---|---|
| Demande soumise | `billing.subscription_submitted` | Tenant (accusé) |
| Demande soumise | `billing.subscription_admin_new` | Super-admins (alerte) |
| Plan activé | `billing.subscription_activated` | Tenant |
| Demande refusée | `billing.subscription_rejected` | Tenant (avec motif) |

Câblage : `SubscriptionChangeRequestService` appelle le notifier dans `openForPayment` (submitted) et
`syncFromPayment` (activated/rejected). Destinataire tenant = `settings.billing_email` sinon 1er
utilisateur (mirroir de `RenewalService`) ; super-admins = `is_super_admin`.

### Tests

`ConsentTest` (3) — refus sans consentement (#18), trace immuable (texte/version/IP/entité liée),
endpoint texte. `SubscriptionNotificationTest` (3) — outbox rempli à la soumission (#19/#20),
à l'activation, au refus.

### P2b — Notifications in-app (cloche) ✅

Fil in-app d'abonnement, en complément de l'e-mail : table `subscription_notifications` (migration
`2026_07_05_000005`) + modèle `SubscriptionNotification`. `SubscriptionNotifier` dépose une notification
in-app (`inApp()`) à chaque événement tenant (submitted/activated/rejected), **en plus** de l'e-mail.

Endpoints tenant : `GET /api/me/subscription/notifications` (fil + compteur non-lu),
`POST .../{id}/read` (isolation TenantScope → 404 cross-tenant).

Front : `useNotifications` **fusionne deux sources** (`/api/marketplace/alerts` + le fil abonnement),
trie par date, et route le mark-as-read selon `source`. `NotificationCenter` étiquette les items
d'abonnement « Abonnement ». Vérifié en navigateur (cloche « (1) » → panneau « Abonnement — Nouveau
plan activé »).

Test : `InAppNotificationTest` (2) — création à la soumission + marquage lu + isolation tenant.

---

## Écran admin de validation (P8) ✅

La validation *fonctionnait* déjà (l'admin approuve/rejette les `manual_payments` via `ManualPaymentView`,
ce qui active/refuse la demande liée par la sync P1). Manquait le **contexte** : l'admin ne voyait que
le montant. `ManualPayment::toAdminArray()` expose désormais un bloc `change_request` :
`from/to_plan_code`, `change_type`, `interval`, `quantity`, `effective`, `net_payable_minor`, et
`consent_captured` (le consentement a-t-il bien été recueilli ?). Chargé sans N+1 (`with('changeRequest')`).

Front (`ManualPaymentView` admin) : sous le plan, un rappel « plan source → plan cible · périodicité ×
durée · prise d'effet · ✓/⚠ consentement ». Les paiements legacy sans demande s'affichent normalement
(rétro-compatible). Vérifié en navigateur (super-admin : « starter → essential · monthly ×1 · immédiat ·
✓ consentement »).

Test : `AdminReviewContextTest` — la liste de validation porte bien le contexte de la demande.

Consentement manuel admin : `ConsentService::recordManual()` est prêt côté service (utilisé pour tracer
un accord hors plateforme) ; le bouton dédié dans l'écran admin pourra l'appeler au besoin.

---

## Options additionnelles ✅

### Taxes & frais d'installation

`plans.tax_rate_bps` (points de base, 1800 = 18 %) + `plans.setup_fee_minor` (frais UNIQUE). Le devis
ajoute `tax_minor` (sur le brut après promo) et `setup_fee_minor` (uniquement sur un vrai **changement**
de plan, pas un renouvellement). Figés sur la demande. **Règlement** : un paiement portant taxe/frais est
routé vers la branche « net autoritatif » (le résolveur, qui matche le prix de base, les prendrait sinon
pour un trop-perçu) ; le mono simple reste inchangé. Admin : édition `tax_rate_bps`/`setup_fee_minor`.
Front : lignes « Frais d'installation » + « Taxe (X %) » dans le devis. Test `TaxAndFeeTest`.

### Paiement automatisé (PSP) — désactivé par défaut

Abstraction `PspGateway` + driver `FakePspGateway` (checkout factice + webhook signé HMAC), lié par
config (`billing.psp.driver`). `POST /api/me/subscription/psp/initiate` crée la demande + un paiement
`psp` et renvoie l'URL de checkout ; `POST /api/webhooks/psp` (public, authentifié par le driver)
confirme et **active automatiquement** en réutilisant `ManualPaymentService::approve` (approbation
système par un super-admin). Flag `billing.psp.enabled` (défaut **false** → 503). Prêt à brancher un vrai
rail (Flutterwave/Paystack/Stripe) sans toucher au flux. Test `PspPaymentTest`.

### Demande de correction (admin)

Alternative douce au refus : `POST /api/admin/manual-payments/{id}/request-correction` garde le paiement
en attente, repasse la demande en `pending_payment` avec la consigne (`metadata['correction_note']`) et
**notifie** le tenant (in-app + e-mail). Front : bouton « Corriger » dans l'écran admin. Test
`RequestCorrectionTest`.

### Analytics (graphes)

Panneau « Statistiques » dans l'écran admin des plans (barres CSS, sans dépendance) consommant
`GET /api/admin/plans/analytics` : revenu récurrent par plan, abonnements actifs par plan, demandes par
statut.

---

## P3 — Downgrade sûr : aperçu d'impact ✅

**Principe** : ne JAMAIS supprimer de données métier à cause d'un changement de plan. On **avertit**
avant confirmation ; la restriction effective est déjà assurée par l'existant (`EnsureTenantHasModule`
+ `EnforceQuota` → au-delà des limites, la **création** est bloquée, les données restent consultables).

### Backend

`DowngradeImpactService::forPlan(tenant, targetPlan)` (lecture seule) renvoie
`{is_downgrade, from_plan, to_plan, modules_lost[], quota_overages[], has_impact}` :
- `is_downgrade` : `sort_order(cible) < sort_order(courant)` ;
- `modules_lost` : modules du plan courant absents du plan cible (aujourd'hui vide — tous les modules
  sont sur tous les plans — mais la mécanique reste correcte) ;
- `quota_overages` : pour `users/products/customers/warehouses/orders`, l'**usage courant** dépassant
  la **limite du plan cible** → `{resource, usage, limit, excess}`.

`QuotaService` gagne deux méthodes réutilisables (additives, sans toucher l'enforcement) :
`usage(tenant, resource)` (best-effort, 0 sur erreur de schéma) et `planLimit(plan, resource)`.

Endpoint : `POST /api/me/subscription/downgrade-impact` (`plan_code`).

### Frontend

Dans le drawer, à la sélection d'un plan, `refreshImpact()` interroge l'endpoint et affiche un **encart
d'avertissement ambre** (si `has_impact`) : modules retirés + dépassements de quotas, avec le message
rassurant « vos données ne seront pas supprimées… ». i18n FR+EN (libellés de ressources inclus).

### Tests

`DowngradeImpactTest` (2) : un downgrade signale le dépassement d'utilisateurs (#17) ; un upgrade n'a
aucun impact. Vérifié aussi en navigateur (pro → Découverte : « 4 utilisé(s) pour 1 inclus, 3 en trop »).

---

## P4 — Assistant multi-étapes (drawer) + upgrade différé ✅

### Assistant dans le drawer

Le formulaire mono-bloc devient un **assistant en 5 étapes** (dans le même `BaseModal`) :
1. **Plan** (plan actuel + sélection + aperçu d'impact P3) ;
2. **Périodicité & durée** (mensuel/annuel + durée + devis autoritatif) ;
3. **Paiement** (méthode + preuve + code promo + notes) ;
4. **Résumé & consentement** (récap ancien→nouveau plan, durée, total, prise d'effet, case obligatoire) ;
5. **Confirmation** (référence de la demande + prochaines étapes).

Stepper visuel (états à venir / actif / fait ✓), navigation Retour/Suivant avec validation par étape
(`canProceed`), bouton Soumettre à l'étape 4. i18n FR+EN.

### Upgrade différé (prise d'effet au prochain cycle)

Champ `effective` (`immediate`|`next_cycle`) proposé **uniquement si un plan payant est en cours**
(`canDeferChange`). Chaîne complète :
- stocké sur la `SubscriptionChangeRequest` (`effective`) ;
- à l'**approbation** : si un plan payant est actif à échéance future, `ManualPaymentService::approveDeferred`
  encaisse le paiement et **planifie** le changement (`subscriptions.metadata['scheduled_change']`) — la
  demande passe en `approved` (pas `activated`) et `syncFromPayment` la laisse en l'état (garde P4) ;
- au **renouvellement** : `RenewalService::expirePeriods` applique le changement planifié à l'échéance
  (`applyScheduledChange` → `changePlan(periodStart = fin du cycle courant, periods)`), active la demande
  et audite `billing.scheduled_change_applied`. Best-effort (un échec ne bloque pas le dunning).
- Cas trivial : `next_cycle` sans plan payant en cours → activation immédiate.

### Tests

`DeferredUpgradeTest` (1) : `next_cycle` → approbation = **planifiée** (demande `approved`, plan inchangé,
`scheduled_change` posé) ; puis échéance atteinte → le cron **applique** (nouvel abonnement Enterprise
actif, demande `activated`). Vérifié en navigateur : parcours complet des 5 étapes jusqu'à la
confirmation (référence affichée).

### Limite documentée

Le devis d'un changement `next_cycle` applique encore la proration de la quantité 1 (le reliquat n'a pas
de sens en différé) — à affiner si besoin ; sans plan payant en cours, l'option n'est pas proposée.

---

## P5 — Back-office des plans ✅

Réutilise l'admin existant (`AdminPlanController`, `PlanListView.vue`, `adminService`).

### Cycle de vie éditorial

Colonnes `plans.status` (`active`|`draft`|`archived`) + `badge` (migration `2026_07_05_000004`).
Constantes + `Plan::isSelectableByTenant()` + `scopeSelectable()` (is_active + is_public + status active).

### Sécurité (Phase 13)

Les 5 points de sélection tenant (`calculate-upgrade`, `submit`, `downgrade-impact`, `change-requests`)
passent par `->selectable()->firstOrFail()` → un plan **brouillon/archivé renvoie 404**. `PublicPricingController`
filtre aussi `->selectable()`. Un plan non `active` n'est donc **jamais** proposé ni chiffrable côté tenant.

### Endpoints admin (super-admin)

| Méthode | Route | Rôle |
|---|---|---|
| `POST`   | `/api/admin/plans`            | Créer (brouillon par défaut ; prix `global` seedés → chiffrable) |
| `DELETE` | `/api/admin/plans/{plan}`     | **Archiver** (jamais de suppression dure ; abonnements intacts) |
| `PATCH`  | `/api/admin/plans/{plan}`     | + `status`/`badge` |
| `GET`    | `/api/admin/plans/analytics`  | Adoption + revenu récurrent par plan + demandes par statut |

`store` crée aussi les `plan_prices` `global` (mensuel/annuel) pour un chiffrage immédiat via repli marché.

### Frontend (admin)

`PlanListView.vue` : badge de statut (Actif/Brouillon/Archivé) + badge d'affichage sur chaque carte,
sélecteur de statut + badge dans l'édition, bouton **Archiver**, modale **Créer un plan** (brouillon).

### Tests

`AdminPlanManagementTest` (4) : création brouillon non sélectionnable (#… sécurité) → publication →
sélectionnable ; archivage retire de la sélection ; non-admin interdit (403) ; analytics. Vérifié en
navigateur (super-admin : cartes ACTIF, création → carte « Brouillon »).
