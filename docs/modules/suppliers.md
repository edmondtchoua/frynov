# Module Suppliers — Documentation technique

## Vue d'ensemble

Gestion des fournisseurs avec code auto-généré, recherche et liaison aux produits. Isolé par tenant.

## Architecture

```
app/Modules/Suppliers/
├── Providers/SuppliersServiceProvider.php
├── Models/Supplier.php
├── Services/SupplierService.php
├── Http/
│   ├── Controllers/SupplierController.php
│   ├── Requests/{Create,Update}SupplierRequest.php
│   └── Resources/SupplierResource.php
├── database/migrations/
│   └── 2026_05_30_600001_create_suppliers_table.php
├── routes/api.php
└── Tests/
    ├── Unit/SupplierServiceTest.php   (7 tests)
    └── Integration/SupplierApiTest.php (7 tests)
```

## Modèle — Supplier

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | UUID | PK |
| `tenant_id` | UUID | Isolation tenant |
| `code` | VARCHAR | Code fournisseur (auto-généré si absent) |
| `name` | VARCHAR | Nom obligatoire |
| `email` | VARCHAR | Nullable |
| `phone` | VARCHAR | Nullable |
| `contact_name` | VARCHAR | Interlocuteur principal |
| `address` | JSON | Adresse structurée |
| `payment_terms` | VARCHAR | Conditions de paiement |
| `notes` | TEXT | Notes libres |
| `status` | ENUM | `active` \| `inactive`, défaut `active` |
| `deleted_at` | TIMESTAMP | Soft delete |

## Service — SupplierService

### `create(array $data, string $tenantId): Supplier`
- Auto-génère `code` si absent (format `FXXX`, incrémental)
- Force `status = 'active'` si non fourni

### `update(Supplier $supplier, array $data): Supplier`
- Mise à jour partielle avec `fill()`

### `delete(Supplier $supplier): void`
- Soft delete

### `findOrFail(string $id, string $tenantId): Supplier`
- Lève `ModelNotFoundException` si non trouvé OU appartient à un autre tenant

### `findOrCreateByName(string $name, string $tenantId): Supplier`
- Cherche par nom (case-insensitive via `LOWER()`)
- Crée si absent — utilisé par l'import de produits

### `list(string $tenantId, array $filters = []): LengthAwarePaginator`
- Filtre : `search` (nom, code, email), `status`
- Tri par `name`, 20 par page

## Auto-génération du code

```php
$max = Supplier::where('tenant_id', $tenantId)
    ->where('code', 'LIKE', 'F%')
    ->max('code');

$next = $max ? (int) ltrim(substr($max, 1), '0') + 1 : 1;
return 'F' . str_pad($next, 3, '0', STR_PAD_LEFT);  // F001, F002, ...
```

## Routes

```
GET    /api/suppliers           → index (paginé, filtrés)
POST   /api/suppliers           → create
GET    /api/suppliers/{id}      → show
PUT    /api/suppliers/{id}      → update
DELETE /api/suppliers/{id}      → delete (soft)
GET    /api/suppliers/search    → typeahead (q=...)
```

## Resource — SupplierResource

Expose tous les champs + `products_count` (whenLoaded).

## Tests

**Unit (7 tests) :** auto_code creation, code increment, update, soft_delete, findOrFail wrong tenant throws, findOrCreateByName creates, findOrCreateByName returns existing.

**Integration (7 tests) :** requires auth, lists only tenant suppliers, creates supplier, shows supplier, updates supplier, deletes supplier, cannot access other tenant supplier.
