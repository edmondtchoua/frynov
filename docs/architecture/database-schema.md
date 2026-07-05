# Schéma de base de données

> Moteur : **MySQL 8** (production) — **SQLite** (tests)
> Convention : toutes les clés primaires sont des **UUID v4**.

---

## Diagramme simplifié

```
tenants ──< users
        ──< products ──< product_variants
        ──< categories ──< products (via category_id)
```

---

## Table `tenants`

Représente une entreprise cliente (boutique, commerce, PME).

| Colonne | Type | Contrainte | Description |
|---------|------|-----------|-------------|
| `id` | uuid | PK | Identifiant unique |
| `name` | varchar(255) | NOT NULL | Nom de l'entreprise |
| `slug` | varchar(100) | UNIQUE | Identifiant URL (ex: `boutique-dakar`) |
| `plan` | varchar(50) | DEFAULT 'starter' | Plan tarifaire (`starter`, `pro`, `enterprise`) |
| `status` | varchar(20) | DEFAULT 'active' | `active`, `suspended`, `cancelled` |
| `settings` | json | NULLABLE | Paramètres : monnaie, fuseau, etc. |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Exemple `settings` :**
```json
{
  "currency": "XOF",
  "timezone": "Africa/Dakar",
  "language": "fr"
}
```

---

## Table `users`

Utilisateurs du système. Un user appartient toujours à un tenant (sauf super-admin).

| Colonne | Type | Contrainte | Description |
|---------|------|-----------|-------------|
| `id` | uuid | PK | |
| `tenant_id` | uuid | FK tenants, NULLABLE | NULL = super-admin Anthropic |
| `name` | varchar(255) | NOT NULL | |
| `email` | varchar(255) | UNIQUE | |
| `password` | varchar(255) | NOT NULL | Bcrypt hashé |
| `is_super_admin` | boolean | DEFAULT false | Accès cross-tenant |
| `email_verified_at` | timestamp | NULLABLE | |
| `remember_token` | varchar(100) | NULLABLE | |
| `deleted_at` | timestamp | NULLABLE | Soft delete |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

---

## Table `personal_access_tokens`

Tokens Sanctum. Un seul token actif par utilisateur (session unique).

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint PK | |
| `tokenable_type` | varchar | Polymorphique (`App\Models\User`) |
| `tokenable_id` | uuid | ID de l'utilisateur |
| `name` | varchar | Libellé du token (`api`) |
| `token` | varchar(64) | Hash SHA-256 |
| `abilities` | json | `["*"]` |
| `last_used_at` | timestamp | |
| `expires_at` | timestamp | +30 jours à l'émission |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## Tables Spatie Permission (avec teams)

### `roles`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint PK | |
| `team_id` | uuid NULLABLE | = `tenant_id` du contexte |
| `name` | varchar | `admin`, `manager`, `cashier`, `viewer` |
| `guard_name` | varchar | `sanctum` |

### `permissions`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint PK | |
| `name` | varchar | Ex: `products.create`, `inventory.edit` |
| `guard_name` | varchar | `sanctum` |

### `model_has_roles` / `model_has_permissions`

| Colonne | Type | Description |
|---------|------|-------------|
| `role_id` / `permission_id` | bigint | |
| `model_type` | varchar | `App\Models\User` |
| `model_uuid` | uuid | ID de l'utilisateur (clé morphique UUID) |
| `team_id` | uuid NULLABLE | = `tenant_id` |

---

## Table `categories`

Catégories de produits, hiérarchiques (auto-référentielle).

| Colonne | Type | Contrainte | Description |
|---------|------|-----------|-------------|
| `id` | uuid | PK | |
| `tenant_id` | uuid | FK tenants | |
| `parent_id` | uuid | FK categories, NULLABLE | Sous-catégorie |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | NOT NULL | Auto-généré, unique par tenant |
| `description` | text | NULLABLE | |
| `sort_order` | int | DEFAULT 0 | Ordre d'affichage |
| `is_active` | boolean | DEFAULT true | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Index : `UNIQUE(tenant_id, slug)`

---

## Table `products`

Produit principal (avec ou sans variantes).

| Colonne | Type | Contrainte | Description |
|---------|------|-----------|-------------|
| `id` | uuid | PK | |
| `tenant_id` | uuid | FK tenants | |
| `category_id` | uuid | FK categories, NULLABLE | |
| `sku` | varchar(100) | NOT NULL | Ex: `VET-0001` |
| `name` | varchar(255) | NOT NULL | |
| `description` | text | NULLABLE | |
| `price_amount` | int | NOT NULL | En centimes. Ex: `25000` = 25 000 XOF |
| `price_currency` | char(3) | NOT NULL | `XOF`, `EUR`, `USD`, `GNF`… |
| `compare_at_price_amount` | int | NULLABLE | Prix barré (promo si supérieur à price) |
| `cost_amount` | int | NULLABLE | Prix d'achat (marge) |
| `stock_quantity` | int | DEFAULT 0 | Quantité en stock |
| `low_stock_threshold` | int | DEFAULT 5 | Alerte stock bas |
| `has_variants` | boolean | DEFAULT false | True = voir product_variants |
| `status` | varchar(20) | DEFAULT 'draft' | `draft`, `active`, `archived` |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |
| `deleted_at` | timestamp | NULLABLE | Soft delete |

Index : `UNIQUE(tenant_id, sku)`

---

## Table `product_variants`

Variantes d'un produit (taille, couleur, etc.).

| Colonne | Type | Contrainte | Description |
|---------|------|-----------|-------------|
| `id` | uuid | PK | |
| `product_id` | uuid | FK products | |
| `sku` | varchar(100) | NOT NULL | Ex: `VET-0001-V1` |
| `name` | varchar(255) | NOT NULL | Ex: `Rouge / L` |
| `attributes` | json | NOT NULL | `{"Couleur": "Rouge", "Taille": "L"}` |
| `price_amount` | int | NULLABLE | Si NULL → hérite du produit parent |
| `price_currency` | char(3) | NULLABLE | |
| `stock_quantity` | int | DEFAULT 0 | |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Index : `UNIQUE(product_id, sku)`

---

---

## Table `stocks`

Stock courant par produit ou par variante (une ligne par produit/variante).

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | uuid PK | |
| `tenant_id` | uuid index | |
| `product_id` | uuid FK products | |
| `variant_id` | uuid nullable | Si NULL : stock du produit de base |
| `quantity` | int unsigned | Quantité physique actuelle |
| `reserved_quantity` | int unsigned | Réservé pour commandes en attente |
| `low_stock_threshold` | smallint | Seuil d'alerte stock bas (défaut 5) |

**Règle :** un produit avec variantes n'a pas de ligne stock propre — seules ses variantes en ont.

**Disponible = `quantity − reserved_quantity`** (toujours ≥ 0)

---

## Table `stock_movements`

Audit trail immutable de chaque opération de stock.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | uuid PK | |
| `tenant_id` | uuid index | |
| `stock_id` | uuid FK stocks | |
| `product_id` | uuid index | Dénormalisé pour requêtes rapides |
| `variant_id` | uuid nullable | |
| `type` | varchar(20) | `in`, `out`, `adjustment`, `return` |
| `quantity` | int unsigned | Toujours positif |
| `quantity_before` | int | Snapshot avant opération |
| `quantity_after` | int | Snapshot après opération |
| `reason` | varchar(50) | `delivery`, `sale`, `loss`, `return`, `count`, `manual` |
| `reference` | varchar(100) nullable | Ex: `BL-2026-001`, `CMD-2026-042` |
| `note` | text nullable | Note libre |
| `performed_by` | uuid nullable | ID de l'utilisateur |

Index : `(stock_id, created_at)`, `(tenant_id, type)`

---

## Évolution du schéma

| Module | Tables à venir |
|--------|---------------|
| Orders | `orders`, `order_lines` |
| Payments | `payments`, `payment_methods` |
| Customers | `customers`, `customer_addresses` |
| Delivery | `deliveries`, `delivery_lines` |
