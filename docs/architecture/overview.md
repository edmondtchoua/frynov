# Vue d'ensemble de l'architecture

## Positionnement produit

ETech ERP Africa est un ERP SaaS **multi-tenant** ciblant les PME commerçantes d'Afrique subsaharienne : boutiques de vêtements, épiceries, quincailleries, pharmacies, distributeurs. Le produit fonctionne offline-first sur mobile (Flutter POS) et en ligne sur navigateur (Vue 3 admin).

---

## Structure du monorepo

```
ETech/
├── backend/          Laravel 13 — API REST + logique métier
├── frontend/         Vue 3 + Vite — Interface d'administration web
├── mobile/           Flutter — Application POS (point de vente)
├── shared/           Contrats partagés (types TypeScript, schémas)
├── infra/            Terraform, Nginx, scripts de déploiement
├── docs/             ← Vous êtes ici
├── docker-compose.yml
└── Makefile
```

---

## Stack technique

### Backend
| Composant | Technologie | Version |
|-----------|-------------|---------|
| Framework | Laravel | 13.x |
| Langage | PHP | 8.3 |
| Authentification | Laravel Sanctum | ^4.0 |
| Permissions | Spatie Laravel Permission | ^7.4 |
| QR Codes | simplesoftwareio/simple-qrcode | ^4.2 |
| Codes-barres | picqer/php-barcode-generator | ^3.2 |
| Tests | PHPUnit | ^12 |

### Frontend (planifié)
| Composant | Technologie |
|-----------|-------------|
| Framework | Vue 3 + Composition API |
| Build | Vite |
| État | Pinia |
| UI | Tailwind CSS |

### Mobile POS (planifié)
| Composant | Technologie |
|-----------|-------------|
| Framework | Flutter 3 |
| État | Riverpod |
| Offline | SQLite local + sync |

### Infrastructure
| Composant | Technologie |
|-----------|-------------|
| Conteneurs | Docker + Docker Compose |
| Base de données | MySQL 8 |
| Cache / Mutex | Redis |
| CI/CD | GitHub Actions |

---

## Principes de conception

### 1. Multi-tenancy par colonne `tenant_id`
Chaque ligne de données appartient à un tenant via une colonne `tenant_id` UUID. Il n'y a pas de bases de données séparées par tenant — un seul schéma partagé avec isolation par filtre. Le tenant est résolu à chaque requête API via :
1. Header `X-Tenant-ID` (UUID direct)
2. Header `X-Tenant-Slug` (slug lisible)
3. Sous-domaine (`boutique.etech.sn` → slug `boutique`)

### 2. Clés primaires UUID
Tous les modèles utilisent des UUID v4 comme clé primaire (`HasUuids` trait). Avantages : pas de conflit entre tenants, identifiants non-séquentiels exposables en API.

### 3. Monnaie en centimes entiers
Les montants sont **toujours stockés en centimes** (entier) et jamais en float. `25 000 XOF` est stocké comme `25000`. Le Value Object `Money` gère le formatage à l'affichage.

```php
// ✅ Correct
Money::of(25000, 'XOF')  // → "25 000 XOF"

// ❌ Jamais
$price = 250.00;
```

### 4. Architecture modulaire
Le backend est découpé en modules isolés sous `app/Modules/`. Chaque module est auto-suffisant (modèles, controllers, services, routes, tests, vues). Voir [Système de modules](modules.md).

### 5. Session unique par token
À la connexion, tous les tokens API existants de l'utilisateur sont révoqués avant d'en émettre un nouveau. Cela évite les sessions fantômes et simplifie la gestion de sécurité.

### 6. Permissions avec équipes (Spatie teams)
Spatie Permission est configuré avec `teams: true`, où `team_foreign_key = tenant_id`. Un utilisateur peut avoir des rôles différents dans des tenants différents. La résolution du tenant courant se fait via `setPermissionsTeamId($tenantId)` dans le middleware.

---

## Flux d'une requête API typique

```
Client HTTP
    │
    ▼
[Middleware: api]
    │
    ├── ResolveTenant          → résout X-Tenant-ID / slug / sous-domaine
    │                            bind 'current.tenant' dans le container
    ├── Sanctum auth           → vérifie Bearer token
    │
    └── EnsureUserBelongsToTenant → vérifie que l'user appartient au tenant

    ▼
[Controller]
    │
    ├── Validation (FormRequest)
    ├── Service (logique métier)
    └── Resource (JSON / HTML)

    ▼
Client HTTP (réponse)
```

---

## Conventions de nommage

| Élément | Convention | Exemple |
|---------|------------|---------|
| Classes PHP | PascalCase | `ProductCodeService` |
| Méthodes PHP | camelCase | `generateForProduct()` |
| Tables BDD | snake_case pluriel | `product_variants` |
| Colonnes BDD | snake_case | `price_amount` |
| Routes API | kebab-case | `/api/catalog/products` |
| Événements | Passé | `ProductArchived` |
| Jobs | Verbe | `SyncInventoryJob` |
