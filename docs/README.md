# ETech ERP Africa — Documentation

Bienvenue dans la documentation officielle de **ETech ERP Africa**, un ERP SaaS multi-tenant conçu pour les PME du commerce africain (Sénégal, Côte d'Ivoire, Mali, Cameroun…).

---

## Table des matières

### Architecture
| Document | Description |
|----------|-------------|
| [Vue d'ensemble](architecture/overview.md) | Stack technique, principes de conception, structure du monorepo |
| [Système de modules](architecture/modules.md) | Comment les modules Laravel sont organisés et comment en créer un nouveau |
| [Schéma de base de données](architecture/database-schema.md) | Toutes les tables, colonnes et relations |

### Modules backend
| Module | Statut | Document |
|--------|--------|----------|
| Auth | ✅ Stable | [auth.md](modules/auth.md) |
| Catalog | ✅ Stable | [catalog.md](modules/catalog.md) |
| Inventory | 🔜 En cours | — |
| Orders | 🔜 Planifié | — |
| Payments | 🔜 Planifié | — |
| Delivery | 🔜 Planifié | — |

### Référence API
| Groupe | Document |
|--------|----------|
| Authentification | [api/auth.md](api/auth.md) |
| Catalogue produits | [api/catalog.md](api/catalog.md) |
| Étiquettes & codes | [api/labels.md](api/labels.md) |

### Guides techniques
| Guide | Description |
|-------|-------------|
| [Installation & setup](guides/development-setup.md) | Prérequis, clone, configuration locale |
| [Conventions de code](guides/conventions.md) | Standards, patterns, règles du projet |
| [Tests](guides/testing.md) | PHPUnit 12, stratégie unit/intégration |

### Guide utilisateur
| Guide | Description |
|-------|-------------|
| [Prise en main](user/getting-started.md) | Présentation, premier login, concept tenant |
| [Catalogue & produits](user/catalog.md) | Gérer produits, catégories, variantes |
| [Étiquettes produits](user/labels.md) | Imprimer étiquettes thermique et A4 |

---

## Version du produit

| Élément | Valeur |
|---------|--------|
| Version | 0.1.0 (Phase 1 — Backend Core) |
| Backend | Laravel 13 / PHP 8.3 |
| Base de données | MySQL 8 (production) · SQLite (tests) |
| Dernière mise à jour | 2026-05-30 |
