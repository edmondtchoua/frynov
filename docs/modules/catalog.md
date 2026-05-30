# Module Catalog

## Responsabilité

Gère le référentiel produits : catégories, produits, variantes, génération de SKU, codes QR/barres, et impression d'étiquettes physiques.

---

## Modèles

### `Product`

Produit principal. Peut avoir des variantes (tailles, couleurs…).

```php
// Accesseurs
public function price(): Money             // Value Object Money
public function isOnSale(): bool           // compare_at_price > price
public function isActive(): bool
public function isDraft(): bool

// Relations
public function category(): BelongsTo
public function variants(): HasMany
public function tenant(): BelongsTo
```

**Statuts :**
- `draft` — brouillon, non visible en front
- `active` — publié, vendable
- `archived` — retiré du catalogue (non supprimé)

### `ProductVariant`

Déclinaison d'un produit (ex : `Boubou Rouge / L`).

```php
// Résolution du prix : variante > produit parent
public function effectivePrice(): Money

// Ex: "Couleur: Rouge · Taille: L"
public function attributesLabel(): string
```

### `Category`

Catégorie hiérarchique (arbre auto-référentiel).

```php
public function parent(): BelongsTo
public function children(): HasMany
public function products(): HasMany
```

---

## Services

### `SkuGeneratorService`

Génère des SKU uniques par tenant.

```php
// Génère: "PRD-0001", "VET-0023", "CHN-0100"
public function generate(string $tenantId, string $prefix = 'PRD'): string

// Génère: "PRD-0001-V1", "PRD-0001-V2"
public function generateVariant(string $parentSku, int $index): string
```

**Algorithme :** trouve le dernier numéro utilisé pour le préfixe dans les tables `products` et `product_variants`, incrémente de 1, padding 4 chiffres.

### `CatalogService`

Logique métier du catalogue.

```php
public function createProduct(array $data, Tenant $tenant): Product
public function updateProduct(Product $product, array $data): Product
public function archiveProduct(Product $product): Product   // ne supprime pas, status='archived'
public function createVariant(Product $product, array $data): ProductVariant
public function createCategory(array $data, Tenant $tenant): Category
```

**Comportements importants :**
- `createProduct()` : auto-génère le SKU si non fourni dans `$data`
- `archiveProduct()` : envoie l'événement `ProductArchived` (hook pour audit, sync, etc.)
- `createVariant()` : positionne `has_variants = true` sur le produit parent
- `createCategory()` : déduplique le slug (ajoute suffixe `-2`, `-3`… si déjà pris)

### `ProductCodeService`

Génère les codes QR et codes-barres au format SVG.

```php
// QR Code SVG (sans déclaration XML)
// Payload JSON: {"sku": "VET-0001", "id": "uuid", "name": "Boubou Sénégalais"}
public function qrCode(Product|ProductVariant $item, int $size = 200): string

// Code-barres SVG (Code128 par défaut, ou EAN-13)
public function barcode(Product|ProductVariant $item, string $type = 'code128'): string

// Fiche complète : SKU + QR + barcode
public function sheet(Product|ProductVariant $item): array
```

**Note technique :** `simplesoftwareio/simple-qrcode` ajoute une déclaration `<?xml …?>` en tête de SVG. Elle est supprimée avec `preg_replace('/^<\?xml[^>]+\?>\s*/u', '', $svg)` avant retour, car elle invalide l'injection directe dans du HTML.

### `LabelService`

Génère du HTML d'étiquettes prêt à imprimer (retourné en `text/html`).

```php
// Génération pour un seul produit
public function generateForProduct(
    Product $product,
    int $copies,
    string $format = 'thermal',
    array $options = []
): string

// Génération pour des variantes spécifiques
public function generateForVariants(
    Product $product,
    array $variantCopies,   // [['variant' => ProductVariant, 'copies' => N]]
    string $format = 'thermal',
    array $options = []
): string

// Batch multi-produits (livraison complète)
public function generate(array $items, string $format, array $options = []): string
// $items: [['product' => Product, 'variant' => ?ProductVariant, 'copies' => N], …]
```

**Formats disponibles :**

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `FORMAT_THERMAL` | `'thermal'` | Étiquette 58mm pour imprimante Bluetooth |
| `FORMAT_A4` | `'a4sheet'` | Feuille A4, grille 3×8 = 24 étiquettes (Avery L7159) |

**Options disponibles :**

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `show_price` | bool | `true` | Affiche le prix sur l'étiquette |
| `show_qr` | bool | `true` | Affiche le QR code |

---

## Étiquettes — Détail des gabarits

### Étiquette thermique 58mm (`thermal.blade.php`)

```
┌─────────────────────────────────────────┐
│ BOUTIQUE DAKAR              30/05/26    │  ← en-tête
├─────────────────────────────────────────┤
│ Boubou Sénégalais                       │  ← nom produit
│ SKU: VET-0001                           │  ← SKU monospace
│ [Rouge / L]                             │  ← badge variante
├───────────┬─────────────────────────────┤
│  ▓▓▓▓▓▓  │  ║║ ║ ║║║ ║│║║ ║║║         │  ← QR + code-barres
│  ▓▓▓▓▓▓  │                             │
├─────────────────────────────────────────┤
│ Prix                    25 000 XOF      │  ← prix (optionnel)
└─────────────────────────────────────────┘
```

CSS d'impression : `@page { size: 58mm auto; margin: 0; }` — page-break entre chaque étiquette.

### Feuille A4 (`a4sheet.blade.php`)

Compatible **Avery L7159** (3 colonnes × 8 rangées = 24 étiquettes/page).

Dimensions CSS :
- Colonne : `63.5mm`
- Hauteur : `33.9mm`
- Gouttière horizontale : `2.5mm`
- Padding page : `4.7mm 7.4mm`

Les étiquettes vides de fin de page sont remplies avec des `<div class="label"></div>` vides pour maintenir la grille.

---

## Routes API

Voir [api/catalog.md](../api/catalog.md) et [api/labels.md](../api/labels.md).

---

## Tests

| Fichier | Type | Tests |
|---------|------|-------|
| `Tests/Unit/CatalogServiceTest.php` | Unit | 9 |
| `Tests/Unit/LabelServiceTest.php` | Unit | 6 |
| `Tests/Integration/ProductApiTest.php` | Integration | 14 |
| `Tests/Integration/LabelApiTest.php` | Integration | 7 |
| **Total** | | **36** |

```bash
php vendor/bin/phpunit "app/Modules/Catalog/Tests" --no-coverage
```
