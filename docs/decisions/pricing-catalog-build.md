# Build — Pricing (périodicité + proration) & Catalogue (stock variantes + produits spéciaux)

> **Plan de build vivant** issu d'un audit multi-agents (2026-06-10). Consolide 4 sous-chantiers en
> incréments livrables (1 RC = 1 unité testable). Le français reste la source de vérité.

## 🎯 Défauts produit adoptés
- **Annuel = mensuel ×10** (≈ 2 mois offerts, -16,7 % ; 0 reste 0).
- **Acompte échelonné** : abonnement en `past_due`, **accès activé une fois soldé** (reste dû tracé).
- **Détection périodicité au paiement** : match montant↔prix, **tolérance ±1 %** ; `market_code` stocké sur le paiement.
- **Proration** : crédit = **avoir reporté** (non remboursé), arrondi **par exposant devise** (XOF = 0 décimale).
- **Produits spéciaux** : périmètre = **digital + garanties + licences** (IMEI/VIN = SKU/GTIN, déjà livrés).

## 🧱 Streams & dépendances
- **Billing** (séquentiel) : `RC-0` socle → périodicité `RC-1A→D` → proration `RC-2A→D`.
- **Stock variantes** : `RC-3A` fix fondation → grille `RC-4A→C`.
- **Produits spéciaux** : `RC-5A` policy → `RC-5B→E`.
- 3 streams **parallélisables** une fois les fondations posées ; coordonner les merges (touchent `OrderService`/`StockService`).

## 🐛 Bugs fondation débusqués par l'audit
- **`StockService::findOrCreate` ignore `warehouse_id`** alors que l'index unique DB l'inclut → `RC-3A` (prérequis grille + digital).
- **`PublicPricingController` force `interval=monthly`** (param écrasé) → `RC-1A` (whitelist + inverser le test `unsupported_interval_falls_back_to_monthly`).

## 📋 Suivi
| RC | Stream | Contenu | État |
|---|---|---|---|
| **RC-0** | billing | seed prix **annuels** (×10) + colonnes `subscriptions` (interval/currency/market_code/amount_paid) | ✅ **rc.101** |
| RC-3A | stock | fix `findOrCreate` (warehouse_id) + coût unitaire `moveIn` | ✅ **rc.102** |
| RC-1A | pricing | API publique `interval` whitelist + `monthly_equivalent`/`savings` | ✅ **rc.103** |
| RC-1B | pricing | `changePlan(interval)` → `addMonth/addYear`, abonnement porte l'interval | ✅ **rc.104** |
| RC-1C | pricing | détection périodicité à l'approbation (`PaymentPeriodResolver`, `manual_payments.interval`+`market_code`) | ⬜ |
| RC-1D | pricing | toggle Mensuel/Annuel front (landing + UpgradeView) + i18n | ⬜ |
| RC-5A | spécial | colonnes `stock_tracking`/`fulfillment_type` + data-migration | ⬜ |
| RC-4A–C | stock | grille multi-variantes par entrepôt (back + front) + i18n | ⬜ |
| RC-2A–D | pricing | `ProrationCalculator` + crédit/reliquat + preview-change + UI/i18n | ⬜ |
| RC-5B–E | spécial | digital · assets/entitlements · licences/garanties · reporting | ⬜ |

## ⚠️ Risques (rappels)
- Proration **dépend** de la périodicité (interval persisté + tarif annuel en base) → ordre strict.
- Socle données `subscriptions` livré **une seule fois** (RC-0) ; proration n'ajoute que `credit_balance`.
- Tolérance FX **±1 %** indispensable (mobile money) ; arrondi **par exposant devise** (XOF/XAF = 0).
- Pas de **job de renouvellement/relance** (cron billing) — hors périmètre, à planifier après.
- **DoD i18n** : tout RC touchant une vue livre FR+EN + met à jour le tracker (garde CI).
