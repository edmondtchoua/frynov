# API — Étiquettes produits

Ces endpoints retournent du **HTML prêt à imprimer** (`Content-Type: text/html`). Ils sont conçus pour être ouverts dans un onglet navigateur ou une WebView Flutter, puis imprimés via `window.print()`.

---

## GET `/api/catalog/products/{id}/label`

Génère des étiquettes pour un seul produit.

### Requête

```http
GET /api/catalog/products/f47ac10b/label?format=thermal&copies=10&price=1&qr=1
Authorization: Bearer {token}
```

### Paramètres

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `format` | string | `thermal` | Format : `thermal` (58mm) ou `a4sheet` (A4 grille 3×8) |
| `copies` | int | `1` | Nombre d'exemplaires (1–500) |
| `price` | bool | `1` | Afficher le prix (`1` = oui, `0` = non) |
| `qr` | bool | `1` | Afficher le QR code (`1` = oui, `0` = non) |

### Réponse 200

```
Content-Type: text/html; charset=UTF-8
```

Page HTML complète avec :
- Barre d'outils avec bouton "Imprimer"
- Étiquette(s) optimisées pour impression
- CSS `@media print` masquant la toolbar et gérant les sauts de page

### Cas d'usage : réception de marchandise

```
# On vient de recevoir 30 Boubous → imprimer 30 étiquettes
GET /api/catalog/products/f47ac10b/label?copies=30
```

---

## GET `/api/catalog/products/{productId}/variants/{variantId}/label`

Génère des étiquettes pour une variante spécifique.

### Requête

```http
GET /api/catalog/products/f47ac10b/variants/a1b2c3d4/label?format=thermal&copies=5
Authorization: Bearer {token}
```

L'étiquette affiche le SKU de la variante (`VET-0001-V1`) et ses attributs (`Rouge / L`).

---

## POST `/api/catalog/products/labels/batch`

Génère des étiquettes pour plusieurs produits en une seule requête. Idéal pour imprimer l'étiquetage complet d'une livraison.

### Requête

```http
POST /api/catalog/products/labels/batch
Authorization: Bearer {token}
Content-Type: application/json
```

```json
{
  "format": "thermal",
  "show_price": true,
  "show_qr": true,
  "items": [
    { "product_id": "f47ac10b-...", "copies": 30 },
    { "product_id": "a1b2c3d4-...", "copies": 15 },
    { "product_id": "c3d4e5f6-...", "variant_id": "d4e5f6a7-...", "copies": 5 }
  ]
}
```

### Règles de validation

| Règle | Valeur |
|-------|--------|
| `copies` max par item | 500 |
| Total copies max | 5 000 |
| Nombre d'items max | 50 |

### Réponse 200

```
Content-Type: text/html; charset=UTF-8
```

Page HTML unique avec toutes les étiquettes regroupées, prêtes à imprimer.

### Réponse 422 — Dépassement de limite

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items.0.copies": ["The copies field must not be greater than 500."]
  }
}
```

---

## Formats d'impression

### Format `thermal` — 58mm

Pour imprimantes thermiques Bluetooth (Péripage, Munbyn, Xprinter, etc.).

| Propriété | Valeur |
|-----------|--------|
| Largeur | 58mm |
| Hauteur | Variable (auto) |
| `@page` CSS | `size: 58mm auto; margin: 0` |
| Page break | Après chaque étiquette |

**Contenu de l'étiquette :**
- En-tête : nom du tenant + date du jour
- Nom du produit (ellipsis si trop long)
- SKU en monospace
- Badge variante (si applicable)
- QR code (14mm) + code-barres côte à côte
- Prix (si activé), badge PROMO si en solde

### Format `a4sheet` — Feuille A4

Compatible **Avery L7159** et planches similaires.

| Propriété | Valeur |
|-----------|--------|
| Page | A4 (210mm × 297mm) |
| Disposition | 3 colonnes × 8 rangées = **24 étiquettes/page** |
| Taille étiquette | 63.5mm × 33.9mm |
| Gouttière | 2.5mm (entre colonnes) |
| Padding page | 4.7mm (haut/bas) × 7.4mm (gauche/droite) |

**Contenu de l'étiquette :**
- Nom du tenant (grisé, très petit)
- Nom du produit (max 2 lignes)
- SKU en monospace
- Badge variante (si applicable)
- QR code (12mm) en haut à droite
- Code-barres en bas (pleine largeur)
- Prix en bas à droite

---

## Intégration Flutter WebView

```dart
// Ouvrir les étiquettes dans une WebView pour impression
final url = Uri.parse(
  '${apiBase}/catalog/products/${productId}/label'
  '?format=thermal&copies=${quantity}&price=1'
);

// Après chargement, évaluer JavaScript pour déclencher l'impression
webViewController.runJavaScript('window.print()');
```
