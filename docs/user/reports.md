# Rapports — Guide utilisateur

## Vue d'ensemble

Le module **Rapports** vous donne une vision chiffrée de votre activité :
- Le **tableau de bord** affiche les chiffres du jour en temps réel.
- Le **rapport des ventes** analyse votre chiffre d'affaires sur des périodes de 7 jours à 1 an.
- Le **rapport de stock** calcule la valeur de votre inventaire et identifie les produits à risque.

---

## Tableau de bord

Accessible depuis **Tableau de bord** dans la navigation.

### KPI Cards

| Indicateur | Source | Signification |
|-----------|--------|---------------|
| CA du jour | Paiements enregistrés aujourd'hui | Montant total encaissé |
| ↑↓ % vs hier | Comparaison jour précédent | Tendance (vert = hausse, rouge = baisse) |
| Commandes | Commandes non annulées du jour | Volume de l'activité |
| Produits actifs | Produits statut "actif" | Taille du catalogue |
| Alertes stock | Stock ≤ seuil d'alerte | Produits à réapprovisionner |

### Graphique CA

Affiche les 7 derniers jours. Les barres plus opaques = jours passés, la barre pleine = aujourd'hui.

### Commandes récentes

5 dernières commandes avec statut et client. Cliquez sur le numéro pour ouvrir la commande.

### Top produits

5 meilleurs produits en chiffre d'affaires sur les 30 derniers jours.

---

## Rapport des ventes

Accessible depuis **Rapport ventes** dans la navigation (ou depuis le tableau de bord).

### Sélecteur de période

Choisissez entre : **7J**, **30J**, **90J**, **1 an**. Les données se rechargent automatiquement.

### KPIs du rapport

| Indicateur | Description |
|-----------|-------------|
| CA total | Somme de tous les paiements sur la période |
| Paiements | Nombre de paiements enregistrés |
| Panier moyen | CA ÷ nombre de paiements |
| Méthode principale | La méthode de paiement la plus utilisée |

### Graphique d'évolution

Barres journalières sur toute la période sélectionnée. Utile pour identifier les tendances et les pics d'activité.

### Top produits

Tableau des 10 meilleurs produits avec quantité vendue et chiffre d'affaires. Basé sur les lignes des commandes NON annulées.

### Répartition par méthode de paiement

Barres horizontales montrant la part de chaque moyen de paiement (espèces, Mobile Money, carte, virement...).

---

## Rapport de stock

Accessible depuis **Rapport stock** dans la navigation.

### KPIs

| Indicateur | Description |
|-----------|-------------|
| Valeur du stock | Quantités × coût d'achat (ou prix de vente si pas de coût renseigné) |
| Références suivies | Nombre de SKUs avec un suivi de stock |
| En alerte | Produits dont le stock est ≤ au seuil d'alerte |
| En rupture | Produits avec quantité ≤ 0 |

> **Valeur stock :** Si vous avez renseigné un coût d'achat sur vos produits, c'est ce coût qui est utilisé. Sinon, le prix de vente est utilisé comme approximation.

### Produits en alerte

Liste des 10 produits les plus critiques, triés du plus urgent au moins urgent. La barre de progression montre le ratio stock actuel / seuil d'alerte.

**Couleurs :**
- 🟡 Jaune : entre 50% et 100% du seuil
- 🟠 Orange : moins de 50% du seuil
- 🔴 Rouge : rupture totale (quantité = 0)

Cliquez sur **Voir les alertes →** pour accéder à la page complète des alertes de stock.

### Mouvements récents (30 jours)

Résumé des opérations de stock sur le dernier mois, par type :
- **Entrées** : réceptions, réapprovisionnements
- **Sorties** : ventes, expéditions
- **Ajustements** : corrections d'inventaire
- **Retours** : retours fournisseurs ou clients

---

## Conseils d'utilisation

1. **Consultez le tableau de bord chaque matin** pour avoir les chiffres du jour avant.
2. **Utilisez la période 30J pour les décisions de réassort** — c'est le meilleur indicateur de rotation.
3. **Renseignez le coût d'achat sur vos produits** (Catalogue → fiche produit) pour que la valeur du stock soit précise.
4. **Configurez les seuils d'alerte** (Inventaire) pour que le rapport stock soit pertinent.
