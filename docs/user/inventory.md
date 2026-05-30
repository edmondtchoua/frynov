# Guide utilisateur — Stock & Inventaire

## Pourquoi gérer le stock ?

Un stock bien suivi vous permet de :
- Savoir exactement combien d'articles vous avez en rayon
- Être alerté quand un produit est presque épuisé
- Ne jamais vendre un article que vous n'avez plus
- Connaître vos pertes (casse, vol) et ajuster vos commandes

---

## Accéder au stock

Depuis le menu principal, cliquez sur **Stock**.

Vous verrez la liste de tous vos produits avec leur quantité en stock, en temps réel.

---

## Réceptionner une livraison

Quand vous recevez de la marchandise d'un fournisseur :

### Méthode 1 — Saisie manuelle par produit

1. Allez dans **Stock > Entrées**
2. Cliquez sur **+ Nouvelle entrée**
3. Choisissez le produit
4. Saisissez la quantité reçue
5. Renseignez optionnellement le numéro du bon de livraison (ex : `BL-2026-001`)
6. Cliquez sur **Valider**

### Méthode 2 — Scanner les produits à la réception

Si vous avez une imprimante thermique et avez déjà collé les étiquettes :

1. Ouvrez l'application mobile ETech POS
2. Appuyez sur **Mode Réception** (icône carton)
3. Scannez chaque article avec le scanner Bluetooth
4. L'application incrémente automatiquement le stock
5. Appuyez sur **Terminer** pour valider tout

### Méthode 3 — Import par lot (toute une livraison)

1. Allez dans **Stock > Réceptions**
2. Cliquez sur **Importer une livraison**
3. Listez chaque article + quantité
4. Cliquez sur **Valider la réception**

---

## Enregistrer une vente manuelle

Si vous vendez sans passer par la caisse (POS) :

1. Allez dans **Stock > Sorties**
2. Choisissez le produit
3. Indiquez la quantité vendue
4. Sélectionnez la raison : **Vente**
5. Cliquez sur **Valider**

> **En pratique**, les ventes enregistrées via la caisse POS déduisent le stock automatiquement — pas besoin de le faire manuellement.

---

## Déclarer une perte ou une casse

Si un article est cassé, périmé ou perdu :

1. Allez dans **Stock > Sorties**
2. Choisissez le produit
3. Indiquez la quantité
4. Sélectionnez la raison : **Perte / Casse**
5. Ajoutez une note explicative si nécessaire
6. Cliquez sur **Valider**

---

## Faire un inventaire physique

L'inventaire consiste à compter physiquement vos articles et corriger le stock informatique si nécessaire.

1. Allez dans **Stock > Inventaire**
2. Cliquez sur **Nouveau comptage**
3. Pour chaque produit : entrez la quantité réelle comptée
4. Le système calcule automatiquement l'écart entre ce que vous avez saisi et ce qui était en stock
5. Cliquez sur **Valider l'inventaire**

> **Conseil :** Faites un inventaire complet chaque mois ou chaque trimestre, et un inventaire partiel (par catégorie) plus régulièrement.

---

## Alertes de stock bas

Le système vous avertit automatiquement quand un produit passe en dessous de son **seuil d'alerte** (5 unités par défaut).

- Les alertes apparaissent dans le tableau de bord et dans **Stock > Alertes**
- Pour modifier le seuil d'un produit : ouvrez la fiche produit → onglet **Stock** → modifiez **Seuil d'alerte**

---

## Scan-to-action avec le scanner Bluetooth

Si vous avez un scanner de codes-barres Bluetooth ou une douchette USB, vous pouvez :

| Geste | Action |
|-------|--------|
| Scan en mode **Réception** | Ajoute 1 unité au stock |
| Scan en mode **Vente (POS)** | Retire 1 unité du stock |
| Scan en mode **Vérification** | Affiche le stock sans modifier |

Le scan fonctionne aussi avec les QR codes imprimés sur les étiquettes.

---

## Historique des mouvements

Chaque entrée, sortie ou ajustement est enregistré avec :
- La date et l'heure
- La quantité et le type (entrée/sortie/ajustement)
- La raison (livraison, vente, perte…)
- La référence du document (bon de livraison, numéro de commande)
- L'employé ayant effectué l'opération

Pour consulter l'historique d'un produit :
1. Ouvrez la fiche produit
2. Cliquez sur l'onglet **Mouvements**

---

## Questions fréquentes

**Q : Je ne peux pas sortir plus que ce qui est en stock ?**
R : Correct — le système bloque les ventes qui dépasseraient le stock disponible. Cela évite de vendre un article que vous n'avez pas.

**Q : Qu'est-ce que le "stock réservé" ?**
R : Quand une commande est passée mais pas encore traitée, le stock est "réservé" (compté mais pas encore sorti). Le stock disponible = stock total − stock réservé.

**Q : Peut-on avoir un stock négatif ?**
R : Non. Le système interdit les sorties qui mettraient le stock à zéro ou en négatif.
