# Guide utilisateur — Catalogue & Produits

> **Dernière mise à jour :** 2026-06-01

---

## Accès et droits requis

| Action | Rôle minimum |
|--------|-------------|
| Consulter le catalogue | Tous les rôles connectés |
| Créer / modifier une catégorie | **Member**, Manager, Admin |
| Créer / modifier un produit | **Member**, Manager, Admin |
| Archiver un produit | Manager, **Admin** |
| Ajouter des variantes | **Member**, Manager, Admin |
| Générer QR code / code-barres | Tous les rôles connectés |
| Imprimer des étiquettes | Tous les rôles connectés |

> ℹ️ **Viewer, Agent, Cashier, Commercial** peuvent consulter le catalogue en lecture seule mais ne peuvent pas créer ni modifier.

---

## Catégories

Les catégories permettent d'organiser vos produits en arborescence à 2 niveaux (catégorie racine + sous-catégories).

**Exemples de structure :**
```
Vêtements
  └── Boubous
  └── Robes
  └── Pantalons
Tissus
  └── Bazin
  └── Wax
Chaussures
```

### Créer une catégorie — pas à pas

**Qui peut le faire :** Member, Manager, Admin

**Depuis l'interface :**

1. Menu principal → **Catalogue**
2. Onglet **Catégories** (en haut de la page Catalogue)
3. Cliquez sur le bouton **+ Nouvelle catégorie** (en haut à droite)
4. Une fenêtre modale s'ouvre :

| Champ | Obligatoire | Description |
|-------|-------------|-------------|
| **Nom** | ✅ Oui | Ex : `Boubous`, `Wax`, `Chaussures cuir` |
| **Catégorie parente** | Non | Pour créer une sous-catégorie — choisissez dans la liste |
| **Description** | Non | Texte libre pour décrire la catégorie |
| **Ordre d'affichage** | Non | Nombre entier — les catégories sont triées par ordre croissant |
| **Statut** | Non | Toggle **Active / Inactive** — une catégorie inactive n'est plus proposée à la création produit |

5. Cliquez sur **Créer**

La catégorie apparaît immédiatement dans la liste, indentée si elle a une catégorie parente.

### Modifier une catégorie

1. Dans la liste des catégories, cliquez sur **Éditer** en face de la catégorie
2. Modifiez les champs souhaités
3. Cliquez sur **Mettre à jour**

> ⚠️ **Modifier la catégorie parente** d'une catégorie recalcule automatiquement le chemin (`path`) et la profondeur (`depth`) de toute la sous-arborescence.

### Supprimer une catégorie

1. Dans la liste, cliquez sur **Supprimer**
2. Confirmez dans la fenêtre de confirmation

> ⚠️ La suppression est **irréversible**. Les produits associés à cette catégorie perdent leur catégorie mais ne sont pas supprimés.

---

## Produits

### Créer un produit — pas à pas

**Qui peut le faire :** Member, Manager, Admin

**Depuis l'interface :**

1. Menu principal → **Catalogue**
2. Liste des **Produits** (vue par défaut)
3. Cliquez sur **+ Nouveau produit**
4. Remplissez le formulaire :

| Champ | Obligatoire | Description |
|-------|-------------|-------------|
| **Nom** | ✅ | Ex : `Boubou Sénégalais` |
| **Catégorie** | Non | Choisissez dans la liste — crée un lien vers la catégorie |
| **SKU** | Non | Code article unique. Si vide, le système en génère un automatiquement (ex : `VET-0001`) |
| **Prix de vente** | ✅ | Montant en centimes (ex : `25000` = 25 000 XOF) |
| **Prix barré** | Non | Ancien prix — affiche "PROMO" si supérieur au prix de vente |
| **Prix d'achat** | Non | Votre coût d'achat — sert au calcul de marge dans les rapports |
| **Description** | Non | Texte libre |
| **Statut** | ✅ | `Brouillon` (non disponible à la vente) ou `Actif` |
| **A des variantes** | Non | Cochez si le produit existe en plusieurs versions (taille, couleur…) |

5. Cliquez sur **Enregistrer**

> ✅ **Important :** La création d'un produit ne crée **pas** automatiquement une ligne de stock. Pour qu'un produit apparaisse dans le module Stock, il faut effectuer sa première **entrée de stock** (voir le guide Stock).

### Types de produit et suivi de stock

Tout produit n'est pas un article physique à compter. Frynov distingue **ce que vous vendez** de **la façon dont le stock est suivi** :

| Type de produit | Suit du stock ? | Exemple |
|---|---|---|
| **Physique** (simple / à variantes) | Oui — quantité par entrepôt | Boubou, téléphone, pièce détachée |
| **Service** | **Non** — aucun stock | Installation, prestation, formation |
| **Produit digital** | **Non** — livraison immatérielle | Logiciel, ebook, licence *(livraison à venir)* |
| **Kit / lot** | Selon composition | Pack composé d'autres produits |

- Un **service** ou un **produit digital** est automatiquement marqué **non stockable** : aucune entrée de stock ne lui est demandée, et il ne fausse pas vos rapports de stock.
- Les produits physiques restent suivis comme aujourd'hui (quantité par entrepôt, voir le guide Stock).

> 🔜 Le suivi **par unité sérialisée** (un numéro IMEI par téléphone, un VIN par véhicule), les **garanties** et la **livraison digitale** arrivent dans les prochaines versions — cette base les prépare sans changer votre saisie actuelle.

### Modifier un produit

1. Dans la liste des produits, cliquez sur le produit
2. Modifiez les champs souhaités
3. Cliquez sur **Sauvegarder**

### Archiver un produit

Un produit archivé n'est plus disponible à la vente mais son historique est conservé.

**Qui peut le faire :** Manager, Admin

1. Ouvrez le produit
2. Cliquez sur **Archiver le produit**
3. Confirmez

> Un produit archivé ne peut pas être supprimé — cela préserve l'intégrité de l'historique des ventes et des mouvements de stock.

---

## Variantes produit

Les variantes servent quand un même produit existe en plusieurs versions : tailles, couleurs, matières…

**Exemple :** un Boubou vendu en `Rouge/S`, `Rouge/L`, `Bleu/S`, `Bleu/L`.

### Générer des variantes (constructeur d'axes N-dimensions)

**Qui peut le faire :** Member, Manager, Admin

Le constructeur génère **toutes les combinaisons** automatiquement (produit cartésien)
à partir des axes que vous définissez :

1. Ouvrez un produit en **modification** et activez **Variantes**
2. Définissez vos **axes de variation** (autant que nécessaire) :
   - Axe `Taille` → valeurs `S`, `M`, `L`
   - Axe `Couleur` → valeurs `Rouge`, `Bleu`
3. Le compteur affiche le nombre de combinaisons (ici 3 × 2 = **6 déclinaisons**)
4. Cliquez sur **Générer les déclinaisons**

Chaque déclinaison obtient automatiquement son propre **SKU** (`VET-0001-V1`, `VET-0001-V2`…),
son **code-barres**, et hérite du prix parent (modifiable par déclinaison).
Les axes alimentent aussi l'onglet **Attributs**.

> ⚠️ **Désactiver une déclinaison avec du stock** : une fenêtre vous demande quoi faire
> du stock restant — le *transférer au produit principal* ou le *sortir du stock*.

### Consulter un produit (fiche à onglets)

Cliquer sur un produit ouvre sa **fiche de consultation** (≠ formulaire d'édition) :
**Vue d'ensemble · Variantes · Stock · Prix**. Depuis cette fiche, des **actions rapides**
(drawers latéraux) permettent l'**entrée de stock** et l'**ajustement** — par produit
**ou par variante** (chaque variante a son propre stock).

### Entrée de stock en grille (produits à variantes) — RC-4

Pour les produits à variantes, le bouton **« Saisie en grille »** ouvre un **tableau**
**variantes × entrepôts** : une ligne par déclinaison, une colonne par entrepôt. Vous saisissez
**toutes les quantités reçues d'un coup** (plus besoin d'ouvrir un formulaire par variante), avec
un **coût d'achat unitaire** optionnel par ligne (qui alimente le CMUP). Un total « X unités à
ajouter » récapitule la saisie ; **Valider** crée tous les mouvements de réception en une fois.

> C'est la façon dont les meilleurs ERP e-commerce gèrent la réception multi-déclinaisons : rapide,
> visuel, sans répétition. Le stock de chaque cellule (variante/entrepôt) est mis à jour
> indépendamment.

---

## Codes QR et codes-barres

Chaque produit et chaque variante possède automatiquement :

- **Un QR code** — contient le SKU, l'identifiant et le nom du produit. Scannable avec un téléphone ou un scanner pour accéder directement à la fiche produit.
- **Un code-barres Code128** — généré depuis le SKU. Compatible avec les douchettes de caisse (USB ou Bluetooth).

**Pour voir ou télécharger les codes :**

1. Ouvrez la fiche produit
2. Cliquez sur **Codes & Étiquettes**
3. Choisissez : QR seul, code-barres seul, ou les deux

---

## Étiquettes imprimables

Frynov ERP génère des étiquettes prêtes à imprimer pour chaque produit ou variante.

**Formats disponibles :**
- **Thermique** — pour imprimantes à étiquettes (Zebra, Brother, etc.)
- **Feuille A4** — 8 étiquettes par page, pour imprimante standard

**Pour imprimer :**

1. Ouvrez la fiche produit
2. Cliquez sur **Imprimer l'étiquette**
3. Choisissez le format
4. Indiquez le nombre de copies
5. Imprimez depuis votre navigateur

**Pour imprimer en masse (plusieurs produits) :**

1. Allez dans **Catalogue > Étiquettes**
2. Sélectionnez les produits
3. Indiquez les quantités par produit
4. Cliquez sur **Générer les étiquettes**
