# Module Auth

## Responsabilité

Gère l'authentification des utilisateurs, la résolution du tenant courant par requête, et l'autorisation basée sur les rôles (RBAC multi-tenant).

---

## Services

### `AuthService`

Point d'entrée pour toutes les opérations d'authentification.

**Méthodes publiques :**

```php
// Connexion : retourne ['user' => User, 'token' => string]
// Lève InvalidCredentialsException ou TenantInactiveException
public function login(string $email, string $password, ?string $tenantId = null): array

// Valide un mot de passe contre le hash Bcrypt stocké
public function validateCredentials(User $user, string $password): bool

// True si l'utilisateur peut accéder à son tenant (super-admin ou tenant actif)
public function canAccessTenant(User $user): bool
```

**Comportement de `login()` :**
1. Cherche l'utilisateur par email (+ tenant_id si fourni)
2. Vérifie le mot de passe avec `Hash::check()`
3. Vérifie que le tenant est actif (ou que l'user est super-admin)
4. Révoque tous les tokens existants nommés `api` (session unique)
5. Émet un nouveau token avec expiration 30 jours

### `TenantResolverService`

Résout le tenant courant à partir de la requête HTTP.

**Ordre de résolution :**
```
1. Header X-Tenant-ID     → UUID direct du tenant
2. Header X-Tenant-Slug   → slug (ex: "boutique-dakar")
3. Sous-domaine           → "boutique.etech.sn" → slug "boutique"
   (ignoré si "www", "api", "app", "staging")
```

```php
public function resolve(Request $request): ?Tenant
```

---

## Middleware

### `ResolveTenant`

Appelé sur **toutes** les requêtes API (`api` middleware group, `bootstrap/app.php`).

- Appelle `TenantResolverService::resolve()`
- Si un tenant est trouvé : le stocke dans `$request->attributes` ET dans le conteneur IoC (`app()->instance('current.tenant', $tenant)`)
- Si aucun tenant → passe (certaines routes sont publiques ou super-admin)

### `EnsureUserBelongsToTenant`

Appliqué sur les routes protégées nécessitant un contexte tenant.

| Scénario | Comportement |
|----------|-------------|
| Tenant résolu + user du même tenant | OK |
| Tenant résolu + user d'un autre tenant | 403 Forbidden |
| Aucun tenant + user avec tenant_id | Auto-scope : attache le tenant de l'user à la requête |
| Aucun tenant + user sans tenant_id (super-admin) | 400 Bad Request |

**Appel recommandé :**
```php
Route::middleware(['auth:sanctum', EnsureUserBelongsToTenant::class])->group(function () {
    // routes tenant-scoped
});
```

---

## Modèle `User`

```php
// Traits
use HasUuids;           // UUID v4 comme clé primaire
use HasApiTokens;       // Sanctum
use HasRoles;           // Spatie Permission (team-aware)
use SoftDeletes;

// Casts
'password' => 'hashed'  // Hash automatique à l'assignation

// Méthodes
public function isSuperAdmin(): bool
public function tenant(): BelongsTo
```

---

## Configuration Spatie Permission

```php
// config/permission.php
'teams' => true,
'column_names' => [
    'model_morph_key'  => 'model_uuid',   // UUID, pas bigint
    'team_foreign_key' => 'tenant_id',
],
```

**Utilisation :**
```php
// Avant toute vérification de permission, setter le tenant courant
setPermissionsTeamId($tenantId);

$user->hasRole('admin');          // dans le contexte du tenant courant
$user->hasPermissionTo('products.create');
```

**Rôles prédéfinis :**

| Rôle | Description |
|------|-------------|
| `admin` | Accès complet au tenant |
| `manager` | Catalogue, stock, commandes |
| `cashier` | POS uniquement (vente, encaissement) |
| `viewer` | Lecture seule (rapports) |

---

## Routes API

Voir [api/auth.md](../api/auth.md) pour la référence complète des endpoints.

---

## Tests

| Fichier | Type | Tests |
|---------|------|-------|
| `Tests/Unit/AuthServiceTest.php` | Unit | 8 |
| `Tests/Integration/AuthApiTest.php` | Integration | 7 |

**Lancer les tests Auth :**
```bash
php vendor/bin/phpunit "app/Modules/Auth/Tests" --no-coverage
```
