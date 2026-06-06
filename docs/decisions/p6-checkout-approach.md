# Décision P6 — Checkout & paiements locaux (à trancher avant code)

> **Statut : à décider.** Cette note compare les approches pour brancher un flux de
> paiement par marché. Règle produit (plan.md) : *chaque devise affichée doit
> correspondre à un flux de paiement OU à une mention « sur devis / paiement manuel ».*

## Le choix

| Critère | **A — Manuel / sur-demande** (s'appuie sur `ManualPayment` déjà livré) | **B — PSP réel** (Stripe / Flutterwave / Paystack / CinetPay) |
|---|---|---|
| Effort dev | **Faible** : réutilise `ManualPayment` + circuit admin approuver/rejeter + audit déjà en place | **Élevé** : intégration API, webhooks signés, réconciliation, gestion litiges/remboursements |
| Délai de mise en prod | **Immédiat** (compatible v1.0.0) | Semaines à mois (KYC + agrément marchand par pays) |
| Dépendances externes | **Aucune** | Comptes marchands, clés API, conformité PCI-DSS / 3DS, SLA prestataire |
| Couverture marché | **Tous** : instructions (virement / Mobile Money) + validation manuelle | Limitée aux pays/devises supportés par le PSP retenu |
| Afrique (XOF/XAF, NGN, GHS, KES) | MoMo / Wave / virement → preuve + validation admin | Flutterwave / Paystack / CinetPay (selon agrément pays) |
| Europe / Canada / USA | Virement / facture → validation admin | Stripe (carte, SEPA, Apple/Google Pay) |
| Risque | **Faible** (aucun flux financier automatisé) | Élevé (sécurité des paiements, fraude, chargebacks) |
| Réversibilité | **Élevée** | Faible (intégration profonde, données chez le PSP) |
| Expérience client | Acceptable (paiement asynchrone, activation après validation) | Optimale (paiement instantané, activation auto) |

## Recommandation pour v1.0.0

**Approche A (manuel / sur-demande) comme socle de lancement**, pour ces raisons :

1. **Ne bloque pas la v1.0.0** : le module `ManualPayment` (création, preuve, approbation/rejet
   admin, audit HMAC) est **déjà livré et testé** — il ne reste qu'à exposer côté client un
   parcours « demander l'activation / téléverser la preuve » par marché.
2. **Couvre 100 % des marchés** sans dépendre d'un agrément PSP pays par pays.
3. **Risque minimal** : aucun flux financier automatisé, donc pas de surface PCI/fraude pour le MVP.

**Post-1.0 (incrément B, par marché prioritaire)** : brancher un PSP là où le volume le justifie —
p. ex. **Flutterwave/Paystack pour l'UEMOA/Nigeria**, **Stripe pour l'Europe/Amérique du Nord** —
une fois les comptes marchands ouverts. Chaque branchement = un sprint isolé, avec webhooks signés
et tests de réconciliation, sans toucher au socle manuel.

## Décisions attendues du fondateur

- [ ] Valider **A** pour v1.0.0 (socle manuel) ? (recommandé)
- [ ] Si oui : quels **moyens affichés par marché** (Wave/OM/MoMo, virement, carte « bientôt ») ?
- [ ] Quel(s) **PSP cible** pour le post-1.0, et quel marché en premier ?

> Tant que cette note n'est pas tranchée, **aucun code de flux financier** n'est écrit. La v1.0.0
> peut sortir avec l'approche A (ou même « paiement manuel uniquement ») sans dépendance externe.
