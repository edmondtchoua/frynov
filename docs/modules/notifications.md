# Module Notifications (RC-6A/6B — Phase 2A)

> Notifications **sortantes** multi-canal (email / SMS / WhatsApp), entièrement **configurables par
> tenant** : canaux d'envoi, modèles de messages dynamiques, journal d'envoi (outbox + retry). SPA de
> configuration dans **Paramètres → Notifications**.

## Modèles de données

| Table | Rôle |
|---|---|
| `notification_channels` | Canal d'envoi par tenant : `channel` (email/sms/whatsapp), `provider` (`smtp` / `http_api` / `log`), `config` **chiffrée** (`encrypted:array` — credentials, URL, headers, payload), `from_name`/`from_address` (nom d'expéditeur, adresse d'envoi, sender ID, numéro court), `is_default` (un seul par type). |
| `notification_templates` | Modèle `{{placeholders}}` : `tenant_id` **null = global** (seedé FR), surcharge par tenant (résolution tenant → global). Unique (tenant, code, channel, locale). |
| `notification_outbox` | Message **rendu** : pending → sent/failed, `attempts` (max 3), `last_error`, journal consultable. |

## NotificationService

- **`notify(tenantId, templateCode, recipient, data, channelType='email')`** — résout canal par défaut
  actif + modèle (tenant → global), rend les placeholders, dépose en outbox. **Best-effort** : sans
  canal/modèle → `null`, jamais d'exception vers le flux métier appelant.
- **`flush(limit)`** — expédie l'outbox (cron `notifications:flush-outbox`, toutes les 5 min). Retry
  borné : 3 tentatives puis `failed` définitif.
- **`sendTest(channel, recipient)`** — envoi immédiat (bouton « Tester » de la SPA).
- Transports : **smtp** (mailer construit à la volée depuis la config chiffrée — `Mail::build`),
  **http_api** (POST générique : `url` + `headers` + `payload` template avec `{{to}}`, `{{message}}`,
  `{{subject}}`, `{{from}}`, `{{from_name}}` — branche n'importe quel agrégateur SMS/WhatsApp/email
  sans code), **log** (dev/recette).

## Émetteurs branchés

| Événement | Template | Destinataire |
|---|---|---|
| Rappel d'échéance (RC-5J) | `billing.renewal_reminder` | `tenant.settings['billing_email']` sinon email du 1er user |
| Échéance dépassée | `billing.renewal_overdue` | idem |
| Suspension après grâce | `billing.renewal_suspended` | idem |
| Vente digitale (fulfill) | `digital.delivery` (jeton + clé) | email du client (si connu) |

## Endpoints (`/api/notifications`, auth + tenant ; écritures manager/admin)

| Méthode + URL | Description |
|---|---|
| `GET /channels` · `POST /channels` · `PATCH /channels/{id}` · `DELETE /channels/{id}` | CRUD canaux. La config n'expose que ses **clés** (`config_keys`) — jamais les secrets ; un PATCH partiel **conserve** les secrets absents. |
| `POST /channels/{id}/test` | Envoi de test immédiat (`recipient`). |
| `GET /templates` | Fusion globaux + surcharges tenant. |
| `PUT /templates` | Crée/actualise la **surcharge** tenant (code+channel+locale). |
| `GET /outbox` | Journal paginé (`?status=`). |
| `GET /credits` *(RC-7E)* | Soldes par canal + packs de recharge + interrupteur/canaux facturés. |
| `GET /credits/movements` *(RC-7E)* | Journal des mouvements (`?channel=&limit=`). |
| `POST /credits/recharge` *(RC-7E, manager/admin)* | Applique un pack (`pack_code`, `payment_reference?`) — rail manuel. |
| `GET /credits/orders` *(RC-7F)* | Commandes de recharge Mobile Money du tenant. |
| `POST /credits/orders` *(RC-7F, manager/admin)* | Crée une commande payable (`pack_code`) → référence `RCH-…`. |
| `POST /credits/orders/{id}/cancel` *(RC-7F, manager/admin)* | Annule une commande **en attente**. |
| `POST /api/webhooks/mobile-money` *(RC-7F, public signé)* | Confirmation fournisseur → crédit automatique. |

## Crédits de communication rechargeables (RC-7E)

Chaque tenant dispose d'un **solde prépayé par canal** (email/SMS/WhatsApp). L'envoi sur un canal
**facturé** décompte 1 crédit ; à zéro l'envoi est **bloqué** (statut outbox `no_credit`) sans jamais
interrompre le flux métier appelant. Le tenant recharge en achetant un **pack** (rail de paiement
**manuel** : l'opérateur confirme l'encaissement, puis applique la recharge).

- **`communication_credits`** (tenant × canal → `balance`) + **`communication_credit_movements`**
  (journal append-only : `recharge` / `send` / `refund` / `adjustment`, `delta`, `balance_after`,
  `reference`, `meta`).
- **`CommunicationCreditService`** : `debit()` **atomique** (verrou de ligne, jamais négatif → `false`
  si insuffisant), `credit()`, `recharge(packCode)` (packs `config/notifications.php`), `balances()`,
  `isMetered()`.
- **Décompte dans `flush()`** — modèle **réservation puis remboursement** : on débite **avant** l'envoi
  et on **rembourse** si l'envoi échoue → aucun crédit perdu sur échec, aucun double décompte. Solde à
  zéro → `no_credit` (terminal, sans nouvelle tentative). Réglage `notifications.credits.enabled` +
  `metered_channels` (défaut : les 3 canaux facturés).
- **Agrégateur réel** : le transport `http_api` couvre déjà n'importe quel fournisseur. Presets fournis
  dans `config/notifications.php → aggregator_presets` (Termii, Orange SMS, WhatsApp Cloud) — brancher =
  créer un canal `http_api` avec le gabarit, renseigner la clé/le sender (aucune ligne de code).
- **SPA** : onglet **Paramètres → Notifications → Crédits** (soldes par canal, badge facturé/offert,
  bouton *Recharger* → choix du pack + réf. de paiement, journal des mouvements).

## Webhook Mobile Money (RC-7F) — recharge automatique

Flux : le manager crée une **commande de recharge** (`credit_recharge_orders`, pack figé → référence
payable unique `RCH-XXXXXXXX`) → le client paie via Mobile Money avec cette référence → le fournisseur
appelle **`POST /api/webhooks/mobile-money`** → la commande passe `paid` et le solde est crédité
(mouvement lié à la commande), **sans intervention de l'opérateur**.

- **Sécurité** : signature **HMAC-SHA256 du corps brut** (secret `MOMO_WEBHOOK_SECRET`, header
  `X-Webhook-Signature` configurable) comparée en **temps constant** ; secret absent → webhook
  **désactivé** (503, jamais de crédit non signé). Signature invalide → 401. Throttle 60/min.
- **Idempotence** : verrou de ligne sur la commande — un **replay** du webhook renvoie
  `already_processed` sans jamais créditer deux fois.
- **Montant strict** : montant/devise divergents → **aucun crédit**, commande **`needs_review`**
  (payload conservé en `meta` pour arbitrage opérateur). Statuts non finaux (`PENDING`…) ignorés.
- **Multi-fournisseurs sans code** : `notifications.mobile_money.field_map` mappe les champs du
  payload (chemins pointés) — s'adapte à Orange Money, Wave, MTN MoMo…
- **SPA** : la modale de recharge propose **Mobile Money (automatique)** — génère et affiche la
  référence payable — ou **encaissement manuel** (RC-7E) ; liste des commandes avec statut
  (en attente / payée / annulée / à vérifier) et annulation d'une commande en attente.
- Le rail **manuel** (RC-7E) reste disponible en secours.

## Tests — `NotificationTest` (8) + `CommunicationCreditTest` (12) + `RechargeWebhookTest` (10)

Rendu global → outbox · surcharge tenant prioritaire · sans canal = noop silencieux · flush http_api
(succès + 3 échecs → failed, **crédit consommé 1 / remboursé sur échec**) · secrets jamais exposés +
conservés au PATCH partiel · endpoint test · rappel RC-5J → outbox · vente digitale → email client ·
isolation tenant. **RC-7E** : débit/mouvement · débit insuffisant = `false` sans mouvement · recharge
pack → canal crédité · pack inconnu rejeté · flush débite 1/envoi · solde nul → `no_credit` (aucun
appel réseau) · échec → remboursement · API soldes/packs · recharge manager · pack inconnu 422 ·
isolation tenant. **RC-7F** : commande → référence payable · webhook signé → paid + crédit + mouvement
lié · replay = 1 seul crédit · signature invalide 401 sans crédit · secret absent 503 · référence
inconnue ignorée · montant divergent → `needs_review` sans crédit · statut non final ignoré ·
commande annulée non payable · liste isolée par tenant.

## Limites V1 / suite

- Rendu texte brut (pas de layout HTML riche) ; locale `fr` seule seedée.
- Envoi synchrone dans `flush` (pas de queue worker dédiée) — suffisant aux volumes actuels.
- Webhook Mobile Money : secret **unique plateforme** (pas de secret par fournisseur) ; pas
  d'expiration automatique des commandes en attente (annulation manuelle).
