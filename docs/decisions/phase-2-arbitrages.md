# Phase 2 — Plan d'arbitrages (à valider avant lancement)

> **Statut : EN ATTENTE D'INDICATIONS.** Ce document consolide toutes les questions qui demandent une
> décision fondateur, rencontrées pendant le build autonome RC-5C → RC-5K (rc.114 → rc.122). Chaque
> item liste le contexte, la question, les options (avec recommandation ⭐) et la taille estimée.
> Rien ici n'est lancé sans arbitrage ; tout le reste (défauts raisonnables) a déjà été livré.

## 🔔 A. Notifications sortantes (email / SMS / WhatsApp) — **prérequis transverse**

**Contexte.** RC-5J (relances billing) et RC-5I (livraison digitale) tracent tout en audit/metadata,
mais **aucun canal sortant n'existe** (pas de mailer configuré, pas d'agrégateur SMS). Le client ne
reçoit ni son lien de téléchargement, ni sa clé de licence, ni les rappels d'échéance — l'opérateur
doit les lui transmettre manuellement.

**Questions.**
1. Canal prioritaire pour le marché (Afrique de l'Ouest/Centrale) : email, SMS, WhatsApp Business ?
2. Fournisseur : SMTP simple (Resend/Mailgun/SES) ? agrégateur SMS local (ex. Orange SMS API, Termii) ?
3. Qui paie/possède les comptes d'envoi (par tenant ou plateforme) ?

**Options.** (a) Email d'abord (simple, gratuit en volume faible) ⭐ ; (b) SMS d'abord (taux de lecture
local supérieur, coût/SMS) ; (c) les deux derrière une abstraction `NotificationChannel`.
**Taille.** M (abstraction + 1 canal) ; chaque canal supplémentaire S.

## 🌐 B. Portail client digital (self-service)

**Contexte.** RC-5I livre le téléchargement par lien signé (hors auth), mais l'endpoint qui **révèle**
la clé + les liens (`GET /api/digital/access/{token}`) reste en contexte opérateur. Le client final n'a
pas d'espace à lui.

**Questions.**
1. Modèle d'accès client : (a) page publique « saisissez votre jeton » ⭐ (zéro compte, dépend de A pour
   envoyer le jeton) ; (b) lien magique envoyé par email/SMS ; (c) vrais comptes clients (login/mdp).
2. Le portail montre-t-il aussi garanties/SAV du client (suivi de réclamation) ?

**Taille.** M (option a) → L (option c). **Dépend de A.**

## 🧙 C. Assistant de création produit (wizard 5 questions)

**Contexte.** RC-5K expose la politique produit (type, suivi stock, livraison, garantie) dans le
formulaire. L'audit recommandait un **wizard** (« Que vendez-vous ? → stock ? → identifiant unique ? →
garantie ? → livraison ? ») pour guider un opérateur non technicien.

**Questions.**
1. Wizard plein écran à la création (remplace le formulaire) ou panneau « assistant » optionnel ⭐ ?
2. Faut-il masquer complètement les champs avancés (stock_tracking…) derrière l'assistant ?

**Taille.** M (front pur).

## 🧬 D. Caractéristiques spéciales dynamiques (`special_attribute_definitions`)

**Contexte.** Le sérialisé repose sur `serial_type` libre (imei/vin/serial/custom + normalisation par
défaut). L'audit cible des **définitions dynamiques** (numéro moteur, MAC, plaque, certificat…) avec
unicité/normalisation/scope configurables **sans code**.

**Questions.**
1. Quels attributs métier au-delà d'IMEI/VIN sont réellement attendus par les premiers clients ?
2. Scope V1 : unités d'inventaire seulement ⭐, ou aussi produit/variante/ligne de commande ?
3. Migration : convertit-on les `serial_type` existants en définitions seedées ?

**Taille.** L (migrations + refactor InventoryUnitService + UI de gestion). P2 de l'audit.

## 🔑 E. Pool de clés de licence importables

**Contexte.** RC-5E génère les clés à la volée (`XXXX-XXXX-XXXX-XXXX`). Pour revendre des licences
**éditeur** (clés pré-achetées), il faut un pool importable, consommé à la vente.

**Questions.**
1. Format d'import : CSV une clé/ligne ⭐ ? collage en masse ?
2. Pool épuisé au fulfill : bloquer la vente, ou fallback génération ⭐ + alerte ?

**Taille.** S/M.

## 🛡️ F. Garanties — extensions & granularité

**Contexte.** RC-5D/5F livrent politique → contrat → SAV. Restent :
1. **Extension payante** (produit « +12 mois » vendable, prolonge `ends_at`) — la vendre comme produit
   lié ? avec quel rattachement au contrat d'origine ?
2. **Durée** en mois uniquement — faut-il jours/années (denrées, immobilier) ?
3. Produit **agrégé** : un contrat **par ligne** aujourd'hui — passer **par exemplaire** (qty 3 → 3
   contrats) ⭐ ou rester par ligne ?

**Taille.** S (2, 3) ; M (1).

## 💳 G. Billing — reliquats de la revue RC-1/RC-2

Reportés (cf. `pricing-catalog-build.md` §🔭) — chacun demande une règle métier :
1. **`tenant_credits`** (table d'avoirs dédiée vs `metadata['overpaid_minor']`) — migrer les avoirs
   existants ?
2. **Cible nette après promo** (PromotionService) pour auto-activer les paiements `needs_review`.
3. **Sièges `extra_user`** dans le matching de périodicité.
4. **Mismatch devise ↔ moyen de paiement** strict → `needs_review` ?
5. **`applyDeposit`** en place (abondement) au lieu d'annuler/recréer l'abonnement par tranche.
6. Rétro-action d'un acompte imputé (rejet/remboursement).

**Taille.** S chacun, mais règles à trancher une par une.

## 📦 H. Chantiers structurels restants (hors produits spéciaux)

1. **Lots / péremption** (`stock_tracking=batch`) : la valeur existe, **aucune allocation par lot**
   n'est implémentée (FEFO, dates d'expiration, alertes péremption). **Taille L.**
2. **Kits / bundles** (`product_type=kit`) : pas de nomenclature ni de consommation de composants au
   fulfill. **Taille L.**
3. **Push origin / PR** : `release/v1.0.0` est en avance de ~16 commits locaux — quand pousser, et
   faut-il une PR de synthèse vers `master` ?

## Ordre recommandé (une fois arbitré)

| # | Item | Pré-requis | Taille |
|---|------|-----------|--------|
| 1 | A. Canal de notification | — | M |
| 2 | B. Portail client (option a) | A | M |
| 3 | E. Pool de licences | — | S/M |
| 4 | F. Garanties (durées + par exemplaire) | — | S |
| 5 | C. Wizard produit | — | M |
| 6 | G. Billing (1→6, dans l'ordre) | — | S×6 |
| 7 | D. Attributs dynamiques | — | L |
| 8 | H. Lots puis Kits | — | L+L |
