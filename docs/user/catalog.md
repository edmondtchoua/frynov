# Guide utilisateur — Catalogue & Produits

## Accéder au catalogue

Depuis le menu principal, cliquez sur **Catalogue**.

---

## Catégories

Les catégories permettent d'organiser vos produits. Vous pouvez créer des catégories principales et des sous-catégories.

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

### Créer une catégorie

1. Allez dans **Catalogue > Catégories**
2. Cliquez sur **+ Nouvelle catégorie**
3. Entrez le nom (ex : `Boubous`)
4. Choisissez éventuellement une catégorie parente (ex : `Vêtements`)
5. Cliquez sur **Enregistrer**

---

## Produits

### Créer un produit

1. Allez dans **Catalogue > Produits**
2. Cliquez sur **+ Nouveau produit**
3. Remplissez les informations :

| Champ | Obligatoire | Description |
|-------|-------------|-------------|
| **Nom** | Oui | Ex : `Boubou Sénégalais` |
| **Catégorie** | Non | Pour organiser le catalogue |
| **SKU** | Non | Code article — généré automatiquement si vide |
| **Prix de vente** | Oui | En centimes dans votre monnaie (ex : `25000` pour 25 000 XOF) |
| **Prix barré** | Non | Ancien prix (affiche "PROMO" si supérieur au prix actuel) |
| **Prix d'achat** | Non | Votre coût — pour calculer la marge |
| **Description** | Non | Détails du produit |
| **Statut** | Oui | `Brouillon` (invisible) ou `Actif` (visible en vente) |

4. Cliquez sur **Enregistrer**

> **Astuce :** Le SKU est un code unique par produit (ex : `VET-0001`). Il sera encodé dans le QR code et le code-barres de l'étiquette. Si vous ne le renseignez pas, le système en génère un automatiquement.

### Modifier un produit

1. Dans la liste des produits, cliquez sur le produit à modifier
2. Modifiez les champs souhaités
3. Cliquez sur **Sauvegarder**

### Archiver un produit

Un produit archivé n'apparaît plus dans la liste de vente mais son historique est conservé.

1. Ouvrez le produit
2. Cliquez sur **Archiver le produit**
3. Confirmez

> Un produit archivé ne peut **pas** être supprimé — cela préserve l'historique des ventes et des mouvements de stock.

---

## Variantes produit

Les variantes servent quand un produit existe en plusieurs versions : tailles, couleurs, matières…

**Exemple :** un Boubou vendu en `Rouge/S`, `Rouge/L`, `Bleu/S`, `Bleu/L`.

### Ajouter des variantes

1. Ouvrez un produit
2. Cliquez sur **Ajouter des variantes**
3. Pour chaque variante, renseignez :
   - **Attributs** : ex `Couleur: Rouge`, `Taille: L`
   - **Prix** (optionnel — hérite du prix parent si vide)
   - **Stock initial**
4. Cliquez sur **Créer les variantes**

Chaque variante obtient son propre SKU (ex : `VET-0001-V1`, `VET-0001-V2`) et son propre code-barres.

---

## Codes QR et codes-barres

Chaque produit et chaque variante possède automatiquement :

- **Un QR code** — contient le SKU, l'identifiant et le nom du produit. Scannable avec l'app mobile pour accéder directement à la fiche produit.
- **Un code-barres Code128** — généré depuis le SKU. Utilisable avec les douchettes de caisse.

Pour voir ou télécharger les codes d'un produit :
1. Ouvrez la fiche produit
2. Cliquez sur **Codes & Étiquettes**
3. Vous pouvez afficher le QR seul, le code-barres seul, ou les deux
