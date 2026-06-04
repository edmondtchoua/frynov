# Facturation et abonnement

> **Dernière mise à jour :** 2026-06-04 — replanification pricing localisé.

## Principe de tarification cible

Frynov ERP évolue vers une tarification plus simple : **les modules métier principaux restent accessibles sur les plans publics**, tandis que les limites portent sur les ressources critiques.

Cela signifie que les plans ne doivent pas bloquer arbitrairement l'accès à tout un module comme Clients, Paiements, Livraisons ou Rapports. En revanche, chaque plan peut limiter :

- le nombre d'utilisateurs inclus ;
- les utilisateurs additionnels ;
- les produits / SKU ;
- les commandes mensuelles ;
- les clients ;
- les boutiques / branches ;
- les entrepôts ;
- les imports ;
- l'accès API ;
- la synchronisation marketplace ;
- le stockage ;
- le niveau de support.

## Plans cibles

> Les montants ci-dessous servent de structure produit. La source contractuelle finale devra provenir du backend et du pays de facturation sélectionné.

| Plan | Pour qui ? | Utilisateurs inclus | Limites indicatives | Support |
|---|---|---:|---|---|
| Découverte | Test, commerçant solo | 1 | 100 produits, 50 commandes/mois, 1 boutique, 1 entrepôt | Communauté / email standard |
| Essentiel | Boutique active | 2 | 500 produits, 300 commandes/mois, 1 000 clients | Email |
| Croissance | PME en expansion | 5 | 5 000 produits, 2 000 commandes/mois, 3 boutiques, 3 entrepôts | Prioritaire |
| Business / Enterprise | Grossistes, franchises, réseaux multi-sites | 10 ou contrat | Volumes élevés ou sur devis, API, SLA | Dédié |

## Devises et zones

La devise affichée doit correspondre au pays ou marché sélectionné :

| Zone | Devise |
|---|---|
| UEMOA — Sénégal, Côte d'Ivoire, Mali, Burkina Faso, Bénin, Togo, Niger, Guinée-Bissau | XOF |
| CEMAC — Cameroun, Gabon, Congo, Tchad, RCA, Guinée équatoriale | XAF |
| Nigeria | NGN |
| Ghana | GHS |
| Kenya | KES |
| Afrique du Sud | ZAR |
| Europe | EUR |
| Canada | CAD |
| USA / international fallback | USD |

Règles importantes :

- Un utilisateur au Canada ne doit pas voir des prix XOF/XAF par défaut.
- Un utilisateur en France ne doit pas voir des prix FCFA par défaut.
- XOF et XAF sont deux devises différentes et ne doivent pas être mélangées dans les données techniques.
- Un sélecteur manuel de pays/devise doit permettre de corriger une détection IP erronée.

## Paiement manuel

Le paiement manuel reste important pour les marchés où la carte bancaire internationale n'est pas le moyen principal.

1. Aller dans **Paramètres > Facturation > Paiement manuel**.
2. Sélectionner la méthode disponible dans votre pays.
3. Effectuer le paiement selon les instructions affichées.
4. Téléverser la preuve de paiement.
5. Un administrateur valide ou rejette le paiement.

## Mise à niveau de plan

1. Aller dans **Paramètres > Abonnement**.
2. Cliquer sur **Changer de plan**.
3. Vérifier la devise et le marché affichés.
4. Choisir le plan cible.
5. Confirmer le mode de paiement.

À terme, la page d'upgrade doit afficher les prix depuis la source backend officielle, pas depuis une grille hardcodée côté frontend.

## Limites d'utilisation

- Les limites sont vérifiées côté backend.
- Un avertissement doit être affiché avant saturation, idéalement autour de 80%.
- Au dépassement, l'ajout est bloqué jusqu'à la mise à niveau ou la réduction du volume.
- Les données existantes restent accessibles même en cas de dépassement.

## Codes promotionnels

Les codes promotionnels doivent rester compatibles avec les codes plans existants pendant la période de migration. Toute modification des codes (`starter`, `pro`, `enterprise`, `essential`) doit être accompagnée de tests sur les promotions et abonnements.
