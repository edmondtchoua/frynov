## Vue d'ensemble

Cette PR livre **Sprint 17 (refonte fiche produit)** puis un **audit qualité approfondi** de tout l'ERP : **20 corrections de bugs**, **2 refactorings cause-racine**, la **réparation du harnais de test** (backend + frontend) et **16 tests composant** sur les vues critiques. Documentation, seeders et landing page alignés sur le code réel.

> **Backend : 501 tests ✅** (dont **121 réactivés** — voir ci-dessous) · **Frontend : 112 Vitest ✅** · couverture 3.8 % → 16.7 % · builds verts.

---

## 1. Sprint 17 — Refonte fiche produit

- **`ProductShowPage`** : page de consultation à onglets (Vue d'ensemble · Variantes · Stock · Prix) — séparée du formulaire d'édition
- **Drawers stock** : entrée & ajustement depuis la fiche, par produit **ou par variante**
- **`product_type`** (simple/variable/service/kit) + sync des axes N-dimensions vers `product_attributes`
- **`OrderCreateView` reconstruit** : recherche produit (autocomplete), sélection de variante imposée, sélection client, total live — remplace l'ancien stub qui exigeait de saisir des UUID à la main

## 2. Audit qualité — 20 corrections

### 🔴 Critiques (5)
| Bug | Impact |
|---|---|
| `fulfill()` échouait sur stock serré | `moveOut` avant `release` → livraison impossible quand stock = demande exacte (cas PME) |
| **121 tests jamais exécutés** | `phpunit.xml` filtrait par suffixe → tests sécurité/multi-tenant skippés en CI |
| Gate couverture frontend fictif | seuils 75 % vs 3.8 % réel → CI frontend rouge en permanence |
| Plan Enterprise bloqué | limites `0` traitées comme strictes → le tier le plus cher ne pouvait rien créer |
| Downgrade gardait modules premium | `activatePlanModules` additif → faille d'entitlement |

### 🟠 Money (5) · Stock (4) · UX/Auth (3)
Montants ×100 (OrderList, paiement manuel), mélange de devises, devise commande hardcodée, onboarding qui droppait la devise · `NotifyLowStock` cassé, transfert sur-réception (stock fantôme), CMUP non validé, commande de variante impossible · OrderCreate stub, `voidPayment` silencieux, `generateSlug` fragile.

### 🟡 Responsive (2)
Tables clippées sur mobile (fix global) · line-row commande qui débordait.

## 3. Refactorings cause-racine (2)

- **`@/shared/utils/money`** — élimine les 5 bugs ×100/÷100 (12+ formatters dupliqués → 1 source de vérité : `formatMoney`/`toCents`/`fromCents`)
- **`@/shared/utils/date`** — 23 formatters de date dupliqués → 3 helpers null-safe (`formatDate`/`formatDateTime`/`formatDateShort`)

## 4. Tests ajoutés

- **Backend** : 10 tests de régression (stock serré, devise tenant, chaîne variante bout-en-bout, sur-réception transfert, downgrade modules, devise onboarding) + réactivation des 121 tests cachés
- **Frontend** : 16 tests composant (OrderCreate 5 · OrderDetail 4 · ProductShow 4 · ProductForm 3) + 11 tests `money.ts` + 6 tests `date.ts`

## 5. Documentation & données

- `plan.md` : section Audit, roadmap renumérotée, compteurs à jour
- `conventions.md` : sections money + date frontend
- `testing.md` : config phpunit.xml réelle + avertissement sur le piège des suffixes
- `PlansSeeder` : Enterprise normalisé sur `null` · landing + `billing.md` alignés sur les vraies limites (seed = source de vérité)
- `catalog.md` : builder N-axes + fiche à onglets

---

## Notes de revue
- Aucune migration destructive ; données existantes préservées
- Conventions multi-tenant et permissions inchangées (vérifiées par les tests de sécurité désormais exécutés)
- `phpunit.xml` : vérifier le total `php artisan test` (~501, pas ~370)

🤖 Generated with [Claude Code](https://claude.com/claude-code)
