# API Billing publique

## `GET /api/public/pricing`

Retourne la grille publique des plans actifs et publics, localisée par marché ou pays. Cet endpoint est volontairement accessible sans authentification afin d'alimenter la landing page et les écrans d'upgrade avant connexion.

### Paramètres

| Paramètre | Exemple | Règle |
|---|---|---|
| `country` | `CA`, `FR`, `SN`, `CM` | Code pays ISO-2 utilisé pour pré-sélectionner le marché. |
| `market` | `canada`, `europe`, `waemu`, `cemac` | Sélection manuelle prioritaire sur la détection pays. |
| `interval` | `monthly` | `monthly` uniquement à ce stade ; toute autre valeur revient à `monthly`. |

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
  "selectable_markets": [],
  "data": [
    {
      "code": "essential",
      "name": "Essentiel",
      "price": {
        "market_code": "canada",
        "currency": "CAD",
        "interval": "monthly",
        "base_amount_minor": 2500,
        "included_users": 2,
        "extra_user_amount_minor": 1000
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

### Règles de sécurité et produit

- L'endpoint n'expose que les plans `is_active=true` et `is_public=true`.
- `market` est prioritaire sur `country` afin de respecter le sélecteur manuel utilisateur.
- Un pays inconnu revient au marché `global` en USD.
- Les données contractuelles de prix doivent venir de cette API, pas d'une table hardcodée dans la landing.
