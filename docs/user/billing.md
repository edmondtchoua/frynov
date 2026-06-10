# Facturation et abonnement

> **Dernière mise à jour :** 2026-06-06 — P4 : la page tarifs publique consomme la source backend.

## Tarifs localisés sur la page publique (P4 — livré)

La page d'accueil publique (`/`) affiche désormais les tarifs **dans la devise de votre pays**, et ces montants proviennent **du backend** (`GET /api/public/pricing`), jamais de valeurs codées en dur :

- Le pays est déduit du réseau (couche CDN/edge, sans appel tiers) puis, à défaut, de la langue du navigateur.
- Un **sélecteur « Tarifs pour »** permet de corriger manuellement le pays/la devise (utile derrière un VPN). Les prix se mettent à jour immédiatement.
- Exemples : un visiteur au **Canada** voit des prix en **CAD**, en **France** en **EUR**, au **Sénégal** en **XOF**, au **Cameroun** en **XAF** — jamais un XOF par défaut hors zone FCFA.
- Si l'API est momentanément injoignable, un repli local (déjà adapté à la devise) garde la page fonctionnelle.

**Écran d'upgrade connecté (P5)** : la page **Paramètres → Abonnement → Changer de plan** (`/billing/upgrade`) affiche les mêmes tarifs issus du backend, avec un sélecteur de devise. Votre **abonnement en cours** reste affiché dans sa devise de facturation (source `/api/me/subscription`).

> Les prix affichés restent indicatifs tant que le checkout local par marché (P6) n'est pas livré ; la finalisation contractuelle se fait à la souscription.

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

### Payer au mois, à l'année, ou en plusieurs fois (détection automatique)

Le système **reconnaît tout seul** ce que vous payez à partir du **montant** :

- **Paiement mensuel complet** → abonnement activé pour **1 mois**.
- **Paiement annuel complet** (≈ 10 mois, ~2 mois offerts) → abonnement activé pour **1 an**.
- **Acompte (paiement partiel)** → l'abonnement reste **en attente de solde** (« past due ») : l'accès
  s'ouvre **une fois le total atteint**. Vos versements **s'additionnent** ; le **reste à payer** est suivi.
  Quand le solde est atteint, l'abonnement s'active et la **période part de votre premier versement**.
- Une **petite tolérance** (±1 %) absorbe les arrondis de mobile money — un paiement plein n'est jamais
  pris à tort pour un trop-payé.
- Un **trop-perçu** éventuel est conservé en **avoir** sur votre compte.

> 💡 Vous pouvez préciser au paiement si vous visez le **mensuel** ou l'**annuel** (utile pour les
> acomptes) ; sinon le système déduit la périodicité du montant. Un paiement avec **code promo** ou en
> **devise non reconnue** est mis de côté pour **validation manuelle** par un administrateur.

## Mise à niveau de plan

1. Aller dans **Paramètres > Abonnement**.
2. Cliquer sur **Changer de plan**.
3. Vérifier la devise et le marché affichés.
4. Choisir le plan cible.
5. Confirmer le mode de paiement.

> 💰 **Reliquat (proration) à l'upgrade.** Si vous montez en gamme **avant la fin** de votre période
> payée, l'écran affiche directement sur chaque plan le **reliquat appliqué** (la valeur du temps non
> consommé de votre plan actuel) et le **montant net à payer** après déduction. Vous ne réglez que ce
> net — le crédit comble le reste. En cas de **downgrade** ou si le reliquat dépasse le coût, l'excédent
> devient un **avoir** déduit à votre prochaine échéance (jamais remboursé en espèces).

La source backend officielle pour préparer la landing et la page d'upgrade est maintenant l'API publique `GET /api/public/pricing`. La page frontend ne doit plus maintenir durablement une grille de prix séparée.

## Limites d'utilisation

- Les limites sont vérifiées côté backend.
- Un avertissement doit être affiché avant saturation, idéalement autour de 80%.
- Au dépassement, l'ajout est bloqué jusqu'à la mise à niveau ou la réduction du volume.
- Les données existantes restent accessibles même en cas de dépassement.

## Codes promotionnels

Les codes promotionnels doivent rester compatibles avec les codes plans existants pendant la période de migration. Toute modification des codes (`starter`, `pro`, `enterprise`, `essential`) doit être accompagnée de tests sur les promotions et abonnements.
