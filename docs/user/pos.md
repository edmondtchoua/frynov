# Guide utilisateur — Caisse (Point de vente)

> **Dernière mise à jour :** 2026-07-05

La **Caisse** (POS — *Point Of Sale*) permet d'encaisser des ventes au comptoir :
ouvrir une session de caisse avec un fond, enregistrer des ventes rapides (scan ou
recherche produit), encaisser, puis clôturer la caisse avec un rapprochement des espèces.

---

## Accès et droits requis

| Action | Rôle minimum |
|--------|-------------|
| Ouvrir / clôturer une session de caisse | **Cashier**, Manager, Admin |
| Encaisser une vente | **Cashier**, Manager, Admin |
| Consulter l'historique des sessions | **Cashier**, Manager, Admin |

> ℹ️ Les rôles **Viewer, Agent, Commercial, Delivery** n'ont pas accès à la caisse.
> Le menu **Caisse** reste visible mais toute opération est refusée côté serveur (403).

---

## 1. Ouvrir une session de caisse

Avant toute vente, le caissier ouvre une **session** :

1. Menu latéral → **Caisse**.
2. Saisir le **fond de caisse** (les espèces déjà présentes dans le tiroir en début de journée).
3. Cliquer **Ouvrir la caisse**.

> Un caissier ne peut avoir **qu'une seule session ouverte** à la fois. Il doit clôturer
> la session courante avant d'en ouvrir une nouvelle.

---

## 2. Enregistrer une vente

Une fois la session ouverte, l'écran de caisse s'affiche en deux zones :

### Ajouter des produits au panier
- **Recherche** : tapez le nom ou la référence (SKU) du produit, puis cliquez sur le résultat.
- **Scanner** : tapez/scannez un code-barres ou SKU exact et appuyez sur **Entrée** —
  le produit est ajouté directement au panier.
- **Produits à déclinaisons** : si le produit a des variantes (taille, couleur…), une
  fenêtre vous demande de **choisir la déclinaison** avant l'ajout.

### Ajuster le panier
- Boutons **+ / −** pour la quantité de chaque ligne (à 0, la ligne est retirée).
- Le **total** se met à jour en temps réel.

### Encaisser
1. Choisir le **moyen de paiement** : Espèces, Mobile Money ou Carte.
2. Cliquer **Encaisser**.

À l'encaissement, le système, de façon **atomique** :
- crée la commande et **résout les prix depuis le catalogue** (jamais depuis l'écran) ;
- **décrémente le stock** (la vente sort immédiatement du magasin) ;
- enregistre le/les **paiement(s)** couvrant le montant total ;
- rattache la vente à la session de caisse.

> ⚠️ Si le **stock est insuffisant**, la vente est **refusée et entièrement annulée** :
> aucun mouvement de stock ni paiement fantôme n'est créé.

### Paiement mixte (espèces + Mobile Money)

Un client peut régler **en plusieurs fois** dans la même vente — par exemple une partie en
espèces et le reste en Mobile Money (Wave, Orange Money…). Ajoutez un moyen de paiement par
ligne et répartissez les montants : **la somme doit être égale au total** de la vente, sinon
l'encaissement est refusé. Seule la **part en espèces** est ajoutée au fond de caisse attendu.

---

## 3. Mouvements de caisse (entrées / sorties d'espèces)

Pendant le service, de l'argent peut entrer ou sortir du tiroir **sans être une vente** :
appoint de monnaie, dépôt à la banque, petite dépense payée en espèces. Ces **mouvements**
sont enregistrés à part pour que le rapprochement reste juste :

- **Entrée (pay-in)** : ajout d'espèces au tiroir (ex. appoint de fond). → augmente l'attendu.
- **Sortie (pay-out)** : retrait d'espèces (ex. dépôt banque, dépense). → diminue l'attendu.

> ⚠️ Une **sortie ne peut pas dépasser** les espèces réellement présentes dans le tiroir.

L'attendu de caisse devient : **fond + ventes espèces + entrées − sorties.**

---

## 4. Rembourser une vente (retour au comptoir)

Pour rembourser un client au comptoir :

1. Retrouvez la vente, sélectionnez les **articles à rendre** et la quantité.
2. Indiquez l'**état** de chaque article (revendable ou non) et le **motif**.
3. Choisissez le **mode de remboursement** (espèces par défaut).

Le système, de façon atomique :
- crée et valide un **retour (RMA)** ;
- **réintègre en stock** les articles revendables ;
- **défait les artefacts liés** : unités sérialisées, garanties annulées, accès numériques révoqués ;
- si le remboursement est **en espèces**, enregistre une **sortie de caisse** du montant remboursé
  (l'argent quitte le tiroir). Un remboursement Mobile Money/Carte ne touche pas les espèces.

---

## 5. Imprimer le ticket de caisse

Après chaque vente, vous pouvez **imprimer le ticket** (format ticket 80 mm, compatible
imprimantes thermiques et imprimantes classiques) :

- **Caisse Desktop** : bouton **Imprimer le ticket** (ou touche **F7**) — un aperçu s'affiche,
  cliquez **Imprimer**. Tant qu'une nouvelle vente n'est pas passée, vous pouvez **réimprimer**
  le ticket de la dernière vente.
- **POS mobile** : bouton **Ticket** dans la barre du haut après une vente.

Le ticket comporte : le nom, l'adresse et le téléphone de votre boutique (Paramètres → Entreprise),
le numéro de vente, la date, le caissier, la caisse, le détail des articles, **tous les moyens de
paiement utilisés** (y compris un paiement mixte, avec la référence Mobile Money) et le total.

---

## 6. Clôturer la caisse (rapprochement)

En fin de service :

1. Cliquer **Clôturer la caisse**.
2. Le système affiche les **espèces attendues** = fond de caisse **+** ventes réglées en espèces
   **+** entrées **−** sorties (remboursements espèces, retraits).
   *(Les ventes Mobile Money / Carte ne gonflent pas les espèces attendues.)*
3. Saisir les **espèces réellement comptées** dans le tiroir.
4. L'**écart** s'affiche en direct :
   - **0** → caisse juste ✓
   - **positif** → surplus
   - **négatif** → manquant
5. Cliquer **Clôturer**.

La session passe en statut **clôturée** ; l'écart est enregistré pour audit. Le caissier
peut ensuite ouvrir une nouvelle session.

---

## Notes importantes

- **Montants** : tous les montants sont affichés en unité principale (ex. 4 200 XOF) mais
  stockés en **centimes** en interne (×100). Les devises XOF/XAF s'affichent sans décimales.
- **Devise** : celle configurée pour votre entreprise (Paramètres → Entreprise).
- **Traçabilité** : chaque vente caisse génère une commande **honorée** et un paiement liés
  à la session, et chaque ouverture/clôture est journalisée (audit trail : `pos.session.opened`,
  `pos.sale`, `pos.session.closed`).
- **Isolation** : une session de caisse n'est visible que dans votre espace (tenant). Un autre
  commerce ne peut ni la voir ni la clôturer.

---

## Récapitulatif des endpoints (référence technique)

| Méthode | Endpoint | Rôle |
|---|---|---|
| `GET`  | `/api/pos/sessions/current` | session ouverte du caissier |
| `POST` | `/api/pos/sessions` | ouvrir une session |
| `POST` | `/api/pos/sessions/{id}/checkout` | encaisser une vente (simple ou mixte) |
| `GET`  | `/api/pos/sessions/{id}/movements` | mouvements de caisse |
| `POST` | `/api/pos/sessions/{id}/cash-movement` | entrée / sortie d'espèces |
| `POST` | `/api/pos/sessions/{id}/refund` | rembourser une vente |
| `GET`  | `/api/pos/orders/{orderId}/receipt` | ticket de caisse (impression / réimpression) |
| `POST` | `/api/pos/sessions/{id}/close` | clôturer (rapprochement) |
| `GET`  | `/api/pos/sessions` | historique des sessions |
