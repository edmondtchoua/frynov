# Guide utilisateur — Commandes

Ce guide explique comment créer et gérer des commandes client dans ETech ERP.

---

## Comprendre les statuts

Une commande passe par 4 statuts possibles :

| Statut | Signification |
|--------|---------------|
| 🔘 Brouillon | Commande créée, stock non encore réservé |
| 🔵 Confirmée | Stock réservé, en attente de livraison |
| 🟢 Livrée | Marchandise remise au client, stock déduit |
| 🔴 Annulée | Commande annulée, stock libéré si nécessaire |

---

## Créer une commande

1. Cliquez sur **+ Nouvelle commande** dans la liste des commandes.
2. Ajoutez les articles en saisissant l'ID produit, la quantité, et optionnellement un prix personnalisé.
3. Ajoutez une note si nécessaire (informations de livraison, instructions spéciales…).
4. Cliquez sur **Créer la commande**.

> La commande est créée en **brouillon**. Le stock n'est pas encore impacté.

---

## Confirmer une commande

1. Ouvrez la commande depuis la liste.
2. Cliquez sur **Confirmer**.
3. Si le stock est insuffisant pour un article, un message d'erreur s'affiche avec la quantité disponible.

> Une fois confirmée, la quantité est **réservée** dans le stock — elle n'apparaît plus comme disponible pour d'autres commandes.

---

## Marquer une commande comme livrée

1. Ouvrez une commande **confirmée**.
2. Cliquez sur **Marquer livrée**.
3. L'horodatage de livraison est enregistré et le stock est définitivement déduit.

> Après livraison, la commande est verrouillée (aucune action n'est plus possible).

---

## Annuler une commande

1. Ouvrez une commande en **brouillon** ou **confirmée**.
2. Cliquez sur **Annuler**.
3. Si la commande était confirmée, les réservations de stock sont automatiquement libérées.

> Une commande **livrée** ou déjà **annulée** ne peut pas être annulée à nouveau.

---

## Filtrer les commandes

La liste des commandes offre maintenant 3 filtres: recherche texte (numéro/client), plage de dates, et statut.

Utilisez les onglets en haut de la liste pour filtrer par statut :
- **Toutes** — affiche toutes les commandes
- **Brouillons** — commandes non encore confirmées
- **Confirmées** — en attente de livraison
- **Livrées** — historique des ventes
- **Annulées** — commandes annulées

---

## Numérotation

Les commandes sont numérotées automatiquement par ordre de création : `ORD-00001`, `ORD-00002`, etc.  
Ce numéro est unique par boutique et peut servir de référence lors des échanges avec les clients.

---

## FAQ

**Q : Je veux modifier une commande déjà confirmée, c'est possible ?**  
R : Non, une fois confirmée, la commande est verrouillée pour garantir la cohérence du stock. Annulez-la et recréez une nouvelle commande.

**Q : Le stock n'a pas été déduit après livraison ?**  
R : Assurez-vous d'avoir cliqué sur **Marquer livrée**. La confirmation seule réserve le stock sans le déduire.

**Q : Je vois l'erreur "Stock insuffisant" lors de la confirmation ?**  
R : La quantité disponible est inférieure à ce qui est demandé. Consultez la fiche stock pour voir le stock disponible, puis ajustez la quantité ou réapprovisionnez.
