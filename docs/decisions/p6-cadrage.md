# Cadrage Sprint P6 — Paiements locaux & checkout par devise

> **Statut : cadrage produit livré, en attente de validation fondateur.**
> Complète [`p6-checkout-approach.md`](p6-checkout-approach.md) (comparatif A/B) avec un **état de
> l'existant vérifié dans le code**, le **design technique** et un **plan incrémental livrable**.
> Issu d'un audit multi-agents (2026-06-09). Règle produit (plan.md, NO-GO l.561) : *chaque devise
> affichée doit correspondre à un flux de paiement **OU** à une mention « sur devis / paiement manuel ».*

## 1. État de l'existant (vérifié)

**Déjà en place :**
- **Pricing localisé cohérent** : `PublicPricingController` (10 marchés / 9 devises XOF·XAF·NGN·GHS·KES·ZAR·EUR·CAD·USD), table `plan_prices` source de vérité (repli `global`→USD), `PlansSeeder` (4 plans × 10 marchés).
- **Détection géo privacy-first** : `GET /api/public/geo` (headers edge CF/CloudFront/Vercel) + `useGeoContent.ts` (fallback locale, override manuel via `<select>` marché).
- **Module `Payments` (commercial)** : `PaymentService::record` (verrou anti-double-paiement, plafonnement au solde +1 %, idempotence `X-Idempotency-Key`, audit `payment.recorded`), `balance`/`isFullyPaid`. 5 méthodes = **libellés** (`cash`/`mobile_money`/`card`/`transfer`/`cheque`), sans connecteur ni machine à états.
- **Module `ManualPayment` (abonnement) — livré ET testé** : déclaration tenant + **preuve sur disque privé** + approbation/rejet admin (`changePlan` transactionnel) + URL signée 30 min + audit. **C'est le socle de l'approche A.**
- **Checkout atomique POS** : `PosService` (create→confirm→fulfill→record, devise héritée de la commande).
- **Patrons réutilisables** : `PlanPrice` (par marché), `CountryRule` (par pays, +CRUD super-admin audité, 30 pays), middleware `VerifyWebhookSignature` (HMAC-SHA256, anti-replay) — **prêt mais inutilisé**.

**Ce qui MANQUE (cœur de P6) :**
- **Aucun rail de paiement réel** (zéro SDK/clé Wave/OM/MoMo/M-Pesa/Flutterwave/Paystack/CinetPay/Stripe).
- **Aucune liaison programmatique marché → moyens de paiement** : le lien est **purement éditorial** (`paymentCopy`/`pricingNote` en dur dans `useGeoContent.ts`).
- **Aucune mention « manuel / sur devis » structurée** garantissant le DoD pour les devises sans PSP.
- **Webhooks non fonctionnels** : `VerifyWebhookSignature` sur **aucune route** et lit `config/billing.php` **inexistant** (code mort).
- Checkout **mono-devise** (figé `order.currency = tenant.settings.currency`, défaut XOF) ; méthodes **en dur** à 3 endroits.
- `CountryRule` (30 pays, devises GNF/MAD/TND…) et `MARKETS` (10 marchés) **disjoints, sans pont**.

## 2. Design retenu — table `market_payment_methods` (calquée sur `PlanPrice`)

Une table structurée, requêtable par API publique + admin, qui **matérialise le DoD** :

| Colonne | Rôle |
|---|---|
| `market_code` (+ `country_code` nullable) | granularité marché (override pays possible) |
| `currency` | devise du marché |
| `method` | `wave` · `orange_money` · `mtn_money` · `mpesa` · `bank_transfer` · `card` · `cash` … |
| `mode` | **`auto`** (rail PSP réel) · **`manual`** (preuve + validation admin) · **`quote`** (sur devis) |
| `is_active`, `display_order`, `label`/`metadata` | affichage |

→ `GET /api/public/payment-methods?market=…|country=…` (même `resolveMarket` que le pricing).
**Au départ : tout en `manual`/`quote`** (aligné sur `paymentCopy`), **aucun flux financier**. `CountryRule.metadata` reste réservé à l'inscription.

## 3. Plan incrémental (livrable, testé)

| RC | Titre | Périmètre | v1.0.0 ? |
|---|---|---|---|
| **P6-1** ✅ **LIVRÉ (rc.92)** | **Socle marché↔moyens + mention manuelle** | Table/modèle/migration/seeder `market_payment_methods` (10 marchés, tout `manual`/`quote`) + `GET /api/public/payment-methods`. **Zéro rail, zéro PSP.** +5 tests `PublicPaymentMethodsTest`. | ✅ |
| **P6-2** | **Brancher le checkout déclaratif (approche A) + admin** | Front : sélecteurs de paiement (`PosView` + bloc hors-POS) alimentés par l'API (fin du « en dur ») ; mention « manuel/sur devis » quand `mode≠auto` ; parcours client ManualPayment par marché. Admin : audit/validation/rejet/notifications. i18n FR+EN (garde CI). Tests. | ✅ |
| **P6-3** | *(post-1.0)* **Infra webhook/PSP sans rail actif** | Créer `config/billing.php` ; routes webhook derrière `webhook.signature` (stub no-op + flag) ; interface `PaymentGateway` (initiate/verify/refund) + machine à états `Payment` derrière feature flag. Tests signature/anti-replay. | ❌ |
| **P6-4** | *(post-1.0, par marché)* **Premier PSP réel** | 1 PSP sur 1 marché (Flutterwave/Paystack UEMOA-Nigeria **ou** Stripe Europe-Amérique du Nord, selon décision) : initiation, callback, webhook signé, réconciliation, remboursement. Bascule `mode` du marché `manual`→`auto`. Sprints isolés. | ❌ |

## 4. Arbitrages à trancher (recommandations)

1. **Approche checkout v1.0.0** → **A (manuel/sur-devis)** comme socle. *(débloque le NO-GO sans dépendance externe ; B en incréments isolés post-1.0).*
2. **Où stocker marché→moyens** → **nouvelle table `market_payment_methods`** (patron `PlanPrice`, porte le `mode auto/manual/quote`).
3. **DoD devises sans PSP (NGN/GHS/KES/ZAR/fallback)** → **mention systématique `manual`/`quote`** adossée à `ManualPayment` (jamais masquer un marché ni dégrader la devise).
4. **Checkout multi-devises (devise par commande) dès P6 ?** → **Non (minimal)** : garder `order.currency = devise tenant` ; le multi-devises par commande = incrément ultérieur si besoin réel (évite incohérence `payment.currency` vs `order.currency`).
5. **Machine à états paiement (pending/captured/failed/refunded)** → **seulement au 1er PSP (P6-4)** ; l'approche A se modélise déjà via `pending/approved/rejected` de `ManualPayment`.

## 5. Risques

- **NO-GO actif** : ne pas activer un encaissement par devise tant que le rail n'est pas documenté **et** disponible → P6-1/P6-2 restent **déclaratifs**.
- **Webhook cassé** (`config/billing.php` absent) → ne brancher aucune route `webhook.signature` avant P6-3.
- **Divergence `CountryRule`(30) vs `MARKETS`(10)** : hors des 10 marchés → USD global ; clarifier avant d'élargir la couverture paiement.
- **3 copies de `MARKETS`** (controller/seeder/front) : le mapping moyens **ne doit pas** ajouter une 4ᵉ source de vérité → centraliser via l'API.
- **i18n DoD** : tout écran créé/modifié (sélecteurs, mention, admin) livre FR+EN et passe la garde CI.
- **Pas de machine à états/remboursement** sur `Payment` commercial → prérequis de tout PSP asynchrone (P6-4).

## 6. Décisions attendues du fondateur

- [ ] Valider **approche A** pour v1.0.0 (socle manuel) — *recommandé*.
- [ ] Valider le design **`market_payment_methods`** (table structurée vs éditorial).
- [ ] Lancer **P6-1** (socle, zéro PSP) comme prochain incrément.
- [ ] *(post-1.0)* PSP cible + marché prioritaire (Flutterwave/Paystack vs Stripe).

> Tant que ces cases ne sont pas cochées, **aucun code de flux financier**. P6-1 (table + endpoint + mention)
> est non-engageant et sûr : il peut démarrer dès validation des points 1-3.
