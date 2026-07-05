# Guide des tests

## Stack de test

| Composant | Version | Notes |
|-----------|---------|-------|
| PHPUnit | ^12 | Framework de test PHP |
| Laravel TestCase | 13 | Fournit l'application, les helpers HTTP |
| Mockery | ^1.6 | Mocks pour tests unitaires |
| SQLite | :memory: | Base de données en mémoire pour les tests |

---

## Organisation des tests

```
app/Modules/{Module}/Tests/
├── Unit/           Tests unitaires (services, value objects, logique pure)
│   └── *Test.php
└── Integration/    Tests d'intégration (routes HTTP + base de données)
    └── *Test.php
```

Les tests sont organisés **par module** pour garder la cohésion. Il n'y a pas de dossier `tests/Feature` ou `tests/Unit` global.

---

## Écrire un test

### Convention de base

```php
namespace App\Modules\Catalog\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;                          // toujours étendre Tests\TestCase

class MonServiceTest extends TestCase
{
    #[Test]                                  // attribut PHP 8 — pas @test docblock
    public function it_does_something(): void
    {
        // Arrange
        $service = new MonService();

        // Act
        $result = $service->doSomething();

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

> **Important :** toujours étendre `Tests\TestCase` (pas `PHPUnit\Framework\TestCase` directement) pour avoir accès aux facades Laravel, au container IoC et aux helpers HTTP.

### Test unitaire avec mock

```php
use Mockery;
use Mockery\MockInterface;

class LabelServiceTest extends TestCase
{
    private LabelService $labelService;
    private MockInterface $codeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codeService  = Mockery::mock(ProductCodeService::class);
        $this->labelService = new LabelService($this->codeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();         // ← toujours appeler close() dans tearDown
        parent::tearDown();
    }

    #[Test]
    public function it_generates_thermal_html(): void
    {
        $this->codeService->allows('qrCode')->andReturn('<svg></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg></svg>');

        $html = $this->labelService->generateForProduct($product, copies: 1, format: 'thermal');
        $this->assertStringContainsString('58mm', $html);
    }
}
```

### Test d'intégration HTTP

```php
class LabelApiTest extends TestCase
{
    use RefreshDatabase;    // recrée la BDD in-memory à chaque test

    private Tenant $tenant;
    private User   $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        // Créer tenant + user + token dans setUp()
        $this->tenant = Tenant::create([...]);
        $this->user   = User::create([..., 'tenant_id' => $this->tenant->id]);
        $this->token  = $this->user->createToken('api')->plainTextToken;
    }

    #[Test]
    public function it_returns_thermal_label_html(): void
    {
        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$this->product->id}/label?format=thermal&copies=1");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
```

---

## Compter des éléments HTML dans les tests

Quand on compte des répétitions d'un produit dans du HTML, utiliser les attributs `data-sku` plutôt que le texte brut. Le SKU apparaît aussi dans le SVG du code-barres, ce qui fausse le `substr_count` sur le texte brut.

```php
// ✅ Correct — compte les divs d'étiquettes
$this->assertEquals(24, substr_count($html, 'data-sku="VET-0001"'));

// ❌ Faux — compte aussi les occurrences dans le SVG barcode
$this->assertEquals(24, substr_count($html, 'VET-0001'));
```

---

## Lancer les tests

### Tous les tests

```bash
cd backend
php vendor/bin/phpunit --no-coverage
```

### Un module spécifique

```bash
php vendor/bin/phpunit "app/Modules/Catalog/Tests" --no-coverage
php vendor/bin/phpunit "app/Modules/Auth/Tests" --no-coverage
```

### Un fichier spécifique

```bash
php vendor/bin/phpunit "app/Modules/Catalog/Tests/Integration/LabelApiTest.php" --no-coverage
```

### Un seul test

```bash
php vendor/bin/phpunit --filter "it_returns_thermal_label_html" --no-coverage
```

---

## Configuration PHPUnit

> ⚠️ **Piège corrigé (2026-06)** : PHPUnit `<directory>` ne supporte pas les wildcards
> (`app/Modules/*/Tests`). L'ancienne config matchait par **suffixe** (`ApiTest.php`,
> `ServiceTest.php`…) ce qui laissait **23 fichiers / 121 tests jamais exécutés** —
> dont les tests de **sécurité et multi-tenant**. Règle d'or : **toute suite utilise un
> catch-all `suffix="Test.php"` par racine** (racines disjointes → aucun double-comptage,
> aucun fichier oublié). Ne jamais filtrer par suffixe spécifique.

```xml
<!-- backend/phpunit.xml -->
<testsuites>
    <testsuite name="Modules"><directory suffix="Test.php">app/Modules</directory></testsuite>
    <testsuite name="Shared"> <directory suffix="Test.php">app/Shared</directory></testsuite>
    <testsuite name="Tests">
        <directory suffix="Test.php">tests/Unit</directory>
        <directory suffix="Test.php">tests/Feature</directory>
        <directory suffix="Test.php">tests/Integration</directory>
    </testsuite>
    <testsuite name="E2E"><directory>tests/E2E</directory></testsuite>
</testsuites>

<php>
    <env name="APP_ENV" value="testing"/>
    <!-- APP_KEY DOIT décoder en 32 octets (AES-256), sinon "Unsupported cipher" -->
    <env name="APP_KEY" value="base64:...32-byte-key..."/>
    <env name="QUEUE_CONNECTION" value="sync"/>
</php>
```

Après toute modif de `phpunit.xml`, **vérifier le total** : `php artisan test` doit
afficher ~500 tests, pas ~370.

---

## Couverture de code

```bash
# Requiert Xdebug ou PCOV
php vendor/bin/phpunit --coverage-html coverage/

# Ouvrir coverage/index.html dans le navigateur
```

---

## État actuel des tests (Phase 1)

| Module | Tests | Statut |
|--------|-------|--------|
| Auth | 30 (23 unit + 7 integration) | ✅ |
| Catalog | 36 (15 unit + 21 integration) | ✅ |
| **Total** | **66** | **✅ Tous passent** |
