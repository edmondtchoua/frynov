# Prise en main — Frynov ERP

> **Dernière mise à jour :** 2026-06-01

---

## Qu'est-ce que Frynov ERP ?

Frynov ERP est un logiciel de gestion d'entreprise cloud, conçu pour les commerçants, distributeurs et PME. Il vous permet de :

- **Gérer votre catalogue produits** — créer des articles, des catégories, des variantes
- **Suivre votre stock** — entrées, sorties, inventaires, transferts inter-entrepôts, alertes
- **Traiter vos ventes** — commandes, encaissements (cash, Mobile Money, carte…)
- **Gérer vos retours** — approbation, remise en stock, résolution de litiges
- **Suivre vos livraisons** — de la commande jusqu'au client
- **Gérer vos clients** — historique d'achats, contact
- **Imprimer des étiquettes** — codes QR et codes-barres pour vos produits
- **Consulter vos rapports** — chiffre d'affaires, marges, rotation des stocks

---

## Connexion à votre espace

### Depuis le navigateur web

1. Ouvrez votre navigateur et allez sur **`https://app.frynov.com`**
2. Entrez votre adresse email et votre mot de passe
3. Cliquez sur **Se connecter**

### Première connexion

Si c'est votre première connexion, un assistant de démarrage (onboarding) vous guidera pour :
- Configurer le nom et le pays de votre entreprise
- Définir votre devise (XOF, XAF, MAD, NGN…)
- Choisir vos modules (stock, livraison, e-commerce…)
- Indiquer le nombre de points de vente
- Créer votre premier entrepôt

---

## Rôles et droits d'accès

Chaque utilisateur a un **rôle** qui détermine ce qu'il peut faire dans le système.

| Rôle | Description | Accès catalogue & stock |
|------|-------------|-------------------------|
| **Admin** | Propriétaire ou gérant — accès complet | ✅ Création, modification, suppression, ajustements |
| **Manager** | Responsable opérationnel | ✅ Création, modification, ajustements stock |
| **Member** | Employé polyvalent | ✅ Création catégories et produits — ❌ Pas d'ajustement stock |
| **Viewer** | Lecture seule | ❌ Consultation uniquement |
| **Agent** | Commercial ou agent terrain | ❌ Consultation stock uniquement |
| **Cashier** | Caissier POS | ❌ Consultation produits uniquement |
| **Commercial** | Représentant commercial | ❌ Consultation catalogue uniquement |
| **Delivery** | Livreur | ❌ Accès livraisons uniquement |

### Qui peut créer quoi ?

| Opération | Admin | Manager | Member | Viewer | Agent | Cashier |
|-----------|:-----:|:-------:|:------:|:------:|:-----:|:-------:|
| Créer une catégorie | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Créer un produit | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Entrée de stock** (réception) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Sortie de stock** (perte, vente manuelle) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Ajustement de stock** (inventaire) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Créer une commande | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |
| Enregistrer un paiement | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| Gérer les utilisateurs | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Verrouiller une période fiscale | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

> ⚠️ Les restrictions de rôle sont appliquées **côté serveur**. Un utilisateur avec un rôle insuffisant qui tente une opération non autorisée reçoit une erreur `403 Accès refusé`, même s'il voit le bouton à l'écran.

---

## Navigation principale

| Section | Description | Accès minimum |
|---------|-------------|---------------|
| **Tableau de bord** | KPIs du jour — ventes, stock critique, commandes en attente | Tous |
| **Catalogue** | Produits, catégories, variantes, étiquettes | Tous (lecture) |
| **Stock** | Mouvements, inventaires, transferts, alertes | Tous (lecture) |
| **Commandes** | Création, suivi, confirmation, livraison | Agent+ |
| **Retours** | Retours clients, approbation, remise en stock | Manager+ |
| **Paiements** | Enregistrement et suivi des paiements | Cashier+ |
| **Clients** | Base clients, historique | Member+ |
| **Fournisseurs** | CRUD fournisseurs | Member+ |
| **Livraisons** | Suivi des livraisons | Delivery+ |
| **Import/Export** | Import Excel de produits/clients/stocks | Manager+ |
| **Rapports** | CA, marges, stock | Viewer+ |
| **Paramètres** | Profil boutique, équipe, abonnement | Admin |

---

## Premier démarrage — liste de contrôle

### Étape 1 — Configurer l'espace (Admin)
- [ ] Se connecter avec le compte admin
- [ ] Vérifier le nom de l'entreprise et la devise dans **Paramètres > Entreprise**
- [ ] Créer au moins un entrepôt dans **Stock > Entrepôts**

### Étape 2 — Organiser le catalogue (Admin, Manager ou Member)
- [ ] Créer les catégories principales dans **Catalogue > Catégories**
- [ ] Ajouter les sous-catégories si nécessaire
- [ ] Créer les premiers produits dans **Catalogue > Produits**
- [ ] Imprimer et coller les étiquettes sur les produits (**Catalogue > Étiquettes**)

### Étape 3 — Initialiser le stock (Admin ou Manager)
- [ ] Pour chaque produit : aller dans **Stock**, cliquer **Entrée**, saisir la quantité initiale
- [ ] Vérifier que les seuils d'alerte sont corrects (5 unités par défaut)

### Étape 4 — Inviter l'équipe (Admin)
- [ ] Aller dans **Paramètres > Équipe**
- [ ] Inviter chaque employé avec le rôle approprié
- [ ] Communiquer les mots de passe temporaires à chacun

### Étape 5 — Tester le circuit complet
- [ ] Créer une commande test
- [ ] Enregistrer un paiement
- [ ] Vérifier que le stock a bien été décrémenté
- [ ] Consulter le tableau de bord

---

## Questions fréquentes

**Q : J'ai créé un produit dans le Catalogue mais il n'apparaît pas dans Stock.**
R : C'est normal. Le stock d'un produit n'existe que lorsqu'une première **Entrée de stock** est effectuée. Allez dans Stock, cherchez le produit, cliquez **Entrée**, saisissez la quantité. Seuls les rôles **Manager** et **Admin** peuvent faire cette opération.

**Q : Je clique sur "Entrée" dans le stock mais j'ai une erreur.**
R : Vérifiez votre rôle dans **Paramètres > Mon profil**. Seuls Manager et Admin peuvent modifier le stock. Si vous avez besoin de ces droits, contactez votre administrateur.

**Q : Comment changer le rôle d'un employé ?**
R : Allez dans **Paramètres > Équipe**, cliquez sur l'employé, puis sur **Modifier le rôle**. Seul un Admin peut changer les rôles.

**Q : Peut-on avoir plusieurs entrepôts ?**
R : Oui, selon votre plan d'abonnement. Allez dans **Stock > Entrepôts** pour en créer. Chaque entrepôt gère son propre stock et ses propres transferts.

---

## Support et aide

- **Documentation complète** : guides dans ce dossier
- **Email** : support@frynov.com
- **WhatsApp Business** : disponible depuis l'interface
