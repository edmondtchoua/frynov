# Conventions de code

## Règles générales

1. **Pas de commentaires évidents** — le code se lit via les noms. Commenter uniquement les contraintes non-évidentes ou les workarounds.
2. **Pas de gestion d'erreur fantaisiste** — valider aux frontières (requête HTTP), faire confiance au code interne.
3. **Pas de features flags** ou de compatibilité backwards inutile — changer le code directement.
4. **Pas de float pour l'argent** — toujours des centimes entiers.

---

## Structure d'un Service

```php
class CatalogService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SkuGeneratorService $skuGenerator,
    ) {}

    public function createProduct(array $data, Tenant $tenant): Product
    {
        if (empty($data['sku'])) {
            $data['sku'] = $this->skuGenerator->generate($tenant->id, $data['sku_prefix'] ?? 'PRD');
        }

        return Product::create([
            'tenant_id'      => $tenant->id,
            'sku'            => $data['sku'],
            'name'           => $data['name'],
            'price_amount'   => $data['price_amount'],
            'price_currency' => $data['price_currency'],
            'status'         => $data['status'] ?? 'draft',
        ]);
    }
}
```

**Règles :**
- Injection de dépendances via constructeur (readonly)
- Méthodes courtes, un seul niveau de responsabilité
- Lancer des exceptions métier nommées (`InvalidCredentialsException`, `ProductNotFoundException`)

---

## Value Object `Money`

```php
// Créer
$price = Money::of(25000, 'XOF');

// Formatter
(string) $price      // → "25 000 XOF"
$price->amount()     // → 25000
$price->currency()   // → "XOF"

// Comparer
$price->isGreaterThan($other)
$price->equals($other)
```

Toujours utiliser `Money` pour les calculs et l'affichage. Ne jamais diviser par 100 pour "afficher en euros/francs" — laisser `Money::format()` gérer ça.

---

## Argent côté frontend — `@/shared/utils/money`

> ⚠️ **Convention DB** : tous les montants sont stockés en **centimes entiers (× 100)**.
> Un prix de 4 200 XOF est stocké `420000`.

Un audit a tracé **5 bugs ×100/÷100** à des formatters réimplémentés dans chaque vue
(certains divisaient, d'autres non). **Toujours** utiliser l'utilitaire partagé :

```ts
import { formatMoney, toCents, fromCents } from '@/shared/utils/money'

formatMoney(420000)            // → "4 200 XOF"  (÷100, XOF/XAF sans décimales, EUR/USD 2)
formatMoney(p.amount_cents, p.currency)
toCents(4200)                  // → 420000       (à la SOUMISSION d'un montant saisi)
fromCents(420000)              // → 4200         (pour pré-remplir un input éditable)
```

**Interdit** : `new Intl.NumberFormat(...).format(cents / 100)` inline dans une vue.
**Interdit** : envoyer un montant saisi sans `toCents()` (→ enregistré 100× trop petit).

`reportService` ré-exporte `formatMoney`/`formatMoneyCompact` depuis cet util pour compat.

---

## Dates côté frontend — `@/shared/utils/date`

Même histoire que l'argent : **23 formatters de date dupliqués** (`fmtDate`, `formatDate`,
`shortDate`…) avec locales et options divergentes. Tous remplacés par 3 helpers null-safe
(retournent `—` si entrée invalide) :

```ts
import { formatDate, formatDateTime, formatDateShort } from '@/shared/utils/date'

formatDate(iso)        // "1 juin 2026"        (date seule — le plus courant)
formatDateTime(iso)    // "1 juin 2026 10:05"  (logs d'audit, horodatages)
formatDateShort(iso)   // "1 juin"             (listes denses, graphiques)
```

**Interdit** : `new Date(iso).toLocaleDateString(...)` ou `new Intl.DateTimeFormat(...)`
inline dans une vue. `reportService` ré-exporte `shortDate` depuis cet util.

---

## Modèles Eloquent

```php
class Product extends Model
{
    use HasUuids;       // UUID v4 auto-assigné
    use SoftDeletes;    // toujours sur les entités métier

    protected $fillable = [...];  // liste explicite — jamais $guarded = []

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'has_variants' => 'boolean',
            'attributes'   => 'array',    // json → PHP array automatique
        ];
    }
}
```

---

## Routes API

```php
// routes/api.php d'un module

// Routes publiques (scanner POS, webhooks)
Route::get('products/sku/{sku}', [ProductController::class, 'findBySku']);

// Routes protégées
Route::middleware(['auth:sanctum', EnsureUserBelongsToTenant::class])->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::post('products/labels/batch', [LabelController::class, 'batch']);
});
```

**Conventions URL :**
- Pluriel pour les ressources : `/products`, `/categories`
- Actions non-CRUD : verbe ou sous-ressource — `/products/{id}/archive`, `/products/labels/batch`
- Scanner (public) : `/products/sku/{sku}` avant `apiResource` pour éviter le conflit de route

---

## Exceptions métier

Créer des exceptions dédiées dans `app/Modules/{Module}/Exceptions/` :

```php
// app/Modules/Auth/Exceptions/InvalidCredentialsException.php
class InvalidCredentialsException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid email or password', 401);
    }
}
```

Les exceptions sont catchées dans le Handler global et converties en réponses JSON appropriées.

---

## Tests — règles

1. Un test = un comportement, pas une méthode
2. Nommage `it_does_something_when_condition()` en snake_case
3. `#[Test]` attribut PHP 8 (jamais `@test` docblock)
4. Toujours étendre `Tests\TestCase`
5. Toujours appeler `Mockery::close()` dans `tearDown()`
6. Utiliser `RefreshDatabase` sur les tests d'intégration
7. Pas de mocks sur la base de données dans les tests d'intégration — SQLite in-memory suffit

---

## Git

```
Branches :
  main                    → production
  develop                 → intégration
  phase{N}/feature/{slug} → feature branch (ex: phase1/feature/inventory)
  hotfix/{slug}           → correction urgente

Commits (Conventional Commits) :
  feat(module): description
  fix(module): description
  test(module): description
  docs: description
  refactor(module): description
```
