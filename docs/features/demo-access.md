# Accès de démonstration — données démo en production

Système permettant à un prospect de **demander une démo** via un formulaire public,
puis de recevoir par email un **accès de démonstration isolé, temporaire et jetable**,
sans jamais polluer les données des vrais tenants.

## 1. Diagnostic (audit de l'existant)

Avant implémentation, l'audit a établi :

| Brique | État initial |
|---|---|
| Isolation multi-tenant (`TenantScope` fail-closed, trait `HasTenant`, IDOR guard) | ✅ Robuste, testée |
| `DemoSeeder` (données démo dev) | ✅ Idempotent, isolé — mais **sans garde anti-prod ni flag `is_demo`** |
| Emails transactionnels (Mailables Auth : invitation, reset, 2FA) | ✅ Opérationnels |
| Notifications outbox (module Notifications) | ✅ Opérationnel |
| Formulaire de contact / demande de démo | ❌ **Inexistant** (lien footer mort) |
| Tenant démo `is_demo`, users démo, expiration | ❌ Absents |
| Back-office de gestion des demandes | ❌ Absent |

**Risque corrigé en priorité (P0)** : `DemoSeeder` pouvait être lancé en prod par erreur
(`php artisan db:seed`) et injecter 3 tenants fictifs dans la base réelle.

## 2. Architecture

- **Un tenant éphémère par prospect** (`tenants.is_demo = true`, `demo_expires_at`).
  Chaque prospect obtient son bac à sable jetable : données fictives, modifiable,
  détruit à l'expiration. L'isolation vis-à-vis des vrais tenants est garantie par le
  `TenantScope` existant (aucune modification du cœur d'isolation).
- **`demo_requests`** : objet *platform-level* (pas de `tenant_id`), géré par le
  super-admin. Machine à états :
  `new → pending_review → approved → demo_access_sent → (converted_to_customer | expired)`
  avec branche `rejected`.
- **Emails** : Mailables Laravel standard (comme les invitations Auth), car les emails
  démo sont pré-tenant / platform-level.

### Composants backend (module `App\Modules\Demo`)

| Élément | Rôle |
|---|---|
| `DemoRequest` (modèle + migration) | Demande de démo, statuts, cycle de vie |
| `DemoRequestController` (public) | `POST /api/demo-requests` — throttle + honeypot + validation + consentement |
| `AdminDemoRequestController` | Back-office super-admin (index/show/approve/resend/reject/expire/convert/notes) |
| `DemoProvisioningService` | Crée/détruit le tenant démo éphémère + données fictives |
| `DemoAccessService` | Orchestration `grant()` / `resend()` (provisioning + email + maj demande) |
| Mailables | `DemoRequestReceivedMail`, `DemoRequestInternalMail`, `DemoAccessMail`, `DemoReminderMail`, `DemoEndedMail` (FR/EN) |
| Commandes | `demo:revoke-expired` (horaire), `demo:send-reminders` (quotidien) |

### Composants frontend

- Page publique `/contact` (`ContactView.vue`) + lien footer landing.
- Back-office `/admin/demo-requests` (`DemoRequestListView.vue`) : KPIs, filtres, actions.
- Bannière **Mode démonstration** (`DemoModeBanner.vue`) affichée dans l'app quand
  `tenant.is_demo`, avec CTA « Activer un compte réel » → `/contact`.

## 3. Flux

### Manuel (défaut — `DEMO_MODE=manual`)
1. Prospect soumet le formulaire → `demo_requests` (statut `pending_review`).
2. Accusé de réception au prospect + notification interne à l'équipe.
3. Un admin approuve dans le back-office → provisioning du tenant démo + email d'accès
   (identifiant + mot de passe temporaire + lien de connexion + date de validité).
4. À l'expiration, `demo:revoke-expired` démonte le tenant + envoie l'email de fin.

### Automatique (`DEMO_MODE=auto`)
Identique, mais le provisioning + l'envoi des accès ont lieu immédiatement à la
soumission (protégé par throttle + honeypot).

## 4. Configuration (`config/demo.php` / `.env`)

| Variable | Défaut | Rôle |
|---|---|---|
| `DEMO_MODE` | `manual` | `manual` (validation admin) ou `auto` (envoi immédiat) |
| `DEMO_ACCESS_TTL_DAYS` | `14` | Durée de validité d'un accès démo |
| `DEMO_TEAM_EMAIL` | `MAIL_FROM_ADDRESS` | Adresse interne notifiée des nouvelles demandes |
| `DEMO_APP_URL` | `FRONTEND_URL` | Base URL front pour le lien de connexion |
| `DEMO_FORM_THROTTLE` | `5,1` | Throttle du formulaire public (`max,minutes`) |
| `DEMO_REMINDER_DAYS_BEFORE` | `3` | Rappel J-N avant expiration (`0` = désactivé) |

**Scheduler requis** (déjà câblé dans `routes/console.php`, nécessite le cron Laravel) :
`demo:revoke-expired` (horaire) et `demo:send-reminders` (quotidien 09:00).

## 5. Sécurité

- Isolation stricte par `tenant_id` (TenantScope fail-closed) — un compte démo ne voit
  jamais un vrai tenant, et inversement.
- Compte démo = rôle `admin` **de son propre tenant démo** uniquement ; jamais super-admin.
- Mot de passe temporaire **aléatoire**, jamais stocké en clair (hashé), renvoyable
  (nouveau mot de passe) via `resend`.
- Formulaire public : throttle dédié + honeypot + consentement obligatoire + validation.
- `DemoSeeder` **bloqué en production** (exception, override `DEMO_SEEDER_FORCE`).
- Toutes les actions back-office sont **auditées** (`AuditService` : `demo.access_granted`,
  `demo.access_resent`, `demo.rejected`, `demo.expired_manual`, `demo.expired_auto`,
  `demo.converted`).
- Statut d'envoi email tracé (`access_email_status`) ; envois best-effort (un échec
  d'email n'interrompt jamais l'enregistrement de la demande).

## 6. Guide back-office (utilisateur interne)

Menu **Admin → Demandes de démo** :
- **KPIs** : demandes, à traiter, démos actives, expirées, converties.
- **Filtrer** par statut / rechercher (email, entreprise).
- **Approuver** : provisionne le tenant démo et envoie les accès.
- **Renvoyer** : régénère le mot de passe et ré-expédie les accès.
- **Expirer** : révoque immédiatement (démonte le tenant démo).
- **Convertir** : marque la demande comme client gagné.
- **Rejeter** (avec motif) / **Notes internes**.

## 7. Tests (backend)

- `DemoRequestTest` — soumission publique, emails, honeypot, consentement, validation.
- `DemoProvisioningTest` — provisioning isolé + email d'accès, révocation, mode auto.
- `AdminDemoRequestTest` — 403 non-admin, liste+stats, approve (provisioning+email+audit),
  reject/convert/notes, `demo:revoke-expired` (teardown+email), `demo:send-reminders`
  (idempotent).

## 8. Couverture des critères d'acceptation

Soumission + email enregistré + consentement + notification interne + génération/envoi
d'accès + rattachement au tenant démo + isolation + données fictives + expiration +
renvoi + révocation + mode démo visible (bannière) + anti-spam (throttle/honeypot) +
seeders idempotents + conversion + logs d'audit + gestion d'erreur email — **couverts**.
