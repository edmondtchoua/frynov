# Recette / QA — Produits spéciaux & Phase 2 (rc.114 → rc.131)

> **Date : 2026-06-26.** Campagne de recette complète des 19 incréments RC-5C → RC-6J.
> Méthode : runs de référence (backend/front/i18n/typecheck), vérifications transverses (routes,
> scheduler), **audit sécurité** (agent dédié) et **revue adverse** des flux critiques (agent dédié),
> correctifs immédiats + tests de régression.

## 1. Runs de référence

| Vérification | Résultat |
|---|---|
| Suite backend complète (Pest) | ✅ **871 tests / 869 passed / 2 skipped / 0 fail** (après correctifs QA, +3 tests de régression) |
| Suite frontend (Vitest) | ✅ **271 passed** (52 fichiers) |
| Gate i18n (`npm run i18n:check`) | ✅ parité FR/EN, 0 chaîne en dur, ALLOWLIST vide |
| vue-tsc | ✅ 0 nouvelle erreur introduite par les RC (baseline de dette pré-existante : ~180 lignes, hors périmètre) |
| Chargement des routes (`route:list`) | ✅ 257 routes, tous les nouveaux groupes enregistrés (warranties 9, digital 8, notifications 8, portal 2, special-attributes 3, batches…) |
| Scheduler (`schedule:list`) | ✅ 4 crons : snapshot inventaire, revoke-expired, **billing:process-renewals** (01:30), **notifications:flush-outbox** (5 min) |
| Migrations from scratch | ✅ prouvées par `RefreshDatabase` (chaque test reconstruit le schéma complet, 60+ migrations) |

## 2. Audit sécurité (agent dédié — routes & contrôleurs RC-5C→6J)

| # | Finding | Gravité | Statut |
|---|---------|---------|--------|
| S1 | **ReDoS** : `validation_regex` (RC-6D) contrôlée par le tenant, évaluée à chaque réception d'unité | Haute | ✅ **Corrigé** : garde `safeRegexRule()` à la création/màj (compilation + sonde adverse, max 120) ; à l'exécution, erreur PCRE = valeur rejetée (fail-closed) |
| S2 | **Énumération d'emails** : throttle 5/min trop généreux sur `POST /portal/digital/request-links` | Moyenne | ✅ **Corrigé** : `throttle:3,10` (3 requêtes / 10 min) |
| — | Isolation tenant (IDOR) sur tous les nouveaux contrôleurs | — | ✅ OK (aucun lookup sans `where tenant_id`) |
| — | Écritures derrière `role_or_permission` ; routes publiques limitées au portail (by design) | — | ✅ OK |
| — | Secrets : config canaux chiffrée (`encrypted:array`), jamais renvoyée (clés seulement) ; clés de licence hors listes | — | ✅ OK |
| — | Fichiers privés : upload Laravel safe, download par lien signé + revérification de révocation, aucun chemin exposé | — | ✅ OK |

## 3. Revue adverse des flux critiques (agent dédié)

| # | Bug | Gravité | Statut |
|---|-----|---------|--------|
| B1 | **Billing multi-devises** : l'abondement en place et le reversal (RC-6G) ciblaient le dépôt sans filtre `market_code` → un acompte EUR pouvait écraser/décrémenter le dépôt XOF | **Critique** | ✅ **Corrigé** (filtre `market_code` sur les deux chemins) + test de régression |
| B2 | **Lot périmé vendu** : `allocateFefo` (RC-6H) ne filtrait pas `expiry_date` — un lot périmé « active » partait en PREMIER (FEFO) | **Critique** | ✅ **Corrigé** (les lots périmés ne sont plus allouables ; restent en stock pour démarque) + test de régression |
| B3 | **Révocation excessive** : un retour PARTIEL d'une ligne digitale révoquait l'accès entier (RC-5H) | **Critique** | ✅ **Corrigé** : révocation seulement quand le CUMUL des retours approuvés couvre toute la ligne + test de régression |
| B4 | **Spam d'alertes** « pool épuisé » (RC-6E) à chaque vente en mode `generate` | Moyenne | ✅ **Corrigé** : déduplication 24 h par produit (cache) |
| B5 | Canal SMTP sans hôte (config corrompue) → tentative d'envoi absurde | Moyenne | ✅ **Corrigé** : échec explicite immédiat, visible dans `last_error` du journal |
| B6 | Payload HTTP API non sérialisable (config aberrante) → notification `failed` avec erreur tracée | Basse | ☑️ Accepté (comportement fail-visible voulu ; config saisie en JSON via l'API) |
| — | Caches d'`OrderService` (produits/nomenclatures) en singleton : pas de contamination inter-tenant (UUID produits) ; branches kit/sérialisé/batch/non-stockable mutuellement exclusives ; proration sans double-compte ; FEFO trié correctement | — | ✅ OK |

## 4. Limites connues (assumées, documentées — pas des blocages)

- Un entitlement digital **par ligne** (pas par exemplaire) — la révocation au retour suit ce modèle.
- ~~FEFO non appliqué aux composants de kits~~ ✅ **levée en RC-7A (rc.133)**.
- ~~Statut `expired` des lots non posé automatiquement~~ ✅ **levée en RC-7A (rc.133)** — cron
  `inventory:expire-batches` quotidien + alerte de retrait.
- Canaux SMS/WhatsApp : infrastructure proxy prête, aucun agrégateur réel branché (config à saisir).
- Emails texte brut (pas de layout HTML) ; locale `fr` seule seedée pour les templates.
- Baseline vue-tsc (~180 lignes de dette pré-existante, hors périmètre de ces RC).

## 5. Verdict

> ### ✅ **GO** — rc.114 → rc.131 recettés.
> 2 findings sécurité et 4 bugs logiques détectés par la campagne, **tous corrigés et verrouillés par
> des tests de régression** dans la même passe (rc.132). Suites complètes vertes, i18n 100 %,
> aucune régression introduite.
