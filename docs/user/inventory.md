# Guide utilisateur — Stock & Inventaire

> **Dernière mise à jour :** 2026-06-06 — filtre multi-sites sur les listes.

---

## Filtrer par entrepôt / site (multi-sites)

Si votre entreprise gère plusieurs entrepôts ou boutiques, un sélecteur **« Tous les entrepôts »** est disponible en haut des listes pour n'afficher que les données d'un site :

- **Stock & Inventaire** (onglet Stock) — quantités par entrepôt + résumé KPI du site sélectionné ;
- **Ventes → Commandes** — les commandes rattachées à l'entrepôt choisi ;
- **Ventes → Paiements** — les encaissements du site choisi.
- **Rapports → Ventes** — CA, top produits et méthodes de paiement du site choisi.
- **Rapports → Stock** — valeur de stock, ruptures, alertes et mouvements du site choisi.

Laisser le sélecteur sur « Tous les entrepôts » affiche l'activité consolidée. La liste des entrepôts se gère dans **Stock & Inventaire → Entrepôts**.

---

## Accès et droits requis

| Action | Rôle minimum |
|--------|-------------|
| Consulter le stock (lecture) | Tous les rôles connectés |
| Voir les alertes de stock bas | Tous les rôles connectés |
| Consulter l'historique des mouvements | Tous les rôles connectés |
| **Entrée de stock** (réceptionner des marchandises) | **Manager, Admin** |
| **Sortie de stock** (perte, casse, vente manuelle) | **Manager, Admin** |
| **Ajustement de stock** (inventaire physique) | **Manager, Admin** |
| Créer / modifier un entrepôt | **Manager, Admin** |
| Approuver une demande d'ajustement | **Manager, Admin** |
| Créer un transfert inter-entrepôt | **Manager, Admin** |
| Verrouiller une période fiscale | **Admin** uniquement |

> ℹ️ **Member, Viewer, Agent, Cashier** peuvent consulter le stock mais **ne peuvent pas** modifier les quantités.
> Les modifications de stock tentées par ces rôles renvoient une erreur `403 Accès refusé`.

---

## Pourquoi gérer le stock ?

Un stock bien suivi vous permet de :
- Savoir exactement combien d'articles vous avez en rayon
- Être alerté quand un produit est presque épuisé
- Ne jamais vendre un article que vous n'avez plus
- Connaître vos pertes (casse, vol) et ajuster vos commandes

---

## Accéder au stock

Menu principal → **Stock**

La liste affiche tous les produits qui ont été **au moins une fois approvisionnés**. Un produit nouvellement créé dans le catalogue n'apparaît ici qu'après sa première entrée de stock.

> ℹ️ **Nouveau produit invisible dans le stock ?** C'est normal. Le stock d'un produit est créé automatiquement lors de sa première entrée. Voir la section « Initialiser le stock d'un nouveau produit » ci-dessous.

---

## Initialiser le stock d'un nouveau produit

Après avoir créé un produit dans le **Catalogue**, il faut lui donner un stock initial.

**Depuis l'interface (Manager ou Admin) :**

1. Menu principal → **Stock**
2. Si le produit n'apparaît pas dans la liste, cliquez sur **Entrée de stock** dans la barre d'actions (ou allez directement sur la liste produits)
3. Cherchez le produit par nom ou SKU
4. Saisissez la quantité initiale reçue
5. Choisissez la raison : **Livraison** (ou **Stock initial** si c'est un premier approvisionnement)
6. Ajoutez optionnellement une référence (ex : `BL-2026-001`)
7. Cliquez sur **Valider**

Le produit apparaît maintenant dans la liste Stock avec la quantité saisie.

---

## Réceptionner une livraison fournisseur

Quand vous recevez de la marchandise :

### Méthode 1 — Produit par produit (depuis la liste Stock)

**Qui peut le faire :** Manager, Admin

1. Menu principal → **Stock**
2. Trouvez le produit dans la liste (utilisez la barre de recherche)
3. Cliquez sur le bouton **Entrée** (↑) sur la ligne du produit
4. Une fenêtre s'ouvre :
   - **Quantité** : nombre d'articles reçus (ex : `50`)
   - **Raison** : choisissez `Livraison`
   - **Référence** (optionnel) : numéro du bon de livraison (ex : `BL-2026-042`)
   - **Note** (optionnel) : remarque libre
5. Cliquez sur **Valider l'entrée**

Le stock est mis à jour immédiatement. Un mouvement est enregistré dans l'historique.

### Méthode 2 — Plusieurs produits d'un coup (livraison groupée)

**Qui peut le faire :** Tout utilisateur connecté (via `/api/inventory/deliveries`)

1. Menu principal → **Stock**
2. Cliquez sur **Réceptionner une livraison**
3. Ajoutez chaque produit de la livraison avec sa quantité
4. Renseignez le numéro de bon de livraison global
5. Cliquez sur **Valider la réception**

> 💡 **Conseil :** La méthode groupée est plus rapide pour les grosses livraisons multi-références.

---

## Enregistrer une sortie de stock

### Vente manuelle (hors commande)

Si vous vendez sans passer par le module Commandes :

**Qui peut le faire :** Manager, Admin

1. Dans la liste Stock, cliquez sur **Sortie** (↓) sur la ligne du produit
2. Saisissez la quantité
3. Choisissez la raison : **Vente**
4. Cliquez sur **Valider**

> En pratique, les commandes passées via le module **Commandes** déduisent le stock automatiquement — cette méthode est uniquement pour les ventes hors-système.

### Déclarer une perte ou une casse

**Qui peut le faire :** Manager, Admin

1. Dans la liste Stock, cliquez sur **Sortie** (↓) sur la ligne du produit
2. Saisissez la quantité perdue/cassée
3. Choisissez la raison : **Perte/Casse** ou **Perte** selon le cas
4. Ajoutez une note obligatoire expliquant la raison (ex : `Casse transport`, `Vol constaté`)
5. Cliquez sur **Valider**

> ⚠️ **Une note est obligatoire** pour tout ajustement ou sortie en perte/casse. Elle est conservée dans le journal d'audit.

---

## Faire un inventaire physique (ajustement)

L'inventaire consiste à compter physiquement vos articles et corriger le stock si nécessaire.

**Qui peut le faire :** Manager, Admin

1. Dans la liste Stock, cliquez sur **Ajuster** (✎) sur la ligne du produit
2. Saisissez la **quantité réelle comptée** (absolue, pas un delta)
3. Ajoutez une note obligatoire (ex : `Inventaire mensuel juin 2026`)
4. Le système calcule et affiche l'écart automatiquement (ex : `−3` si vous en trouvez moins qu'attendu)
5. Cliquez sur **Valider l'ajustement**

### Flux d'approbation (workflow dual)

Si votre espace de travail utilise le workflow d'approbation :
- Un **member** ou **agent** peut soumettre une *demande d'ajustement* (sans l'appliquer)
- Un **manager** ou **admin** doit l'approuver pour que l'ajustement soit appliqué
- Les demandes en attente apparaissent dans **Stock > Demandes d'ajustement**

> 💡 Ce workflow évite les modifications non autorisées du stock par des employés de terrain.

---

## Transferts inter-entrepôts

Si votre entreprise a plusieurs entrepôts, vous pouvez transférer du stock de l'un à l'autre.

**Qui peut le faire :** Manager, Admin

1. Menu principal → **Stock > Transferts**
2. Cliquez sur **Nouveau transfert**
3. Choisissez :
   - **Entrepôt source** (d'où part le stock)
   - **Entrepôt destination** (où arrive le stock)
4. Ajoutez les lignes du transfert (produit + quantité)
5. Cliquez sur **Créer le transfert**

**Cycle de vie :**
```
Brouillon → Expédié → Réceptionné → Terminé
                  ↘ Partiel → Litige → Résolu
```

- **Expédier** : déclenche la déduction du stock source
- **Réceptionner** : ajoute le stock à destination
- Si les quantités diffèrent : un litige est ouvert (résolution par Manager/Admin)

---

## Alertes de stock bas

Le système vous avertit automatiquement quand un produit passe en dessous de son **seuil d'alerte**.

- Les alertes apparaissent dans le **tableau de bord** et dans **Stock > Alertes**
- Seuil par défaut : **5 unités**
- Pour modifier le seuil : dans la liste Stock, cliquez sur le produit → champ **Seuil d'alerte**

---

## Historique des mouvements

Chaque entrée, sortie ou ajustement est enregistré de façon permanente avec :

| Donnée | Description |
|--------|-------------|
| Date et heure | Horodatage précis |
| Type | Entrée / Sortie / Ajustement |
| Quantité | Avant et après le mouvement |
| Raison | Livraison, Vente, Perte, Retour client, Transfert… |
| Référence | Numéro de bon, commande, transfert |
| Auteur | Nom de l'employé ayant effectué l'opération |
| CMUP au moment du mouvement | Coût moyen unitaire pondéré au moment de l'écriture |

> ⚠️ **L'historique est immuable** — il est impossible de modifier ou supprimer un mouvement enregistré.

**Pour consulter l'historique d'un produit :**
1. Menu principal → **Stock**
2. Cliquez sur le produit
3. Onglet **Mouvements**

---

## Périodes fiscales (Verrouillage comptable)

**Qui peut le faire :** Admin uniquement

Les périodes fiscales permettent de **clôturer une période comptable** (mensuelle, trimestrielle ou annuelle). Une fois verrouillée, aucune écriture de stock ne peut plus être faite sur cette période.

1. Menu principal → **Stock > Périodes fiscales**
2. Cliquez sur **+ Nouvelle période**
3. Définissez le nom, le type et les dates
4. Quand la période est terminée, cliquez sur **Verrouiller définitivement**
5. Saisissez une raison (ex : `Clôture exercice 2025 — validée par DAF`)
6. Confirmez

> ⚠️ **Le verrouillage est irréversible.** Aucun admin ne peut déverrouiller une période clôturée.

Pour vérifier l'intégrité d'une période verrouillée : cliquez sur **Vérifier intégrité** — le système recalcule le hash HMAC et confirme si la période n'a pas été altérée.

---

## Scan-to-action avec scanner Bluetooth / USB

Si vous avez un scanner de codes-barres :

| Mode | Action |
|------|--------|
| **Réception** | Ajoute 1 unité au stock du produit scanné |
| **Sortie** | Retire 1 unité |
| **Vérification** | Affiche le stock sans modifier |

Le scan fonctionne avec les codes-barres Code128 et les QR codes imprimés sur les étiquettes Frynov ERP.

---

## Questions fréquentes

**Q : Mon nouveau produit n'apparaît pas dans la liste Stock, pourquoi ?**
R : Un produit créé dans le Catalogue n'a pas de stock tant qu'il n'a pas eu de mouvement. Faites une **Entrée de stock** pour l'initialiser (Manager ou Admin requis).

**Q : Je vois le bouton "Entrée" mais j'ai une erreur en cliquant dessus.**
R : Seuls les rôles **Manager** et **Admin** peuvent enregistrer des mouvements de stock. Si vous avez un rôle Member, Viewer ou Agent, votre demande est refusée. Contactez votre administrateur pour changer votre rôle ou demander un ajustement.

**Q : Je ne peux pas sortir plus que ce qui est en stock ?**
R : Correct — le système bloque les sorties qui dépasseraient le stock disponible. Cela évite de vendre un article que vous n'avez pas en rayon.

**Q : Qu'est-ce que le "stock réservé" ?**
R : Quand une commande est passée mais pas encore expédiée, les articles sont "réservés". Le stock disponible = stock total − stock réservé.

**Q : Peut-on avoir un stock négatif ?**
R : Non. Le système interdit les sorties qui mettraient le stock à zéro ou en négatif. Une erreur `Stock insuffisant` est renvoyée.

**Q : Qu'est-ce que le CMUP ?**
R : Le **Coût Moyen Unitaire Pondéré** est la valeur comptable moyenne d'une unité en stock. Il est recalculé automatiquement à chaque entrée de stock, en tenant compte du prix d'achat de chaque livraison.
