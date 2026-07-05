# Guide utilisateur — Comptabilité (référentiel SYSCOHADA)

> **Dernière mise à jour :** 2026-07-05 · Périmètre actuel : plan comptable, journaux, taxes,
> exercices & périodes, paramètres. Les écritures, la facturation et les états financiers arrivent
> dans les prochaines versions.

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

## FAQ

**Q : Puis-je repartir de zéro après l'initialisation ?**
R : L'initialisation est idempotente : la relancer complète les éléments manquants sans écraser vos
personnalisations (libellés modifiés, comptes ajoutés).

**Q : Pourquoi ne puis-je pas désactiver le compte 571 ?**
R : C'est un compte *système* : les automatismes de caisse en dépendent. Vous pouvez en revanche
créer des sous-comptes (5711, 5712…) par point de vente.
