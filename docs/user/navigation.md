# Guide utilisateur — Navigation Frynov ERP

> **Dernière mise à jour :** 2026-06-04 (pricing localisé + stratégie modules visibles)

---

## Structure de navigation

Frynov ERP utilise une navigation en **2 niveaux** :

1. **Sidebar** (gauche) — accès aux grands modules
2. **Onglets** (dans la page) — navigation dans les sections d'un module

---

## Sidebar

La sidebar liste les modules principaux de votre espace de travail.

```
Tableau de bord
Catalogue
Stock & Inventaire
Ventes
Clients
Fournisseurs
Rapports
Import / Export
Marketplace
──────────────
Paramètres
```

Sur **desktop** : la sidebar peut être réduite (icône seule) via le bouton `◄` en haut.  
Sur **mobile** : la sidebar s'ouvre via le menu hamburger ☰.

---

## Onglets par module

Chaque module affiche ses sections via une barre d'onglets en haut de la page.

### Catalogue
| Onglet | Accès |
|---|---|
| Produits | Liste + création produits |
| Catégories | Gestion de l'arborescence |
| Déclinaisons | Vue globale des variantes produit |
| Étiquettes | Impression thermique / A4 |

### Stock & Inventaire
| Onglet | Accès |
|---|---|
| Stock | Liste des stocks par produit |
| Alertes [N] | Produits en stock bas |
| Entrepôts | Gestion des dépôts |
| Transferts | Transferts inter-entrepôts |
| Clôture de période | Verrouillage comptable |

### Ventes
| Onglet | Accès |
|---|---|
| Commandes | Toutes les commandes |
| Retours & SAV | Gestion des retours clients |
| Paiements | Historique des encaissements |
| Livraisons | Suivi des livraisons |

### Rapports
| Onglet | Accès |
|---|---|
| Rapport des ventes | CA, top produits, méthodes de paiement |
| Rapport de stock | Valeur stock, alertes, mouvements |

### Paramètres
| Onglet | Accès |
|---|---|
| Entreprise | Nom, pays, devise, adresse |
| Équipe | Utilisateurs, rôles, invitations |
| Abonnement | Plan actuel, usages, paiements |
| Intégrations | *(Bientôt disponible)* |
| Notifications | *(Bientôt disponible)* |

---

## Actions principales par page

| Page | Action principale | Action secondaire |
|---|---|---|
| Catalogue > Produits | `+ Nouveau produit` | Importer CSV |
| Catalogue > Catégories | `+ Nouvelle catégorie` | — |
| Stock > Stock | `Ajuster le stock` | Voir mouvements |
| Stock > Entrepôts | `+ Nouvel entrepôt` | — |
| Stock > Transferts | `+ Nouveau transfert` | — |
| Ventes > Commandes | `+ Nouvelle commande` | Filtrer par statut |
| Ventes > Retours | `Créer un retour` | — |
| Ventes > Paiements | `+ Nouveau paiement` | — |
| Clients | `+ Nouveau client` | Rechercher |
| Fournisseurs | `+ Nouveau fournisseur` | — |
| Rapports | Exporter | Filtrer période |
| Paramètres > Équipe | `+ Inviter un membre` | — |
| Paramètres > Abonnement | `Changer de plan` | Payer manuellement |

---

## Qui voit quoi ?

| Élément | Visible par |
|---|---|
| Modules sidebar | Les modules métier publics sont visibles selon rôle et configuration ; les actions restent protégées par permissions/quotas |
| Onglet Abonnement | Tous *(données sensibles réservées admin)* |
| Actions de mutation (créer, modifier, supprimer) | Manager et Admin uniquement |
| Section Admin back-office | Super-admin Frynov uniquement |

> Pour changer votre rôle, contactez l'administrateur de votre espace de travail (Paramètres > Équipe).

---

## Droits par module

Certains onglets ou actions peuvent être masqués ou verrouillés selon votre rôle, vos permissions, les modules activés et les limites de votre plan. Contactez votre administrateur pour en savoir plus.
