# Guide utilisateur — Notifications (email, SMS, WhatsApp)

> **Dernière mise à jour :** 2026-06-28 — RC-6A/6B + RC-7E (crédits).

Frynov peut prévenir automatiquement **vous** (rappels d'abonnement) et **vos clients** (livraison de
leurs achats digitaux : jeton d'accès, clé de licence). Tout se configure dans
**Paramètres → Notifications** (rôle Manager/Admin).

## 1. Configurer un canal d'envoi

Aucun message ne part tant qu'un **canal actif** n'existe pas. Trois types :

- **Email (SMTP)** — renseignez serveur, port, utilisateur, mot de passe, et l'adresse/nom d'expéditeur.
  Le mot de passe est **chiffré** et n'est jamais réaffiché ; laissez le champ vide lors d'une
  modification pour le conserver.
- **Proxy API (agrégateur)** — pour SMS/WhatsApp (ou passerelle email HTTP) : URL de l'API, en-têtes
  (ex. jeton d'autorisation) et corps de requête au format du fournisseur, avec variables (destinataire,
  message, expéditeur). Le **sender ID / numéro court** se met dans « Adresse d'envoi ».
- **Journal (test/dev)** — n'envoie rien, écrit dans les logs (pour valider la chaîne sans fournisseur).

Le bouton **Tester** envoie immédiatement un message de vérification au destinataire de votre choix.

## 2. Personnaliser les modèles

Onglet **Modèles** : chaque message (rappel d'échéance, abonnement échu, suspension, livraison
digitale) a un modèle **global** par défaut que vous pouvez **personnaliser** (sujet + corps). Les
variables entre doubles accolades sont remplacées à l'envoi (nom de l'entreprise, plan, date d'échéance,
nom du client, produit, jeton d'accès…).

## 3. Suivre les envois

Onglet **Journal** : chaque message avec son statut — **En attente** (départ sous 5 minutes),
**Envoyé**, **Échec** (après 3 tentatives ; le détail de l'erreur s'affiche au survol), **Crédit
épuisé** (voir ci-dessous).

## 4. Gérer vos crédits de communication

Onglet **Crédits** : chaque envoi sur un canal **facturé** (email, SMS, WhatsApp) consomme **un
crédit**. Vous voyez votre **solde par canal** et l'historique des mouvements.

- **À court de crédit ?** L'envoi passe en **« Crédit épuisé »** — il ne part pas, mais votre activité
  (ventes, commandes…) n'est **jamais** interrompue. Rechargez, les prochains envois repartent.
- **Recharger** : cliquez **Recharger** sur le canal voulu, choisissez un **pack** (ex. 1 000 SMS),
  indiquez la **référence de votre paiement** (reçu, transaction Mobile Money…). Dès que votre
  opérateur confirme l'encaissement, le solde est **crédité** et tracé dans les mouvements.
- Un envoi qui **échoue** (fournisseur indisponible) est **remboursé** automatiquement : vous ne payez
  que les messages réellement partis.

## Ce qui est envoyé automatiquement

| Quand | À qui |
|---|---|
| Abonnement payant à J-7 / J-3 / J-1 de l'échéance | Email de facturation de l'entreprise |
| Échéance dépassée (grâce 7 jours) puis suspension | idem |
| Livraison d'un achat **digital** (jeton + clé de licence) | Email du **client** (s'il est renseigné sur sa fiche) |
