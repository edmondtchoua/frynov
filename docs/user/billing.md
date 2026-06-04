# Facturation et abonnement

## Comparaison des plans

> Valeurs alignées sur `database/seeders/PlansSeeder.php` (source de vérité).
> `Illimité` = `null` en base ; `QuotaService` traite aussi `0` comme illimité par sécurité.

| Fonctionnalité        | Starter | Pro     | Enterprise |
|-----------------------|---------|---------|------------|
| Utilisateurs          | 3       | 15      | Illimité   |
| Produits              | 200     | 5 000   | Illimité   |
| Commandes / mois      | 100     | 2 000   | Illimité   |
| Entrepôts             | 1       | 3       | Illimité   |
| Agents terrain        | 3       | 10      | Illimité   |
| Prix (XOF/mois)       | Gratuit | 15 000  | Sur devis  |
| Import/Export         | Basique | Complet | Complet    |
| Support               | Email   | Email+Chat | Dédié   |
| API                   | Non     | Oui     | Oui        |
| Rapports avancés      | Non     | Oui     | Oui        |

## Paiement manuel

Le paiement manuel est disponible pour les marchés sans carte bancaire internationale (Mobile Money, virement bancaire local, etc.).

1. Aller dans **Paramètres > Facturation > Paiement manuel**
2. Sélectionner la méthode de paiement disponible dans votre pays
3. Effectuer le virement selon les instructions affichées
4. Téléverser la preuve de paiement (reçu, capture d'écran)
5. Un administrateur valide le paiement sous 24-48h ouvrables

## Mise à niveau de plan

1. Aller dans **Paramètres > Facturation > Mon plan**
2. Cliquer sur **Changer de plan**
3. Choisir le plan cible et la période (mensuel / annuel)
4. Confirmer le mode de paiement
5. L'upgrade est effectif immédiatement après validation du paiement

Le prorata est calculé automatiquement pour les jours restants du cycle en cours.

## Limites d'utilisation

- Les limites (produits, utilisateurs) sont vérifiées en temps réel.
- Un avertissement s'affiche à 80% de la limite atteinte.
- Au dépassement, les ajouts sont bloqués jusqu'à la mise à niveau ou la suppression d'entrées.
- Les données existantes restent accessibles même en cas de dépassement.

## Codes promotionnels

Saisir le code dans **Paramètres > Facturation > Code promo** avant de finaliser le paiement. Les promotions ne sont pas rétroactives.
