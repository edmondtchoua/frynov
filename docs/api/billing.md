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

---

## `POST /api/me/subscription/preview-upgrade` — aperçu de proration (RC-2)

Calcule (**lecture seule**, aucune mutation) le **reliquat** si le tenant changeait de plan maintenant :
crédit du temps non consommé du plan en cours, net à payer, avoir reporté. Le marché/devise est résolu
sur l'abonnement courant. Le calcul est **re-exécuté au commit** (approbation du paiement) avec l'horloge
réelle — ce preview est purement indicatif.

### Paramètres

| Paramètre | Règle |
|---|---|
| `plan_code` | requis — plan cible |
| `interval` | requis — `monthly`/`yearly` |

### Réponse

```json
{
  "eligible": true,
  "reason": "ok",
  "currency": "XOF",
  "exponent": 0,
  "fraction_remaining": 0.967,
  "credit_minor": 957000,
  "new_gross_minor": 9900000,
  "applied_credit_minor": 957000,
  "net_payable_minor": 8943000,
  "carry_credit_minor": 0
}
```

- **Modèle hybride** (décision produit) : le crédit + l'avoir reporté sont **appliqués** au nouveau tarif
  (`applied_credit_minor`) → le client paie le **`net_payable_minor`** ; l'excédent (downgrade /
  crédit > tarif) part en **avoir reporté** (`carry_credit_minor`), jamais en cash.
- **Assiette** du crédit = montant réellement encaissé **moins le trop-perçu déjà tracé** (pas de double
  comptage). **Fraction** = temps non consommé, calculée en **secondes** (arithmétique entière). Crédit
  **borné** par l'assiette.
- `reason` ∈ `ok | downgrade | free_target | not_paid | past_due_no_period | not_eligible | expired |
  degenerate_period | cross_currency_blocked`. Un avoir **ne franchit jamais une devise**.

### Application au commit (RC-2B — paiement manuel, « acompte virtuel »)

À l'approbation d'un paiement sur un **vrai upgrade** (abonnement courant actif payé, plan ou
périodicité différents), le reliquat agit comme un **acompte virtuel** : le client ne vire que le
**net** (`net_payable_minor`), et le crédit comble le reste → `cash + crédit = tarif` → abonnement
**activé**. Garde-fous (revue adverse) :

- le crédit n'est consommé que si l'upgrade **solde réellement** le tarif (sinon il reste intact) ;
- `amount_paid_minor` du nouvel abonnement = **cash réellement encaissé** (le crédit n'est pas du cash,
  n'enfle pas le CA) ;
- l'avoir appliqué (`credit_applied_minor`) et l'avoir reporté (`credit_minor`) sont tracés dans
  `subscriptions.metadata` (jamais écrasés ; clés distinctes de `overpaid_minor`), **émis une seule fois**,
  au moment où l'abonnement courant est réellement annulé (idempotence) ;
- un **renouvellement** (même plan + même périodicité) ou un **premier achat** (sans courant) ne
  déclenchent **aucune** proration.

> **Suite RC-2C** : afficher crédit/net/avoir dans l'UI d'upgrade (i18n FR+EN).
