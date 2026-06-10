# API Billing publique

## `GET /api/public/pricing`

Retourne la grille publique des plans actifs et publics, localisée par marché ou pays. Cet endpoint est volontairement accessible sans authentification afin d'alimenter la landing page et les écrans d'upgrade avant connexion.

### Paramètres

| Paramètre | Exemple | Règle |
|---|---|---|
| `country` | `CA`, `FR`, `SN`, `CM` | Code pays ISO-2 utilisé pour pré-sélectionner le marché. |
| `market` | `canada`, `europe`, `waemu`, `cemac` | Sélection manuelle prioritaire sur la détection pays. |
| `interval` | `monthly`, `yearly` | Périodicité affichée. Whitelist `monthly`/`yearly` ; toute autre valeur retombe sur `monthly`. L'annuel = mensuel ×10 (≈ 2 mois offerts). |

### Réponse

```json
{
  "market": {
    "code": "canada",
    "label": "Canada",
    "currency": "CAD",
    "source": "country",
    "country": "CA"
  },
  "interval": "yearly",
  "selectable_markets": [],
  "data": [
    {
      "code": "essential",
      "name": "Essentiel",
      "price": {
        "market_code": "canada",
        "currency": "CAD",
        "interval": "yearly",
        "base_amount_minor": 25000,
        "included_users": 2,
        "extra_user_amount_minor": 10000,
        "monthly_equivalent_minor": 2083,
        "savings_amount_minor": 5000,
        "savings_pct": 17
      },
      "limits": {
        "max_products": 500,
        "max_monthly_orders": 300,
        "max_customers": 1000
      }
    }
  ]
}
```

- `interval` (racine) : la périodicité réellement appliquée (après whitelist).
- Champs **présents uniquement sur l'annuel** (`interval=yearly`) :
  - `monthly_equivalent_minor` : coût « par mois » de l'offre annuelle = `round(base/12)` ;
  - `savings_amount_minor` : économie en unités mineures vs 12 mensualités (`12×mensuel − annuel`, plancher 0) ;
  - `savings_pct` : la même économie en pourcentage entier (0 pour un plan gratuit).
  - En `monthly`, ces trois champs sont **absents** (rien à comparer).

### Règles de sécurité et produit

- L'endpoint n'expose que les plans `is_active=true` et `is_public=true`.
- `market` est prioritaire sur `country` afin de respecter le sélecteur manuel utilisateur.
- Un pays inconnu revient au marché `global` en USD.
- Les données contractuelles de prix doivent venir de cette API, pas d'une table hardcodée dans la landing.
- L'économie annuelle est calculée à partir du **mensuel réel du marché** (pas d'un ratio figé) : robuste si la grille évolue.

---

## `POST /api/me/manual-payments` — soumettre un paiement (RC-1C)

Le tenant déclare un versement (avec preuve). La **périodicité et le marché sont DÉTECTÉS** du montant.

### Paramètres

| Paramètre | Règle |
|---|---|
| `plan_code` | requis — plan visé |
| `amount_cents` | requis — montant encaissé en **unités mineures** |
| `currency` | défaut `XOF` — détermine le marché côté serveur |
| `payment_method` | requis |
| `market_code` | optionnel — **hint** marché ; ignoré s'il ne correspond pas à la devise |
| `interval` | optionnel `monthly`/`yearly` — périodicité **déclarée** (repli pour router un acompte) |
| `promo_code`, `notes`, `proof` | optionnels |

> La devise n'est jamais saisie librement comme « marché » : le marché est résolu serveur-side à partir
> de la devise (avec le `market_code` comme hint validé). Sans `interval`, la détection est purement basée
> sur le montant (repli mensuel).

## Détection de périodicité & acompte échelonné

À la **soumission** (cumul = 0) puis surtout à l'**approbation** (cumul des acomptes non soldés), le
`PaymentPeriodResolver` compare le montant encaissé aux prix du plan pour le marché résolu :

- **Tolérance ±1 %** (bruit mobile money / FX) : un paiement plein dans la bande est `matched`
  (jamais un faux trop-perçu). Bornes entières arrondies vers l'extérieur (≥ 1 unité mineure).
- **Annuel testé avant mensuel** : les bandes ne se recoupent jamais (annuel = 10× mensuel).
- **Trop-perçu** uniquement **au-delà** de la borne haute de la **plus grande** cible → abonnement
  soldé + **avoir** tracé dans `subscriptions.metadata['overpaid_minor']`.
- **Zone morte** (entre mensuel et annuel) = **acompte vers l'annuel**, pas un trop-perçu mensuel.
- **Acompte** (`partial`) : abonnement `past_due`, **période non démarrée**, modules non activés ;
  le **reste dû** est tracé. Les acomptes s'**accumulent** sur la clé stable `(tenant, plan, market)` ;
  au solde, l'abonnement passe `active` et `current_period_start` reprend la **date du 1er acompte**.
- **Renouvellement** : après un solde, les acomptes du cycle sont marqués `settled` (exclus du cumul)
  → un nouveau paiement repart d'un cumul à zéro (jamais compté comme avoir).
- **`needs_review`** (promo appliquée) / **`unmatched`** (devise hors référentiel) : paiement approuvé
  **sans activation** — l'admin tranche.

`resolution_status` ∈ `matched | partial | overpaid | free | needs_review | unmatched | settled`. Le
paiement expose `market_code`, `detected_interval`, `target_amount_minor`, `remaining_due_minor`,
`overpaid_minor`, `resolution_status` (barre de progression d'acompte côté admin).

> **Hors périmètre RC-1C** (→ RC-2) : abondement d'acompte en place (au lieu d'annuler/recréer), cible
> nette après **promo**, sièges supplémentaires, table d'avoirs dédiée, proration d'upgrade avant fin.
