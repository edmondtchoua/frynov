# Guide utilisateur — Comptabilité (référentiel SYSCOHADA)

> **Dernière mise à jour :** 2026-07-06 · Périmètre actuel : plan comptable, journaux, taxes,
> exercices & périodes, paramètres, écritures & moteur d'imputation, **facturation client**,
> **avoirs**, **balance générale & grand livre**, **lettrage**, **rapprochement bancaire**, **clôture
> d'exercice**, **états financiers (bilan & compte de résultat)**. Module comptable complet.

## Accès et droits

| Action | Rôles |
|---|---|
| Consulter le référentiel | Comptable, Chef comptable, Lecteur comptable, Auditeur, Manager, Admin |
| Initialiser / modifier le référentiel, verrouiller une période | **Chef comptable**, Admin |
| **Rouvrir** une période verrouillée | Admin (permission dédiée `accounting.periods.reopen`) |

Le menu **Comptabilité** n'apparaît que si le module est actif dans votre abonnement.

## 1. Initialiser la comptabilité

Menu **Comptabilité → Plan comptable** → **Initialiser le référentiel**. Frynov crée en une fois :

- le **plan comptable SYSCOHADA** (38 comptes de base, enrichissable) — les comptes marqués
  *système* sont utilisés par les automatismes et ne peuvent pas être désactivés ;
- les **8 journaux** : Ventes (VT), Achats (AC), Caisse (CA), Banque (BQ), Opérations diverses (OD),
  Stock (ST), Avoirs (AV), Régularisations (RG) ;
- la **TVA de votre pays** (ex. 18 % UEMOA, 19,25 % Cameroun) — modifiable dans l'onglet Taxes ;
- l'**exercice en cours** avec ses **12 périodes mensuelles**.

L'opération est **rejouable sans risque** (aucun doublon).

## 2. Plan comptable

- **Recherche** par code ou libellé, **filtre par classe** (1 à 9).
- **Nouveau compte** : le 1er chiffre du code détermine automatiquement la classe
  (ex. `7011` → classe 7). Le code est définitif ; le libellé reste modifiable.

## 3. Taxes

Créez vos taux (saisis en %, ex. `18`). Une taxe *exclusive* s'ajoute au HT ; une taxe *incluse*
est extraite du TTC.

## 4. Exercices & périodes

- **Verrouiller** une période (fin de mois) : plus aucune écriture ne pourra y être datée.
- **Rouvrir** : action exceptionnelle — motif obligatoire, tracé dans le journal d'audit,
  réservée à un profil disposant de la permission dédiée.

## 5. Paramètres

- **Comptabilisation automatique** : les écritures générées par les ventes/paiements seront créées
  en *brouillon* (par défaut, un comptable valide) ou directement *comptabilisées*.
- **Comptes par défaut** : correspondance entre les opérations de l'ERP et vos comptes
  (Caisse → 571, Ventes → 701, TVA collectée → 4431…). C'est la base des écritures automatiques
  à venir (ventes caisse, paiements, retours…).

## 6. Factures

Menu **Comptabilité → Factures** :

1. **Nouvelle facture** : renseignez le client, les lignes (désignation, quantité, prix HT, remise %,
   taxe). Les totaux **HT / TVA / TTC se calculent en direct**. Enregistrez le brouillon.
2. **Émettre** : la facture reçoit un numéro `FA-…`, devient définitive et **génère automatiquement
   son écriture comptable** (débit client 411, crédit ventes 701 et TVA collectée 4431).
3. **Encaisser** : associez un paiement enregistré à la facture (montant partiel ou total). Le statut
   passe *Partiellement payée* puis *Payée*, et une **écriture d'encaissement** est produite.
4. **PDF** : téléchargez la facture au format PDF.

> Une facture émise n'est plus modifiable (comme une écriture comptabilisée) : une correction se fait
> par avoir (voir ci-dessous) ou par extourne de son écriture.

## 7. Avoirs (notes de crédit)

Menu **Comptabilité → Avoirs** — un avoir corrige ou rembourse une facture émise :

1. **Nouvel avoir** : choisissez la **facture d'origine** ; l'avoir en reprend les lignes.
2. **Émettre** : l'avoir reçoit un numéro `AV-`, et **génère l'écriture inverse** de la facture
   (débit ventes 701 et TVA 4431, crédit client 411).
3. **Appliquer** : imputez l'avoir à une facture émise pour en **réduire le reste dû**. Le montant est
   plafonné au reste de l'avoir et au reste dû de la facture ; vous pouvez l'appliquer en plusieurs
   fois. La facture passe *Partiellement payée* puis *Payée* une fois soldée.
4. **PDF** : téléchargez l'avoir.

> Le libellé du statut d'un avoir suit son application : *Émis → Partiellement appliqué → Appliqué*.

## 8. Balance & grand livre

Menu **Comptabilité → Balance** — vos états de lecture, calculés sur les écritures **comptabilisées** :

- **Balance générale** : la liste de vos comptes mouvementés avec, sur la période choisie (dates *Du*
  / *Au*), l'**à-nouveau**, les mouvements **débit** / **crédit** et le **solde**. La ligne de total
  affiche ✓ quand débits = crédits (contrôle d'équilibre).
- **Grand livre** : cliquez une ligne de compte pour ouvrir son détail — chaque écriture (date,
  journal, n°, libellé, débit, crédit) avec le **solde progressif**, l'à-nouveau et le solde final.

> Un solde négatif est **créditeur** (affiché avec un signe −), un solde positif est **débiteur**.
> Les brouillons non comptabilisés n'apparaissent pas ; une écriture extournée et son extourne
> figurent toutes deux (elles s'annulent).

## 9. Lettrage

Menu **Comptabilité → Lettrage** — pour un **compte de tiers** (client 411, fournisseur 401),
rapprochez ce qui se solde de ce qui reste dû :

1. **Choisissez le compte** (ex. `411 Clients`). Ses lignes s'affichent avec le **solde ouvert**.
2. **Cochez** les lignes qui se compensent (par ex. une facture au débit et son règlement au crédit).
   Quand la sélection est **équilibrée** (total débit = total crédit), le bouton **Lettrer** s'active.
3. **Lettrez** : les lignes reçoivent un même **code** (A, B, C…) et sortent du solde ouvert.
4. **Délettrer** : cliquez le badge de code (`A ×`) pour rouvrir le groupe.

> Le **solde ouvert** est ce qui n'est pas encore lettré : les factures non réglées et les règlements
> non affectés. Filtrez sur *Non lettrées seulement* pour ne voir que l'ouvert.

## 10. Clôture d'exercice

Menu **Comptabilité → Exercices & périodes** → bouton **Clôturer l'exercice** :

1. Frynov calcule le **résultat** de l'exercice (produits − charges) et le porte au compte **13**
   (bénéfice au crédit, perte au débit).
2. Une écriture de **report-à-nouveau** est générée à l'ouverture de l'**exercice suivant** : elle
   reprend les soldes de vos comptes de bilan (trésorerie, clients, fournisseurs, capitaux…) pour
   qu'ils repartent avec les bons soldes d'ouverture. L'exercice suivant est **créé automatiquement**
   s'il n'existe pas encore.
3. L'exercice clôturé et ses périodes passent en **Clos** : plus aucune écriture ne peut y être datée.

> La clôture est **définitive**. Un bandeau confirme le bénéfice/perte, le n° du report-à-nouveau et
> l'exercice suivant ouvert. Assurez-vous d'avoir saisi et comptabilisé toutes les écritures de
> l'exercice (les brouillons non comptabilisés ne sont pas repris) avant de clôturer.

## 11. États financiers

Menu **Comptabilité → États financiers** — vos deux états de synthèse, calculés en direct sur les
écritures comptabilisées (choisissez la période *Du* / *Au*) :

- **Bilan** : la photo de votre patrimoine à une date — l'**Actif** (ce que vous possédez : trésorerie,
  clients, stocks…) à gauche, le **Passif** (ce que vous devez + vos capitaux et le **résultat**) à
  droite. Un contrôle confirme que **Actif = Passif** (le bilan est toujours équilibré).
- **Compte de résultat** : vos **charges** (classe 6) face à vos **produits** (classe 7) sur la période,
  et le **résultat** (bénéfice si produits > charges, perte sinon) mis en évidence.

> Ces états se recalculent à chaque consultation : nul besoin de clôturer pour les voir. La clôture
> (section précédente) fige le résultat sur le compte 13 et ouvre l'exercice suivant.

## 12. Rapprochement bancaire

Menu **Comptabilité → Rapprochement** — vérifiez que votre comptabilité concorde avec votre relevé :

1. **Choisissez le compte de banque** (ex. `521 Banque`) et **saisissez le solde du relevé**.
2. **Cochez** chaque écriture qui figure sur le relevé (**pointage**). Les écritures **non cochées**
   sont les **en-cours** : les **dépôts en transit** (encaissés en compta, pas encore sur le relevé)
   et les **chèques en circulation** (émis, pas encore débités par la banque).
3. Le tableau de synthèse affiche l'**écart** : quand *solde comptable = solde relevé + dépôts en
   transit − chèques en circulation*, l'écart est **nul (✓)** — vos comptes sont rapprochés.

> Le pointage est conservé : une écriture pointée le reste. Un écart non nul signale soit un pointage
> incomplet, soit une opération manquante (frais bancaires, virement non enregistré…) à comptabiliser.

## FAQ

**Q : Puis-je repartir de zéro après l'initialisation ?**
R : L'initialisation est idempotente : la relancer complète les éléments manquants sans écraser vos
personnalisations (libellés modifiés, comptes ajoutés).

**Q : Pourquoi ne puis-je pas désactiver le compte 571 ?**
R : C'est un compte *système* : les automatismes de caisse en dépendent. Vous pouvez en revanche
créer des sous-comptes (5711, 5712…) par point de vente.
