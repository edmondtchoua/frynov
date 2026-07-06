# Audit — Système de mise à niveau de plan (Frynov ERP)

> Phase 1 (audit) du chantier « refonte de la mise à niveau de plan ».
> Date : 2026-07-05 · Branche : `feature/rc23-accounting-referential`
> Statut : **audit terminé, aucun code modifié**. Ce document est le livrable 15.1 (rapport d'audit).

---

## 0. Verdict exécutif

Contrairement à ce que laisse penser l'écran actuel (un simple formulaire « Demande de mise à niveau »),
le backend d'abonnement est **mature et déjà en production**. Il ne faut **pas** repartir de zéro : il faut
**combler des manques ciblés** (consentement, notifications, entité « demande de changement » de premier plan,
montant autoritatif côté serveur, aperçu d'impact de downgrade, UX multi-étapes) **sans casser** l'existant.

| Axe | État réel |
|---|---|
| Modèle de données abonnement | 🟢 Mature — `plans`, `plan_prices` (10 marchés / 9 devises), `plan_limits`, `subscriptions`, `promotions`, `manual_payments`, `tenant_credits` |
| Paiement manuel + preuve | 🟢 Complet — upload privé, URL signée 30 min, MIME/taille validés |
| Validation admin | 🟢 Complet — approve/reject, proration, ledger crédits, idempotence (`applied_at`) |
| Isolation tenant / IDOR | 🟢 Fail-closed — `TenantScope` sentinelle + `GuardPortalPrincipal` |
| Modules / RBAC / quotas | 🟢 Recalculés au changement de plan (activation + désactivation downgrade) |
| Audit trail | 🟢 Immuable, chaîné HMAC-SHA256 |
| **Consentement utilisateur** | 🔴 **Absent** |
| **Notifications (in-app/email)** | 🔴 **Absentes** sur soumission/approbation/refus |
| **Entité « demande de changement »** | 🟠 Conflée dans `manual_payments` (pending/approved/rejected) — pas d'états `draft/submitted/pending_validation/...` |
| **Montant autoritatif serveur** | 🟠 Champ libre pré-rempli côté client ; `preview-upgrade` existe mais le formulaire ne verrouille pas le total |
| **Aperçu d'impact downgrade (UI)** | 🔴 Le backend désactive les modules, mais aucun écran ne prévient l'utilisateur avant confirmation |
| **Snapshot de plan à la souscription** | 🟠 Pas de table dédiée ; on s'appuie sur les `subscriptions` soft-deleted |
| **Upgrade différé (prochain cycle)** | 🔴 `changePlan()` est immédiat uniquement |
| Taxes / frais d'installation | 🔴 Non implémentés (potentiellement hors périmètre v1) |

Légende : 🟢 conserver · 🟠 refondre/compléter · 🔴 construire.

---

## 1. Inventaire de l'existant (ce qui EST là)

### 1.1 Modèles & tables (module `Billing`)

| Modèle | Table | Rôle | État |
|---|---|---|---|
| `Plan` | `plans` | Gabarit de plan (`code`, quotas legacy, `trial_days`, `features` JSON) | 🟢 |
| `PlanPrice` | `plan_prices` | Prix multi-marché/devise/intervalle. Unique `(plan_id, market_code, interval)` | 🟢 |
| `PlanLimit` | `plan_limits` | Quotas (`max_products`, `max_monthly_orders`, `storage_mb`…) | 🟢 |
| `Subscription` | `subscriptions` | Abonnement tenant. Statuts : `trialing/active/past_due/suspended/cancelled/pending_approval`. SoftDeletes | 🟢 |
| `Promotion` / `PromoUse` | `promotions` / `promo_uses` | Codes promo (`percent`/`fixed_cents`), usage unique `(promotion_id, tenant_id)` | 🟢 |
| `ManualPayment` | `manual_payments` | Soumission paiement + preuve + résolution périodicité | 🟢 (mais surchargé, cf. §3) |
| `TenantCredit` | `tenant_credits` | Ledger signé (overpaid/proration/consumption/adjustment) | 🟢 |
| `MarketPaymentMethod` | `market_payment_methods` | Méthodes dispo/marché, mode `auto/manual/quote` | 🟢 |
| `ErpModule` / `TenantModule` / `plan_modules` | idem | Catalogue modules, activation par tenant | 🟢 |

**Plans réels** (mensuel, base XOF) : Découverte `0` · Essentiel `9 900` · Croissance `24 900` · Business/Enterprise `59 900`.
Annuel = 10× mensuel. Tous les modules sont inclus dans tous les plans (monétisation par **sièges + quotas**, pas par masquage de modules).

### 1.2 Endpoints

Tenant (`app/Modules/Billing/routes/api.php`, `auth:sanctum`) :
- `POST /api/me/subscription/preview-upgrade` — aperçu proration (lecture seule)
- `POST /api/me/promo/validate` — validation promo temps réel
- `POST /api/me/manual-payments` — **soumission (le formulaire actuel)**
- `GET  /api/me/manual-payments` — historique tenant

Admin (`app/Modules/Platform/routes/api.php`, `RequireAdmin` = `is_super_admin`) :
- `GET /api/admin/manual-payments` (+ `{id}`)
- `POST .../{id}/approve` · `.../{id}/reject`
- `GET .../{id}/proof` (middleware `signed`, 30 min)

### 1.3 Sécurité (déjà en place)
- Preuves sur disque **privé** `storage/app/private/payment-proofs/{tenant}/`, jamais public ; téléchargement admin via URL signée.
- `TenantScope` global fail-closed (sentinelle UUID `000…000`) → pas d'IDOR même si un contrôleur oublie le filtre.
- `EnsureTenantHasModule` fail-closed (403 si tenant absent ou module inactif ; super-admin bypass).
- `EnforceQuota` → `402 Payment Required` au dépassement.
- `AuditLog` immuable (boot hooks bloquent update/delete) + chaînage d'intégrité.

---

## 2. Ce qui est INCOMPLET / INCOHÉRENT / DANGEREUX

| # | Constat | Sévérité | Localisation |
|---|---|---|---|
| G1 | **Aucun consentement** n'est capté (ni case, ni table, ni horodatage/IP/version de texte) | 🔴 Haute (légal/traçabilité) | Frontend + backend |
| G2 | **Aucune notification** utilisateur/admin (soumission, en attente, approuvé, refusé, activé…) | 🔴 Haute (UX/ops) | Backend |
| G3 | **Montant = saisie libre** pré-remplie côté client ; le total réel n'est pas verrouillé sur un calcul serveur autoritatif | 🟠 Moyenne | `SettingsView.vue` L930-970 · `BillingController::submitPayment` L148 |
| G4 | **`manual_payments` conflè** « demande de changement » et « preuve de paiement » ; pas de machine à états `draft/submitted/pending_validation/approved/rejected/cancelled/expired/activated/failed` | 🟠 Moyenne (modèle) | `ManualPayment` |
| G5 | **Downgrade sans aperçu d'impact** : le backend désactive les modules, mais l'utilisateur n'est pas prévenu (utilisateurs/produits en trop, modules perdus) avant de confirmer | 🔴 Haute (perte de service perçue) | Frontend + backend service |
| G6 | **Pas de snapshot de plan** à la souscription ; on reconstruit via `subscriptions` soft-deleted | 🟠 Moyenne | Modèle |
| G7 | **Upgrade différé (prochain cycle) impossible** : `changePlan()` est immédiat | 🟠 Moyenne | `SubscriptionService` |
| G8 | `Tenant.plan` / `Tenant.subscription_status` **dénormalisés** sans garde de synchro | 🟠 Moyenne | `Tenant` |
| G9 | Incohérence garde admin : `me/promo/apply` utilise `role:admin` (Spatie) alors que l'approbation utilise `is_super_admin` | 🟡 Basse | `Billing/routes/api.php:14` |
| G10 | Frontend : **pas de contrôle de taille de fichier**, promo **non revalidée à la soumission**, **pas de case de consentement** | 🟡 Basse | `SettingsView.vue` |
| G11 | Colonnes legacy `plans.price_*_cents` encore peuplées (fallback) alors que `plan_prices` fait foi | 🟡 Basse | `Plan` / seeder |
| G12 | Taxes & frais d'installation non modélisés | 🟡 Basse (hors v1 ?) | — |
| G13 | Aucun événement métier dispatché au changement de plan (seul l'audit log trace) → pas de hook pour notifications/webhooks | 🟠 Moyenne | `SubscriptionService` |

**Note « bug » du montant 5 990 000** : ce n'est **pas** un bug. Convention `amount_cents = valeur × 100`. Le front affiche `59 900`, envoie `5 990 000` cents. À clarifier en UX, pas à corriger en logique.

---

## 3. Diagnostic du cœur du problème

Le système a été construit **paiement-d'abord** (`ManualPayment`) et non **demande-de-changement-d'abord**.
Conséquence : le « changement de plan » n'existe pas comme objet de premier plan avec son cycle de vie, son
consentement, ses notifications et son montant autoritatif. Tout est déduit d'un paiement + d'une approbation admin.

La refonte consiste donc à **introduire une entité `SubscriptionChangeRequest`** qui :
- porte la machine à états complète (`draft → submitted → pending_payment/pending_validation → approved → activated` / `rejected/cancelled/expired/failed`) ;
- référence 0..n `ManualPayment` (le paiement devient une **pièce** de la demande, pas la demande) ;
- calcule et **fige** le montant côté serveur (réutilise `PaymentPeriodResolver` + `previewProration`) ;
- capte le **consentement** et déclenche les **notifications** via un événement métier.

C'est additif : `manual_payments` reste, on le relie à la nouvelle entité. Aucun abonnement existant n'est cassé.

---

## 4. Correspondance avec les 15 phases demandées

| Phase demandée | Déjà couvert | À construire |
|---|---|---|
| 2 — Modèle de plans configurable | Prix/quotas/modules multi-marché | Statut brouillon/archivé, badges, prix trim/semestriel, snapshot |
| 3 — Upgrade/downgrade/périodicité | Upgrade immédiat + proration | Upgrade différé, aperçu d'impact downgrade, machine à états explicite |
| 4 — Calcul automatique du montant | `preview-upgrade`, résolveur de périodicité | Endpoint `calculate-upgrade` autoritatif, champ verrouillé |
| 5 — Formulaire complet | Champs de base | Wizard multi-étapes, résumé, consentement |
| 6 — Notifications | — | Tout (in-app + email, historisées) |
| 7 — Consentement | — | Tout (table + auto/manuel) |
| 8 — Validation admin | approve/reject/audit | request-correction, forcer activation tracée |
| 9 — Impact modules/RBAC/quotas | Recalcul au changement | Aperçu **avant** confirmation, mode lecture seule vs blocage |
| 10 — Modèle de données | ~80 % des tables listées | `subscription_change_requests`, `subscription_consents`, `subscription_notifications`, `subscription_snapshots`, events |
| 12 — API | preview/promo/manual-payments/admin | `calculate-upgrade`, `change-requests` CRUD, `consents`, `notifications` |
| 13 — Sécurité | IDOR/isolation/fichiers/audit | Montant autoritatif (retire la confiance client), verrouillage demandes validées |
| 14 — Tests | Tests billing existants | Tests des 24 critères d'acceptation |

---

## 5. Roadmap proposée (incrémentale, non cassante)

- **P0 — Fondations sûres** : endpoint `calculate-upgrade` **autoritatif** + champ montant **verrouillé** côté front + revalidation promo à la soumission + contrôle taille fichier. *(Ferme G3/G10, base de tout le reste.)*
- **P1 — Entité & états** : `SubscriptionChangeRequest` + machine à états + rattachement des `manual_payments` + snapshot de plan. *(G4/G6.)*
- **P2 — Consentement + Notifications** : table `subscription_consents`, événement métier au changement, notifications in-app + email historisées. *(G1/G2/G13.)*
- **P3 — Downgrade sûr** : service d'aperçu d'impact + écran de confirmation (modules perdus, sièges/produits en trop, lecture seule vs blocage). *(G5/G9.)*
- **P4 — UX wizard** : refonte du slide-over en parcours 6 étapes + upgrade différé (prochain cycle). *(Phase 5/11, G7.)*
- **P5 — Backoffice plans & analytics** : gestion des plans (brouillon/archivé/badges), taxes/frais optionnels, rapports d'abonnement. *(Phase 2/15.5, G11/G12.)*

Chaque palier livre **backend + frontend + tests + docs (FR/EN)** en parallèle, conformément aux règles projet.
