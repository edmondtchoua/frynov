# Cahier de recette — Frynov ERP v0.8.0

> Recette d'acceptation avant finalisation de la release `release/v0.8.0` → `main` + tag.
> Cocher chaque scénario. Toute anomalie bloquante = **NO-GO** jusqu'à correction.

---

## 0. Préparation de l'environnement

```bash
# Backend
cd backend
php artisan migrate:fresh --seed     # base de démo complète (tous modules MVP)
php artisan serve

# Frontend
cd frontend
npm install && npm run dev
```

### Comptes de démo (mot de passe : `Secret123!`)

| Tenant | Plan | Devise | Comptes |
|---|---|---|---|
| Boutique Afrik Style (Dakar) | Découverte / **trialing** | XOF | `admin@afrikstyle.sn` · `manager@…` · `membre@…` · `lecteur@…` |
| TechZone CI (Abidjan) | Croissance / active | XOF | `admin@techzone.ci` · `manager@…` · `vente1@…` · `vente2@…` |
| Grossiste Douala | Business / active | XAF | `admin@grossiste.cm` · `manager@…` · `stock1@…` · `audit@…` (viewer) |
| Super-admin plateforme | — | — | `superadmin@frynov.com` |

- [ ] `migrate:fresh --seed` s'exécute sans erreur
- [ ] 3 tenants, 14 utilisateurs, catalogue + commandes visibles

---

## 1. Authentification & Onboarding

- [ ] **Login** `admin@techzone.ci` → arrive sur le Dashboard
- [ ] 🐛 **Régression corrigée** : juste après login, le **menu Catalogue affiche les 5 onglets** (Produits · Catégories · Déclinaisons · Attributs · Étiquettes) — pas seulement « Produits »
- [ ] 🐛 **Régression corrigée** : **Paramètres → Abonnement** affiche le plan en cours (nom, statut, période), pas « Aucun abonnement actif »
- [ ] **Register** d'un nouveau compte → redirigé vers `/onboarding`
- [ ] Wizard onboarding : 6 étapes (activité, équipe, besoins `needs_*`, entreprise + devise, provisioning) → arrive sur un workspace utilisable
- [ ] Un tenant non-onboardé est **redirigé** vers `/onboarding`
- [ ] **Logout** → retour login ; session expirée (401) → redirection login

---

## 2. Tenant / Entreprise / Abonnement

- [ ] Paramètres → **Entreprise** : nom, pays, **devise**, téléphone, adresse → enregistrer → persiste après rechargement
- [ ] Paramètres → **Abonnement** : plan, statut (`trialing`/`active`), période, limites du plan
- [ ] Tenant `trialing` (Afrik Style) affiche les jours d'essai restants

---

## 3. Utilisateurs / Rôles / Permissions (RBAC)

- [ ] Paramètres → **Équipe** : liste des membres + rôles
- [ ] `admin`/`manager` : peut inviter, changer rôle, activer/désactiver un membre
- [ ] **`viewer`** (`lecteur@afrikstyle.sn`) : lecture seule — pas de boutons de création/suppression ; onglets catalogue réduits
- [ ] **Accès direct URL refusé** : un viewer qui force `/catalog/products/create` ne peut pas créer (403 backend)
- [ ] **Quota sièges** : sur le plan gratuit, inviter un 2ᵉ utilisateur → bloqué (402 « quota dépassé »). Sur un plan payant → autorisé

---

## 4. Catalogue produits

- [ ] **Produits** : liste paginée, recherche, prix formaté (centimes ÷100), badge nb déclinaisons
- [ ] **Créer un produit simple** : SKU auto, prix, catégorie, stock initial
- [ ] **Déclinaisons** : produit `DEMO-VAR-001` (T-shirt) → axes Couleur × Taille, variantes générées, prix/stock par variante
- [ ] **Attributs** : créer/associer un attribut depuis la fiche produit
- [ ] **Catégories** : CRUD, arborescence
- [ ] **Étiquettes** : génération QR / code-barres (impression thermique)
- [ ] Fiche produit : onglets (aperçu, déclinaisons, stock, prix), entrées de stock via drawers (tracées)

---

## 5. Clients

- [ ] Liste clients, recherche, fiche client avec historique commandes
- [ ] Créer / modifier un client ; suppression réservée `manager|admin`

---

## 6. Commandes / Ventes

- [ ] **Créer une commande** : recherche produit, sélection variante obligatoire pour produit variable, total live (prix résolu serveur)
- [ ] Cycle : `draft` → **confirmer** (réserve le stock) → **livrer/fulfill** (déstocke) ; **annuler** (libère le stock)
- [ ] Stock insuffisant à la confirmation → erreur claire, pas de mouvement fantôme
- [ ] **Retours / SAV** : sur une commande livrée → demander un retour, approuver, remettre en stock
- [ ] Montants affichés en unités majeures (÷100), devise du tenant

---

## 7. Stock / Inventaire

- [ ] **Stock** : liste par produit/variante, qté dispo, alerte « stock bas »
- [ ] **Mouvements** : historique (entrée initiale, ventes, etc.) tracé
- [ ] **Entrepôts** : entrepôt principal + (Grossiste) dépôt secondaire
- [ ] **Transfert inter-entrepôts** : transfert démo `TRF-*` (reçu) visible ; créer un transfert ship→receive
- [ ] **Ajustement de stock** : demande en attente → flux approuver/rejeter
- [ ] **Périodes fiscales** : période ouverte du mois ; clôture/verrouillage
- [ ] Réception de livraison fournisseur (lot) met à jour le stock + CMUP

---

## 8. Paiements / Encaissements

- [ ] **Enregistrer un paiement** sur une commande : montant, moyen (espèces/mobile money/carte/virement), référence
- [ ] Montant plafonné au solde restant ; double-paiement empêché (verrou)
- [ ] **Annuler (void)** un paiement : réservé `manager|admin`
- [ ] Liste des paiements : montants ÷100, lien vers la commande

---

## 9. POS / Caisse

- [ ] Menu **Caisse** visible (rôles admin/manager/caissier)
- [ ] **Ouvrir une session** avec fond de caisse
- [ ] **Encaisser** : recherche/scan produit, panier, déclinaisons, moyen de paiement → vente créée (commande payée + déstockée)
- [ ] **Clôturer** : compter les espèces → **écart** (attendu vs compté) affiché
- [ ] Session de démo clôturée (écart) + session ouverte visibles
- [ ] Un `viewer`/`delivery` est refusé (403)

---

## 10. Livraisons · Fournisseurs · Import/Export · Rapports

- [ ] **Livraisons** : cycle `pending → dispatched → delivered/failed`
- [ ] **Fournisseurs** : CRUD, code auto
- [ ] **Import** : session d'import terminée visible ; pipeline upload → mapping → approuver → exécuter (réservé `manager|admin`) ; export Excel/PDF
- [ ] **Rapports** : ventes & valeur de stock (réservé `manager+`)
- [ ] **Dashboard** : KPIs réels (CA, commandes, stock, clients), graphe

---

## 11. Marketplace · Promotions

- [ ] **Marketplace** : annonce de démo (plateforme, statut sync) ; alertes
- [ ] **Promotions** (super-admin) : codes `BIENVENUE20` (20%) et `LANCEMENT5000` valides

---

## 12. Admin back-office (super-admin)

> Login `superadmin@frynov.com` → reste cantonné à `/admin`.

- [ ] **Dashboard** plateforme : KPIs (tenants, users, modules, plans)
- [ ] **Tenants** : liste, détail, suspendre/réactiver, changer de plan
- [ ] **Plans** : éditer les limites (`plan_limits`) — **réservé super-admin** (un user tenant → 403)
- [ ] **Modules** : activer/désactiver par tenant
- [ ] **Promotions** : CRUD
- [ ] **Paiements manuels** : paiement en attente → approuver/rejeter
- [ ] **Audit** : journal `/admin/audit-logs`, vérification de chaîne (HMAC)

---

## 13. Transverses (sécurité, données, UX, perf)

- [ ] **Isolation multitenant** : connecté sur TechZone, impossible de voir/ouvrir une ressource de Grossiste (404)
- [ ] **Pricing localisé** : la landing publique affiche les prix dans la **devise du marché** (selon pays) ; `/api/public/pricing` répond
- [ ] **Géo RGPD** : aucune requête navigateur vers `ipapi.co` (vérifier l'onglet Réseau) — seulement `/api/public/geo`
- [ ] **Landing publique** : la page **scrolle** correctement (hero → tarifs → footer)
- [ ] **Monnaie** : aucun montant affiché ×100 trop grand ; XOF/XAF sans décimales
- [ ] **Responsive** : sidebar drawer mobile, tableaux scrollables
- [ ] **Erreurs** : 422 (validation), 403 (permission), 404 (introuvable) propres ; pas de stack trace exposée
- [ ] **Sync masqué** : `/api/syncs` renvoie 404 en prod (feature flag `FEATURE_SYNC` off)

---

## 14. Tests automatisés (pré-requis CI)

- [ ] Backend : `php artisan test` → **570+ verts**, 0 fatal, 0 incomplete
- [ ] Frontend : `npm run test:unit` → **155+ verts** ; `npm run type-check` propre
- [ ] `npm run build` (frontend) OK

---

## 🚦 Décision de recette

| | |
|---|---|
| **Date** | ____________ |
| **Recetteur** | ____________ |
| **Anomalies bloquantes** | ____________ |
| **Anomalies mineures (post-release)** | ____________ |
| **Décision** | ☐ GO release v0.8.0 ☐ GO conditionnel ☐ NO-GO |

> Après GO : finaliser GitFlow — `release/v0.8.0` → `main` + tag `v0.8.0` + merge back `develop`.
