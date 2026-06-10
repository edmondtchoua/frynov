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
