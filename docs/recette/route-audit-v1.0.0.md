# Audit de réconciliation des routes — v1.0.0

> Vérification croisée **frontend ↔ backend** sur `release/v1.0.0`.
> Méthode : routes backend via `php artisan route:list` ; appels frontend via extraction
> des littéraux `/api/...` (`client.{get,post,put,patch,delete}` + `fetch`) puis recoupement.
> Date : 2026-06-06.

---

## A. Frontend → Backend : tous les liens marchent ✅

**Verdict : aucun lien cassé.** Chaque appel API du frontend résout vers une route backend
existante (méthode + URI), y compris les chemins dynamiques (`/api/orders/${id}/confirm`,
`/api/workspace/users/${id}/warehouses`, `/api/inventory/stock/${id}/move-in`, etc.).

Vérifiés présents des deux côtés (échantillon des familles) : `auth/*`, `catalog/*`
(produits, catégories, variantes, attributs, label, initial-stock, stock-summary),
`inventory/*` (stock, alerts, scan, warehouses, **transfers**, **fiscal-periods**),
`orders/*` (+ **returns**, confirm/fulfill/cancel, payments, deliveries), `payments/*`,
`pos/sessions/*`, `customers/*`, `suppliers/*`, `deliveries/*`, `import/*`, `export/{type}`,
`reports/*`, `marketplace/*`, `me/*`, `workspace/*`, `admin/*` (tenants, modules, plans,
promotions, manual-payments, country-rules, audit-logs), `public/{geo,pricing}`.

> Seuls « faux positifs » écartés : imports TS `@/api/client` et `@/api/types`, et des
> fixtures de tests (`/api/import/imp-1/report`, `/api/payments/pay1`, `…/users/u1/…`).

---

## B. Backend → Frontend : routes non consommées par le SPA, classées

### 🟢 Présent — utile, **gap d'UI à combler** (backend prêt/testé, pas branché)

| Route(s) backend | Constat | Recommandation |
|---|---|---|
| ~~`GET/POST api/inventory/adjustments` (+ `/history`, `/{id}/approve`, `/{id}/reject`)~~ | **Module Ajustement de stock** — ✅ **CÂBLÉ (v1.0.0-rc)** : onglet **Stock → Ajustements** (file d'attente + approuver/rejeter + nouvelle demande + historique). | Résolu. |
| ~~`PATCH api/admin/plans/{plan}` (+ `GET …/{plan}`)~~ | Édition des **limites de plan** (super-admin) — ✅ **CÂBLÉ (v1.0.0-rc)** : bouton « Éditer les limites » + modale (`plan_limits`) dans `PlanListView`. | Résolu. |
| `POST api/admin/audit-logs/verify-chain` | **Vérification d'intégrité** de la chaîne d'audit (HMAC) : pas de bouton UI | Ajouter un bouton « Vérifier la chaîne » dans `AuditLogView`. |
| `POST api/inventory/count` | **Comptage d'inventaire** physique (batch) | UI d'inventaire tournant à câbler (présent mais non exposé). |
| `PATCH api/inventory/stock/{stockId}/threshold` | Édition dédiée du **seuil d'alerte** | Câbler (aujourd'hui le seuil se définit à la création/réception). |
| `GET api/admin/manual-payments/{id}` | **Détail** d'un paiement manuel (liste + approuver/rejeter câblés) | Vue détail optionnelle. |

### 🔵 Futur — prévu / formats alternatifs / extensions

| Route(s) | Note |
|---|---|
| `GET api/catalog/products/{id}/qrcode` · `…/variants/{id}/qrcode` | **QR codes** (le SPA imprime via `label` + `labels/batch`). Format alternatif futur. |
| `GET api/catalog/products/{id}/barcode` · `…/variants/{id}/barcode` | Endpoints **image code-barres** individuels (le SPA passe par `labels/batch` + rendu client). |
| `GET api/catalog/products/{id}/codes` | Endpoint **codes combinés** (barcode+QR) — non consommé. |
| `GET api/catalog/variants/stats` | **Statistiques variantes** (widget dashboard futur). |
| `POST api/auth/refresh` | **Rafraîchissement de token** — utile quand la durée de vie des tokens sera raccourcie (aujourd'hui tokens Sanctum longue durée). |

### 🔴 Hors application — ops / framework (ne pas exposer au SPA)

| Route(s) | Note |
|---|---|
| `*/horizon/api/*` (~20) | Dashboard **Laravel Horizon** (monitoring des queues Redis). Interface d'**ops**, protégée par gate, **non** consommée par le SPA. À garder hors du périmètre applicatif. |

---

## C. Synthèse & actions

- **Liens frontend** : 100 % valides (aucun 404 potentiel par référence cassée). ✅
- **Dette d'exposition** (présent mais non câblé) : ✅ **résorbée** pour les deux gaps
  prioritaires — **Ajustements de stock** (onglet Stock → Ajustements) et **édition des limites
  de plan** (modale `PlanListView`) sont désormais câblés (v1.0.0-rc, frontend 179).
- **Futur** : QR codes, stats variantes, refresh token — endpoints prêts, à activer quand le besoin se confirme.
- **Ops** : routes Horizon à laisser au dashboard d'exploitation, pas au SPA.

> Limite de méthode : extraction par littéraux `/api/...`. Un endpoint construit par
> concaténation de variables non littérales pourrait être marqué « non utilisé » à tort —
> les familles à vue dédiée (transfers, returns, fiscal-periods) ont été reconfirmées par mot-clé.
