<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeModule extends Command
{
    protected $signature = 'make:module
        {name         : Nom du module en PascalCase (ex: Orders, Inventory)}
        {--phase=1    : Phase de la roadmap (0, 1, 2, 3)}
        {--no-tests   : Ne pas générer les fichiers de tests}';

    protected $description = 'Génère la structure complète d\'un nouveau module métier';

    private string $moduleName;
    private string $nameLower;
    private string $nameSnake;
    private string $base;

    public function handle(): int
    {
        $this->moduleName = Str::studly($this->argument('name'));
        $this->moduleNameLower = Str::lower($this->moduleName);
        $this->moduleNameSnake = Str::snake($this->moduleName);
        $this->base      = app_path("Modules/{$this->moduleName}");

        if (is_dir($this->base)) {
            $this->error("Le module {$this->moduleName} existe déjà : {$this->base}");

            return self::FAILURE;
        }

        $this->info("Génération du module <comment>{$this->moduleName}</comment> (Phase {$this->option('phase')})...");
        $this->newLine();

        $this->makeDirectories();
        $this->makeModel();
        $this->makeRepository();
        $this->makeService();
        $this->makeController();
        $this->makeRequests();
        $this->makeResource();
        $this->makeEvents();
        $this->makeServiceProvider();
        $this->makeRoutes();
        $this->makeMigration();

        if (! $this->option('no-tests')) {
            $this->makeTests();
        }

        $this->registerServiceProvider();

        $this->newLine();
        $this->info("✓ Module <comment>{$this->moduleName}</comment> généré avec succès !");
        $this->newLine();
        $this->line('  <fg=yellow>Prochaines étapes :</>');
        $this->line("  1. Enregistrer le provider dans <comment>bootstrap/providers.php</comment>");
        $this->line("  2. Éditer la migration : <comment>database/migrations/*_create_{$this->moduleNameSnake}s_table.php</comment>");
        $this->line("  3. Implémenter le Repository et Service");
        $this->line("  4. Écrire les tests dans <comment>app/Modules/{$this->moduleName}/Tests/</comment>");

        return self::SUCCESS;
    }

    // ── Création des dossiers ────────────────────────────────────────

    private function makeDirectories(): void
    {
        $dirs = [
            'Models',
            'Repositories',
            'Services',
            'Http/Controllers',
            'Http/Requests',
            'Http/Resources',
            'Events',
            'Jobs',
            'Providers',
            'database/migrations',
            'routes',
            'Tests/Unit',
            'Tests/Integration',
            'Tests/Modular',
        ];

        foreach ($dirs as $dir) {
            mkdir("{$this->base}/{$dir}", 0755, true);
        }

        $this->line('  <fg=green>✓</> Répertoires créés');
    }

    // ── Model ────────────────────────────────────────────────────────

    private function makeModel(): void
    {
        $this->writeStub("{$this->base}/Models/{$this->moduleName}.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Models;

        use Illuminate\Database\Eloquent\Concerns\HasUuids;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\SoftDeletes;

        class {$this->moduleName} extends Model
        {
            use HasFactory, HasUuids, SoftDeletes;

            protected \$fillable = [];

            protected function casts(): array
            {
                return [
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ];
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> Model');
    }

    // ── Repository ───────────────────────────────────────────────────

    private function makeRepository(): void
    {
        // Interface
        $this->writeStub("{$this->base}/Repositories/{$this->moduleName}RepositoryInterface.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Repositories;

        use App\Modules\\{$this->moduleName}\Models\\{$this->moduleName};
        use Illuminate\Contracts\Pagination\LengthAwarePaginator;

        interface {$this->moduleName}RepositoryInterface
        {
            public function all(string \$tenantId, array \$filters = []): LengthAwarePaginator;

            public function findById(string \$id, string \$tenantId): ?{$this->moduleName};

            public function create(array \$data): {$this->moduleName};

            public function update({$this->moduleName} \$model, array \$data): {$this->moduleName};

            public function delete({$this->moduleName} \$model): void;
        }
        PHP);

        // Implémentation Eloquent
        $this->writeStub("{$this->base}/Repositories/Eloquent{$this->moduleName}Repository.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Repositories;

        use App\Modules\\{$this->moduleName}\Models\\{$this->moduleName};
        use Illuminate\Contracts\Pagination\LengthAwarePaginator;

        class Eloquent{$this->moduleName}Repository implements {$this->moduleName}RepositoryInterface
        {
            public function all(string \$tenantId, array \$filters = []): LengthAwarePaginator
            {
                return {$this->moduleName}::query()
                    ->where('tenant_id', \$tenantId)
                    ->paginate(20);
            }

            public function findById(string \$id, string \$tenantId): ?{$this->moduleName}
            {
                return {$this->moduleName}::query()
                    ->where('tenant_id', \$tenantId)
                    ->find(\$id);
            }

            public function create(array \$data): {$this->moduleName}
            {
                return {$this->moduleName}::create(\$data);
            }

            public function update({$this->moduleName} \$model, array \$data): {$this->moduleName}
            {
                \$model->update(\$data);

                return \$model->fresh();
            }

            public function delete({$this->moduleName} \$model): void
            {
                \$model->delete();
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> Repository (Interface + Eloquent)');
    }

    // ── Service ──────────────────────────────────────────────────────

    private function makeService(): void
    {
        $this->writeStub("{$this->base}/Services/{$this->moduleName}Service.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Services;

        use App\Modules\\{$this->moduleName}\Models\\{$this->moduleName};
        use App\Modules\\{$this->moduleName}\Repositories\\{$this->moduleName}RepositoryInterface;
        use Illuminate\Contracts\Pagination\LengthAwarePaginator;

        class {$this->moduleName}Service
        {
            public function __construct(
                private readonly {$this->moduleName}RepositoryInterface \$repository,
            ) {}

            public function list(string \$tenantId, array \$filters = []): LengthAwarePaginator
            {
                return \$this->repository->all(\$tenantId, \$filters);
            }

            public function findOrFail(string \$id, string \$tenantId): {$this->moduleName}
            {
                \$model = \$this->repository->findById(\$id, \$tenantId);

                if (! \$model) {
                    abort(404);
                }

                return \$model;
            }

            public function create(array \$data, string \$tenantId): {$this->moduleName}
            {
                return \$this->repository->create([
                    ...\$data,
                    'tenant_id' => \$tenantId,
                ]);
            }

            public function update(string \$id, array \$data, string \$tenantId): {$this->moduleName}
            {
                \$model = \$this->findOrFail(\$id, \$tenantId);

                return \$this->repository->update(\$model, \$data);
            }

            public function delete(string \$id, string \$tenantId): void
            {
                \$model = \$this->findOrFail(\$id, \$tenantId);
                \$this->repository->delete(\$model);
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> Service');
    }

    // ── Controller ───────────────────────────────────────────────────

    private function makeController(): void
    {
        $this->writeStub("{$this->base}/Http/Controllers/{$this->moduleName}Controller.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Http\Controllers;

        use App\Modules\\{$this->moduleName}\Http\Requests\Create{$this->moduleName}Request;
        use App\Modules\\{$this->moduleName}\Http\Requests\Update{$this->moduleName}Request;
        use App\Modules\\{$this->moduleName}\Http\Resources\\{$this->moduleName}Resource;
        use App\Modules\\{$this->moduleName}\Services\\{$this->moduleName}Service;
        use Illuminate\Http\JsonResponse;
        use Illuminate\Http\Request;
        use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
        use Illuminate\Routing\Controller;

        class {$this->moduleName}Controller extends Controller
        {
            public function __construct(
                private readonly {$this->moduleName}Service \$service,
            ) {}

            public function index(Request \$request): AnonymousResourceCollection
            {
                \$items = \$this->service->list(
                    tenantId: \$request->user()->tenant_id,
                    filters:  \$request->query(),
                );

                return {$this->moduleName}Resource::collection(\$items);
            }

            public function show(Request \$request, string \$id): {$this->moduleName}Resource
            {
                \$item = \$this->service->findOrFail(\$id, \$request->user()->tenant_id);

                return new {$this->moduleName}Resource(\$item);
            }

            public function store(Create{$this->moduleName}Request \$request): JsonResponse
            {
                \$item = \$this->service->create(
                    data:     \$request->validated(),
                    tenantId: \$request->user()->tenant_id,
                );

                return (new {$this->moduleName}Resource(\$item))
                    ->response()
                    ->setStatusCode(201);
            }

            public function update(Update{$this->moduleName}Request \$request, string \$id): {$this->moduleName}Resource
            {
                \$item = \$this->service->update(\$id, \$request->validated(), \$request->user()->tenant_id);

                return new {$this->moduleName}Resource(\$item);
            }

            public function destroy(Request \$request, string \$id): JsonResponse
            {
                \$this->service->delete(\$id, \$request->user()->tenant_id);

                return response()->json(null, 204);
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> Controller (CRUD complet)');
    }

    // ── Requests ─────────────────────────────────────────────────────

    private function makeRequests(): void
    {
        foreach (['Create', 'Update'] as $type) {
            $this->writeStub("{$this->base}/Http/Requests/{$type}{$this->moduleName}Request.php", <<<PHP
            <?php

            namespace App\Modules\\{$this->moduleName}\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class {$type}{$this->moduleName}Request extends FormRequest
            {
                public function authorize(): bool
                {
                    return true; // La logique d'autorisation est dans les Policies
                }

                public function rules(): array
                {
                    return [
                        // TODO: définir les règles de validation
                    ];
                }
            }
            PHP);
        }

        $this->line('  <fg=green>✓</> Requests (Create + Update)');
    }

    // ── Resource ─────────────────────────────────────────────────────

    private function makeResource(): void
    {
        $this->writeStub("{$this->base}/Http/Resources/{$this->moduleName}Resource.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Http\Resources;

        use Illuminate\Http\Request;
        use Illuminate\Http\Resources\Json\JsonResource;

        class {$this->moduleName}Resource extends JsonResource
        {
            public function toArray(Request \$request): array
            {
                return [
                    'id'         => \$this->id,
                    'created_at' => \$this->created_at,
                    'updated_at' => \$this->updated_at,
                    // TODO: ajouter les champs du module
                ];
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> Resource API');
    }

    // ── Events ───────────────────────────────────────────────────────

    private function makeEvents(): void
    {
        foreach (['Created', 'Updated', 'Deleted'] as $event) {
            $this->writeStub("{$this->base}/Events/{$this->moduleName}{$event}.php", <<<PHP
            <?php

            namespace App\Modules\\{$this->moduleName}\Events;

            use App\Modules\\{$this->moduleName}\Models\\{$this->moduleName};
            use Illuminate\Broadcasting\InteractsWithSockets;
            use Illuminate\Foundation\Events\Dispatchable;
            use Illuminate\Queue\SerializesModels;

            class {$this->moduleName}{$event}
            {
                use Dispatchable, InteractsWithSockets, SerializesModels;

                public function __construct(
                    public readonly {$this->moduleName} \$model,
                ) {}
            }
            PHP);
        }

        $this->line('  <fg=green>✓</> Events (Created + Updated + Deleted)');
    }

    // ── ServiceProvider ──────────────────────────────────────────────

    private function makeServiceProvider(): void
    {
        $this->writeStub("{$this->base}/Providers/{$this->moduleName}ServiceProvider.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Providers;

        use App\Modules\\{$this->moduleName}\Repositories\\{$this->moduleName}RepositoryInterface;
        use App\Modules\\{$this->moduleName}\Repositories\Eloquent{$this->moduleName}Repository;
        use App\Shared\ModuleServiceProvider;

        class {$this->moduleName}ServiceProvider extends ModuleServiceProvider
        {
            protected string \$moduleName      = '{$this->moduleName}';
            protected string \$moduleNamespace = 'App\\\\Modules\\\\{$this->moduleName}';

            public function register(): void
            {
                // Binding interface → implémentation concrète
                \$this->app->bind(
                    {$this->moduleName}RepositoryInterface::class,
                    Eloquent{$this->moduleName}Repository::class,
                );
            }

            public function boot(): void
            {
                \$this->loadMigrationsFrom(\$this->modulePath('database/migrations'));
                \$this->loadRoutesFrom(\$this->modulePath('routes/api.php'));
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> ServiceProvider');
    }

    // ── Routes ───────────────────────────────────────────────────────

    private function makeRoutes(): void
    {
        $this->writeStub("{$this->base}/routes/api.php", <<<PHP
        <?php

        use App\Modules\\{$this->moduleName}\Http\Controllers\\{$this->moduleName}Controller;
        use Illuminate\Support\Facades\Route;

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::apiResource('{$this->moduleNameSnake}s', {$this->moduleName}Controller::class);
        });
        PHP);

        $this->line('  <fg=green>✓</> Routes API');
    }

    // ── Migration ────────────────────────────────────────────────────

    private function makeMigration(): void
    {
        $timestamp = now()->format('Y_m_d_His');
        $filename  = "{$timestamp}_create_{$this->moduleNameSnake}s_table.php";

        $this->writeStub("{$this->base}/database/migrations/{$filename}", <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$this->moduleNameSnake}s', function (Blueprint \$table) {
                    \$table->uuid('id')->primary();
                    \$table->uuid('tenant_id')->index();
                    // TODO: ajouter les colonnes du module
                    \$table->softDeletes();
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$this->moduleNameSnake}s');
            }
        };
        PHP);

        $this->line('  <fg=green>✓</> Migration');
    }

    // ── Tests ────────────────────────────────────────────────────────

    private function makeTests(): void
    {
        // Test Unitaire
        $this->writeStub("{$this->base}/Tests/Unit/{$this->moduleName}ServiceTest.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Tests\Unit;

        use App\Modules\\{$this->moduleName}\Repositories\\{$this->moduleName}RepositoryInterface;
        use App\Modules\\{$this->moduleName}\Services\\{$this->moduleName}Service;
        use Mockery;
        use Mockery\MockInterface;
        use PHPUnit\Framework\Attributes\Test;
        use PHPUnit\Framework\TestCase;

        class {$this->moduleName}ServiceTest extends TestCase
        {
            private {$this->moduleName}Service \$service;
            private MockInterface \$repository;

            protected function setUp(): void
            {
                parent::setUp();
                \$this->repository = Mockery::mock({$this->moduleName}RepositoryInterface::class);
                \$this->service    = new {$this->moduleName}Service(\$this->repository);
            }

            protected function tearDown(): void
            {
                Mockery::close();
                parent::tearDown();
            }

            #[Test]
            public function it_delegates_list_to_repository(): void
            {
                \$this->repository
                    ->shouldReceive('all')
                    ->once()
                    ->with('tenant-abc', [])
                    ->andReturn(collect());

                \$this->service->list('tenant-abc');

                \$this->addToAssertionCount(1);
            }

            // TODO: ajouter les tests métier spécifiques au module
        }
        PHP);

        // Test d'Intégration
        $this->writeStub("{$this->base}/Tests/Integration/{$this->moduleName}ApiTest.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Tests\Integration;

        use Illuminate\Foundation\Testing\RefreshDatabase;
        use PHPUnit\Framework\Attributes\Test;
        use Tests\TestCase;

        class {$this->moduleName}ApiTest extends TestCase
        {
            use RefreshDatabase;

            #[Test]
            public function authenticated_user_can_list_{$this->moduleNameSnake}s(): void
            {
                \$response = \$this->getJson('/api/{$this->moduleNameSnake}s');

                // Doit retourner 401 sans authentification
                \$response->assertStatus(401);
            }

            #[Test]
            public function user_cannot_access_other_tenant_{$this->moduleNameSnake}s(): void
            {
                // TODO: tester l'isolation multitenant
                \$this->markTestIncomplete('Isolation multitenant à tester');
            }
        }
        PHP);

        // Test Modulaire
        $this->writeStub("{$this->base}/Tests/Modular/{$this->moduleName}ModuleTest.php", <<<PHP
        <?php

        namespace App\Modules\\{$this->moduleName}\Tests\Modular;

        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Tests\TestCase;

        class {$this->moduleName}ModuleTest extends TestCase
        {
            use RefreshDatabase;

            #[Test]
            public function complete_{$this->moduleNameSnake}_crud_flow(): void
            {
                \$this->markTestIncomplete('Implémenter le flux complet du module {$this->moduleName}');
            }
        }
        PHP);

        $this->line('  <fg=green>✓</> Tests (Unit + Integration + Modular)');
    }

    // ── Enregistrement automatique du ServiceProvider ────────────────

    private function registerServiceProvider(): void
    {
        $providersFile = base_path('bootstrap/providers.php');

        if (! file_exists($providersFile)) {
            $this->warn('  bootstrap/providers.php introuvable — enregistrement manuel requis');

            return;
        }

        $content     = file_get_contents($providersFile);
        $providerFqcn = "App\\Modules\\{$this->moduleName}\\Providers\\{$this->moduleName}ServiceProvider::class";

        if (str_contains($content, $providerFqcn)) {
            $this->line('  <fg=yellow>!</> ServiceProvider déjà enregistré');

            return;
        }

        // Insérer avant la dernière ligne `];`
        $content = str_replace(
            "];",
            "    {$providerFqcn},\n];",
            $content,
        );

        file_put_contents($providersFile, $content);
        $this->line('  <fg=green>✓</> ServiceProvider enregistré dans bootstrap/providers.php');
    }

    // ── Helper ───────────────────────────────────────────────────────

    private function writeStub(string $path, string $content): void
    {
        // Nettoyer l'indentation héritée du heredoc
        $lines   = explode("\n", $content);
        $cleaned = array_map(fn ($line) => preg_replace('/^        /', '', $line), $lines);

        file_put_contents($path, implode("\n", $cleaned));
    }
}
