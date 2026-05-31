# Fournisseurs — Guide utilisateur

## Vue d'ensemble

Le module **Fournisseurs** permet de gérer vos partenaires d'approvisionnement. Vous pouvez associer un fournisseur à vos produits pour faciliter les réassorts et les imports de catalogue.

---

## Accéder aux fournisseurs

Dans la navigation : **Fournisseurs**

---

## Liste des fournisseurs

La liste affiche tous vos fournisseurs avec :
- **Code** : code unique (F001, F002...) affiché en monospace
- **Nom** et interlocuteur principal
- **Contact** : email (cliquable) et téléphone
- **Conditions** : délais de paiement
- **Statut** : Actif / Inactif (badge coloré)

### Filtres

- Recherche par nom, code ou email
- Filtre par statut (Actif / Inactif)

---

## Créer un fournisseur

Cliquez sur **+ Nouveau fournisseur** (en haut à droite).

Le formulaire s'ouvre en modal :

| Champ | Requis | Description |
|-------|--------|-------------|
| Nom | ✅ | Nom du fournisseur |
| Code | Non | Auto-généré si vide (F001, F002...) |
| Email | Non | Adresse email de contact |
| Téléphone | Non | Numéro avec indicatif international |
| Interlocuteur | Non | Nom du contact principal |
| Conditions de paiement | Non | Ex : "30 jours fin de mois" |
| Notes | Non | Informations libres |
| Statut | Non | Actif par défaut |

---

## Modifier un fournisseur

Cliquez sur le **crayon ✏** dans la ligne du fournisseur. Le même formulaire s'ouvre pré-rempli.

---

## Désactiver un fournisseur

Modifiez le fournisseur et passez le statut à **Inactif**. Il reste dans la liste mais ne peut plus être sélectionné lors de la création de produits.

---

## Supprimer un fournisseur

Cliquez sur la **croix ✕** dans la ligne. Une confirmation est demandée. La suppression est irréversible.

> ⚠️ Les produits liés à ce fournisseur garderont l'association mais ne pourront plus trouver le fournisseur dans la liste de sélection.

---

## Liaison avec les produits

Dans la fiche produit (Catalogue → Produit), vous pouvez sélectionner un fournisseur. Cela permet :
- De préremplir automatiquement le fournisseur lors d'un **import de produits**
- D'identifier rapidement la source d'approvisionnement

---

## Import de fournisseurs en masse

Utilisez le module **Import / Export** pour importer une liste de fournisseurs depuis un fichier Excel. Voir le guide Import / Export.
