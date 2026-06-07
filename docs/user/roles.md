# Rôles & permissions

> **Pour les administrateurs.** Comment créer des rôles sur mesure et accorder à chaque
> membre exactement les droits dont il a besoin.

---

## À quoi servent les rôles ?

Un **rôle** regroupe un ensemble de **permissions** (créer un produit, ajuster le stock,
confirmer une commande…). Vous attribuez un rôle à chaque membre de votre équipe : il ne
peut faire que ce que son rôle autorise.

Frynov fournit des **rôles de base** prêts à l'emploi, et vous pouvez créer vos propres
**rôles personnalisés** pour coller à votre organisation.

### Les rôles de base (non modifiables)

| Rôle | Pour qui | Peut faire |
|---|---|---|
| **Admin** | Le ou les responsables | Tout, y compris la gestion de l'équipe, des rôles et de l'abonnement |
| **Manager** | Chefs d'équipe | Tout le quotidien (catalogue, stock, ventes, livraisons…) **sauf** gestion des utilisateurs, des rôles et de la facturation |
| **Membre** | Employés | Consulter, créer et modifier dans les modules opérationnels — **pas** de suppression, ni d'actions sensibles |
| **Lecteur** | Observateurs | Consultation uniquement |
| **Caissier / Agent / Commercial / Livreur** | Métiers terrain | Accès ciblés (caisse, ventes, livraisons…) |

Ces rôles sont partagés et **en lecture seule** : vous ne pouvez pas les modifier, mais
vous pouvez vous en inspirer en créant un rôle personnalisé.

---

## Créer un rôle personnalisé

1. Allez dans **Paramètres → Rôles** (onglet visible par les administrateurs).
2. Cliquez sur **« Créer un rôle »**.
3. Donnez-lui un **nom clair** (ex. *Responsable dépôt*, *Vendeur boutique*).
4. **Cochez les permissions** souhaitées. Elles sont **regroupées par module**
   (Catalogue, Stock, Commandes, Clients…) pour s'y retrouver facilement.
   - Le bouton **« Tout cocher »** d'un module sélectionne toutes ses permissions d'un coup.
5. Cliquez sur **« Créer le rôle »**.

> **Bon à savoir** — vous ne voyez que les permissions que **votre plan** autorise. Les
> permissions des modules optionnels (Livraisons, Fournisseurs, Import/Export, Rapports)
> n'apparaissent que si le module correspondant est **activé** pour votre espace. Les
> droits sensibles (gestion des administrateurs, de la facturation, des modules) ne sont
> jamais accordables à un rôle personnalisé.

### Modifier ou supprimer

- **Modifier** : sur la carte du rôle personnalisé, cliquez sur **« Modifier »**, ajustez
  le nom ou les permissions, puis enregistrez.
- **Supprimer** : cliquez sur **« Supprimer »**. ⚠️ Les membres qui portaient ce rôle
  perdent immédiatement les permissions associées.

---

## Attribuer un rôle à un membre

1. Allez dans **Paramètres → Équipe**.
2. Sur la ligne du membre, ouvrez le **menu déroulant de rôle**.
3. Choisissez un rôle de base **ou** un de vos rôles personnalisés (section
   **« Rôles personnalisés »**).

Le changement est immédiat. Vous pouvez aussi choisir un rôle (de base) dès
l'**invitation** d'un nouveau membre.

> Besoin d'un accès **temporaire** (ex. remplacement le temps d'un congé) ? Utilisez le
> bouton **« Accès temp. »** sur la ligne du membre : le rôle est retiré automatiquement à
> l'échéance, sans action de votre part.

---

## Exemple concret

**Objectif** : un employé gère le dépôt (réceptions, ajustements, transferts) mais ne doit
**pas** toucher aux prix produits ni aux commandes clients.

1. Créez un rôle **« Responsable dépôt »**.
2. Cochez, dans le module **Stock** : *Ajuster le stock*, *Réceptionner*, *Transférer*,
   *Auditer*.
3. Ne cochez **rien** dans Catalogue ni Commandes.
4. Attribuez **« Responsable dépôt »** à l'employé depuis l'onglet Équipe.

Résultat : l'employé peut réceptionner et ajuster le stock, mais toute tentative de créer
un produit ou de confirmer une commande lui sera **refusée**.
