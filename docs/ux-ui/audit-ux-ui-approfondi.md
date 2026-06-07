# Audit UX/UI approfondi — Frynov ERP

> Date : 2026-06-06  
> Périmètre : application Vue 3 / Laravel SaaS multitenant, espace tenant, back-office super-admin, onboarding, pricing, modules métier, composants partagés et design system global.  
> Objectif : fournir à l'agent d'implémentation une cartographie UX/UI actionnable, priorisée et vérifiable. Ce document n'applique pas encore les corrections d'interface.

---

## 1. Résumé exécutif

### Niveau UX/UI global : **moyen, avec dette élevée sur cohérence, accessibilité et workflows métier**

L'application dispose déjà d'une base fonctionnelle solide : navigation modulaire, layouts séparés app/admin/auth, design tokens CSS, composants partagés pour notifications/progress bar, onboarding multi-étapes et vues métier nombreuses. Cependant, l'expérience reste hétérogène et encore trop proche d'un assemblage de modules techniques :

- la navigation et les permissions sont partiellement pilotées par rôles et non par capacités/modules actifs ;
- plusieurs composants utilisent du HTML/SVG injecté (`v-html`) au lieu de composants d'icônes cohérents ;
- les états loading/error/empty ne sont pas uniformisés ;
- les tableaux et formulaires critiques manquent de conventions communes ;
- l'accessibilité clavier/screen reader est incomplète ;
- le responsive repose souvent sur des règles globales ou des classes ponctuelles ;
- les parcours métier longs (création produit, commande, onboarding, settings équipe) manquent de sauvegarde progressive, aide contextuelle, prévention d'erreur et confirmation d'actions critiques.

### Risque produit

| Risque | Niveau | Impact |
|---|---:|---|
| Abandon onboarding / activation | Élevé | Le wizard est long, plusieurs choix ne montrent pas clairement l'effet métier ou tarifaire. |
| Erreurs métier dans commandes/stock/paiements | Élevé | Les workflows critiques ne guident pas assez l'utilisateur et exposent des actions sans garde UX forte. |
| Perte de confiance admin/billing | Élevé | Prix fallback, preuves de paiement, états d'abonnement et modules ne sont pas assez explicites. |
| Accessibilité insuffisante | Élevé | Plusieurs contrôles n'ont pas d'état ARIA/focus/labels complets. |
| Dette UI scalable | Moyen à élevé | Beaucoup de styles inline, patterns modaux/tableaux propres à chaque vue. |

---

## 2. Cartographie UI existante

### Layouts et navigation

- `AppLayout.vue` fournit la sidebar tenant, topbar, avatar, notifications, bouton mobile et contenu principal.
- `AdminLayout.vue` fournit une sidebar back-office distincte avec ses propres icônes, libellés et styles.
- `AuthLayout.vue` encadre login/register.
- Les routes sont organisées par modules : dashboard, catalog, inventory, marketplace, orders, billing, customers, suppliers, import-export, reports, settings, profile, admin.

### Design system

- `main.css` contient des tokens globaux de couleur, radius, shadow, typographie, boutons, cards, badges, tables et formulaires.
- Les composants métiers redéfinissent souvent leurs propres classes et styles, ce qui dilue la cohérence.

### Composants transverses

- `NotificationCenter.vue` gère le centre de notifications et des toasts avec `aria-live`.
- `AppProgressBar.vue` fournit un indicateur de navigation/requête.
- Les tab navs métier existent pour catalogue, inventaire, ventes, rapports.

### Parcours principaux

- Landing et pricing public.
- Authentification et inscription.
- Onboarding en plusieurs étapes.
- Dashboard tenant.
- CRUD produits/catégories/variantes/labels.
- Commandes, paiements, retours, livraisons.
- Stock, entrepôts, transferts, clôtures.
- Settings entreprise, équipe, abonnement, intégrations.
- Back-office super-admin : tenants, modules, plans, promotions, paiements, audit.

---

## 3. Forces UX/UI constatées

| Force | Preuve | Valeur |
|---|---|---|
| Tokens globaux présents | `main.css` définit couleurs, radius, typography, shadows. | Base exploitable pour un design system. |
| Navigation app/admin séparée | `AppLayout.vue` et `AdminLayout.vue`. | Séparation mentale tenant vs plateforme. |
| Onboarding structuré | Wizard avec étapes activité/besoins/entreprise. | Bon support d'activation initiale. |
| Feedback de notifications | `NotificationCenter.vue` avec toasts et panel. | Visibilité des alertes marketplace/système. |
| Routes modulaires | `router/index.ts`. | Architecture UI alignée modules métier. |
| États vides existants | Plusieurs vues ont `empty-state` / `state-loading`. | Base pour standardisation. |

---

## 4. Problèmes UX/UI prioritaires

## UX-01 — Navigation tenant non alignée modules actifs / capacités

**Criticité : Haute**  
**Priorité : P0**  
**Zone : AppLayout, route guards, permissions, modules actifs**

### Constat

La sidebar tenant affiche les entrées à partir d'une liste locale `_allNavItems` filtrée principalement avec `managerOnly` et `isManagerOrAbove`. Les types utilisateur exposent pourtant `active_modules`, mais la navigation ne semble pas construire ses entrées depuis la source module active.

### Impact UX

- L'utilisateur peut voir une entrée qui finira en 403 côté API ou route.
- À l'inverse, un module activé peut être invisible si la logique locale ne le connaît pas.
- Les menus deviennent une seconde matrice de droits difficile à maintenir.

### Recommandation

- Construire la navigation depuis une configuration unique : route + module + permission + label + icon component.
- Croiser `active_modules` et permissions fines serveur.
- Afficher un état verrouillé/upgrade uniquement si la stratégie produit le demande, jamais comme seul contrôle sécurité.
- Ajouter une page “accès non disponible” contextualisée : module désactivé, permission manquante, quota atteint.

### Tests UX à ajouter

- Un module désactivé n'apparaît pas comme disponible dans la sidebar.
- Une permission manquante affiche un message explicite, pas une page vide.
- Les entrées sidebar correspondent à la matrice route/module.

---

## UX-02 — Incohérence app admin vs app tenant

**Criticité : Moyenne à haute**  
**Priorité : P1**  
**Zone : AdminLayout, AppLayout, navigation, branding**

### Constat

Le back-office possède son propre layout, ses propres icônes inline et ses libellés partiellement en anglais/français (`Tenants`, `Modules ERP`, `Plans & Tarifs`). Le lien “Retour à l'app” est visible même si les guards redirigent les super-admins hors des routes tenant.

### Impact UX

- Ambiguïté mentale : super-admin plateforme vs utilisateur tenant.
- Possibilité de navigation frustrante si un super-admin clique “Retour à l'app” puis est redirigé.
- Incohérence linguistique et visuelle.

### Recommandation

- Renommer “Tenants” en “Espaces clients” ou “Entreprises”.
- Remplacer “Retour à l'app” par une action claire selon rôle : “Vue plateforme” / “Changer d'espace” / masquer si non applicable.
- Harmoniser les composants sidebar/topbar entre app et admin avec variantes.

### Tests UX à ajouter

- Les super-admins ne voient pas d'action qui mène vers une route interdite.
- Les libellés admin sont tous en français ou suivent une convention bilingue documentée.

---

## UX-03 — Design system incomplet et styles inline fréquents

**Criticité : Moyenne**  
**Priorité : P1**  
**Zone : CSS global, vues métier, composants**

### Constat

`main.css` contient des tokens et composants globaux, mais de nombreuses vues utilisent des styles inline (`style="..."`) pour marges, couleurs, tailles, modales, états, etc. Les patterns table/card/modal sont réimplémentés localement.

### Impact UX

- Variations visuelles d'un module à l'autre.
- Corrections responsive/accessibilité difficiles.
- Risque de régression élevé à chaque nouvelle vue.

### Recommandation

Créer une couche de composants UI partagés :

- `AppButton`
- `AppCard`
- `AppTable`
- `AppEmptyState`
- `AppLoadingState`
- `AppErrorState`
- `AppModal`
- `AppTabs`
- `AppFormField`
- `AppConfirmDialog`

Puis interdire progressivement les styles inline hors cas calculés.

### Tests UX à ajouter

- Test statique : pas de `style="` dans les vues métier sauf allowlist.
- Snapshot visuel des composants UI partagés.

---

## UX-04 — Accessibilité clavier et ARIA insuffisante

**Criticité : Haute**  
**Priorité : P0**  
**Zone : navigation, modales, tables, tabs, toggles, notifications**

### Constat

Certains éléments ont des labels ARIA, par exemple la cloche de notification et les toasts. Mais les composants critiques restent incomplets : sidebar collapsed, onglets, modales, switches onboarding, boutons icônes, actions de ligne.

### Impact UX

- Navigation clavier difficile dans les modales et menus.
- Lecteurs d'écran peu informés des états actif/ouvert/sélectionné.
- Risque conformité WCAG 2.2 AA.

### Recommandation

- Ajouter `aria-expanded`, `aria-controls`, `aria-current`, `aria-selected`, `role="tablist"`, `role="tab"` selon les patterns.
- Remplacer les toggles custom par `<button role="switch" aria-checked="...">` ou composants accessibles.
- Ajouter focus trap et retour focus dans les modales.
- Ajouter `.sr-only` global.
- Ajouter styles `:focus-visible` globaux.

### Tests UX à ajouter

- Tests Vue Testing Library / Vitest : tabs navigables au clavier.
- Tests statiques : boutons icônes doivent avoir `aria-label` ou texte visible.
- Playwright + axe sur dashboard, onboarding, product form, order create, settings, admin modules.

---

## UX-05 — États loading/error/empty non standardisés

**Criticité : Moyenne**  
**Priorité : P1**  
**Zone : toutes vues métier**

### Constat

Les états existent mais avec des implémentations différentes : texte simple `Chargement…`, spinner, empty-state illustré, messages inline, etc. Les erreurs API sont souvent textuelles et non contextualisées.

### Impact UX

- L'utilisateur ne sait pas si l'action est en cours, échouée, ou si l'objet est réellement vide.
- Les erreurs critiques n'indiquent pas toujours la prochaine action.

### Recommandation

Standardiser 4 états :

1. `loading` avec skeleton ou spinner + libellé métier.
2. `empty` avec illustration, raison et CTA principal.
3. `error` avec message humain + bouton réessayer + détail optionnel.
4. `forbidden/locked` avec raison : permission, module, quota, abonnement.

### Tests UX à ajouter

- Chaque page list a loading, empty, error, forbidden state.
- Les erreurs API 403/402 ont une traduction produit.

---

## UX-06 — Tableaux métier peu adaptés aux écrans mobiles et gros volumes

**Criticité : Haute**

**Priorité : P1**

**Zone : produits, clients, commandes, paiements, stock, admin**

### Constat

`main.css` applique une technique responsive-table globale, mais les vues restent majoritairement table-first. Les colonnes sont masquées via classes comme `hide-mobile`, et les actions de ligne restent compactes.

### Impact UX

- Sur mobile, les données critiques sont masquées ou nécessitent un scroll horizontal.
- Les actions de ligne sont difficiles à toucher.
- Les gros volumes nécessitent recherche, tri, filtres, pagination et colonnes persistantes.

### Recommandation

- Créer un composant `ResponsiveDataList` : table desktop, cards mobile.
- Ajouter tri, filtres sauvegardés, densité, colonnes configurables.
- Prévoir actions groupées dans un bottom bar mobile.
- Afficher un compteur de filtres actifs et un bouton “Réinitialiser”.

### Tests UX à ajouter

- À largeur mobile, les listes clés rendent des cards sans scroll horizontal obligatoire.
- Les actions principales ont une cible tactile >= 44px.

---

## UX-07 — Formulaires longs sans prévention suffisante d'erreurs

**Criticité : Haute**

**Priorité : P1**

**Zone : ProductForm, OrderCreate, Settings, Onboarding, imports**

### Constat

Les formulaires contiennent beaucoup de champs, avec validations locales partielles et erreurs souvent non centralisées. Certains placeholders servent d'aide principale. Les actions destructrices ou coûteuses ne partagent pas un pattern de confirmation.

### Impact UX

- Erreurs métier fréquentes : SKU/prix/stock/quantités/devise.
- Abandon de formulaire.
- Perte de données si navigation accidentelle.

### Recommandation

- Composant `AppFormField` avec label, aide, erreur, obligatoire, format attendu.
- Résumé d'erreurs en haut du formulaire avec liens vers champs.
- Autosave brouillon pour formulaires longs.
- Guard `beforeRouteLeave` si formulaire dirty.
- Confirmation standardisée pour suppression, annulation commande, void payment, clôture période.

### Tests UX à ajouter

- Un champ invalide affiche erreur liée via `aria-describedby`.
- Navigation hors formulaire dirty demande confirmation.
- Les actions destructrices ouvrent un confirm dialog accessible.

---

## UX-08 — Onboarding trop déclaratif, pas assez orienté résultat

**Criticité : Haute**

**Priorité : P1**

**Zone : OnboardingView, activation, provisioning**

### Constat

L'onboarding demande activité, besoins, entreprise, devise et points de vente. Les switches besoins sont visuels, mais ne montrent pas clairement ce qui sera activé, créé ou facturé. Les sliders et choix métier n'ont pas toujours de conséquences visibles.

### Impact UX

- L'utilisateur ne comprend pas pourquoi on lui pose certaines questions.
- Les modules/quotas activés restent abstraits.
- Risque de mauvais paramétrage initial.

### Recommandation

- Ajouter un panneau “Ce qui sera configuré” persistant.
- Afficher modules, devise, entrepôt par défaut, paramètres document, équipe.
- Ajouter retour arrière non destructif et sauvegarde de progression.
- En fin d'onboarding : checklist de prochaines actions (ajouter produits, importer, créer commande, inviter équipe).

### Tests UX à ajouter

- Les besoins sélectionnés apparaissent dans le résumé final.
- On peut revenir à une étape sans perdre les données.
- Un onboarding échoué affiche récupération/retry.

---

## UX-09 — Pricing / upgrade : source backend + fallback pas assez explicites

**Criticité : Moyenne à haute**  
**Priorité : P1**  
**Zone : Landing, UpgradeView, BillingView**

### Constat

`UpgradeView` conserve un price book local comme fallback et charge aussi le pricing backend. C'est pragmatique mais peut générer des écarts de confiance si l'API échoue sans signal visible.

### Impact UX

- Le client peut voir un prix fallback non contractuel.
- Le support/billing peut recevoir des réclamations.
- Les devises XOF/XAF/NGN/etc. exigent une précision forte.

### Recommandation

- Afficher clairement “Prix synchronisés” ou “Prix indicatifs hors ligne”.
- Empêcher la soumission d'un paiement si le prix contractuel backend n'est pas confirmé.
- Ajouter une comparaison plan courant → plan cible avec impacts quotas.
- Pour enterprise : formulaire contact structuré au lieu d'un simple bouton.

### Tests UX à ajouter

- Si pricing API échoue, CTA paiement désactivé ou libellé “contacter support”.
- Le montant soumis correspond au plan backend, pas au fallback front.

---

## UX-10 — Notifications et feedback actions à renforcer

**Criticité : Moyenne**  
**Priorité : P2**  
**Zone : NotificationCenter, API client, formulaires**

### Constat

`NotificationCenter` fournit toasts et panneau. Les erreurs 403 globales dispatchent un événement `api:forbidden`, mais le routage produit de ces erreurs reste à clarifier. Les actions locales utilisent parfois messages inline, parfois aucun toast.

### Impact UX

- Feedback incohérent après création/update/delete.
- Les erreurs permission/module/quota peuvent être perçues comme bugs.

### Recommandation

- Créer un bus de feedback unifié : success, warning, error, forbidden, quota.
- Mapper 401/402/403/422/500 vers messages UX standards.
- Ajouter un historique récent des actions importantes.

### Tests UX à ajouter

- 402 quota affiche CTA upgrade.
- 403 permission affiche explication + contact admin.
- 422 validation met le focus au premier champ invalide.

---

## UX-11 — Iconographie et langage visuel non industrialisés

**Criticité : Moyenne**  
**Priorité : P2**  
**Zone : AppLayout, AdminLayout, ModuleList, Dashboard**

### Constat

Les icônes sont souvent définies comme chaînes SVG injectées via `v-html`, ou localement avec SVG inline. Cela nuit à la cohérence, l'accessibilité et la sécurité.

### Impact UX

- Icônes non uniformes.
- Impossible de gérer facilement taille, stroke, title/aria.
- Couplage fort entre données DB et rendu HTML.

### Recommandation

- Créer `AppIcon` avec une enum de noms autorisés.
- Stocker en base un `icon_key`, jamais un SVG brut rendu côté client.
- Définir tailles : 16, 20, 24, 32.
- Toutes les icônes décoratives : `aria-hidden="true"`.

### Tests UX à ajouter

- Les modules utilisent `icon_key` et non `icon_svg` côté rendu app.
- Aucun `v-html` sur les icônes module.

---

## UX-12 — Recherche, filtres et productivité opérateur à améliorer

**Criticité : Moyenne à haute**  
**Priorité : P2**  
**Zone : listes produits, clients, commandes, stock, paiements**

### Constat

Les filtres existent sur certaines vues, par exemple la liste produits. Mais l'expérience n'est pas homogène : debouncing local, pas toujours de persistance, pas de raccourcis clavier, pas de recherche globale.

### Impact UX

- Les utilisateurs terrain perdent du temps à retrouver produits/commandes/clients.
- Les filtres se réinitialisent trop facilement.

### Recommandation

- Ajouter recherche globale `Cmd/Ctrl+K`.
- Persister les filtres par module dans query string ou storage non sensible.
- Ajouter recherches récentes.
- Ajouter filtres rapides : “rupture”, “brouillons”, “à livrer”, “impayées”.

### Tests UX à ajouter

- Les filtres sont reflétés dans l'URL.
- Retour navigateur conserve la liste filtrée.

---

## UX-13 — Internationalisation et localisation incomplètes

**Criticité : Moyenne**  
**Priorité : P2**  
**Zone : textes UI, devises, dates, pays**

### Constat

L'application est majoritairement en français mais contient des statuts techniques (`active`, `beta`, `coming_soon`) et des libellés anglais (`Tenants`). Les pays/devises sont souvent hardcodés dans les composants.

### Impact UX

- Confusion pour utilisateurs non techniques.
- Localisation Afrique multi-pays difficile à maintenir.

### Recommandation

- Introduire dictionnaire i18n minimal : statuts, rôles, modules, erreurs.
- Centraliser pays/devises dans une source backend ou shared config.
- Toujours afficher code ISO devise + format local.

### Tests UX à ajouter

- Aucun statut technique brut dans les vues utilisateur.
- Les devises sont formatées via utilitaire unique.

---

## UX-14 — Routes et pages 403/404/402 non spécialisées

**Criticité : Moyenne**  
**Priorité : P2**  
**Zone : router, API interceptor, NotFoundView**

### Constat

Le route guard redirige login/onboarding/admin, mais il n'y a pas de pages dédiées pour : module désactivé, quota atteint, permission insuffisante, tenant suspendu, abonnement expiré.

### Impact UX

- Les erreurs sécurité deviennent des expériences bloquantes incomprises.
- Les utilisateurs contactent le support au lieu de comprendre l'action suivante.

### Recommandation

Créer des pages/états :

- `AccessDeniedView`
- `ModuleLockedView`
- `QuotaExceededView`
- `TenantSuspendedView`
- `SubscriptionPendingView`

### Tests UX à ajouter

- 402 renvoie vers `QuotaExceededView` avec ressource/usage/limite.
- 403 module renvoie vers `ModuleLockedView` avec nom module.

---

## UX-15 — Création catalogue trop lente sans duplication sûre ni wizard de complétion

**Criticité : Haute**

**Priorité : P1**

**Zone : ProductFormView, ProductListView, ProductShowPage, CategoryListView**

### Constat

Dans une gestion quotidienne, créer un produit proche d'un produit existant ne doit pas obliger l'utilisateur à ressaisir toutes les informations. Le besoin métier est de pouvoir dupliquer un produit “en tout point” tout en excluant automatiquement les champs uniques ou dangereux : SKU, codes-barres, GTIN, valeurs IMEI/VIN/licence uniques, stock, lots, unités sérialisées, garanties déjà émises, historiques et liens commande.

### Impact UX

- Saisie lente et répétitive pour les catalogues avec nombreuses variantes proches.
- Risque d'erreurs si l'utilisateur copie manuellement un produit et oublie de modifier SKU/code-barres/GTIN.
- Risque métier majeur si des identifiants unitaires, licences ou garanties émises sont clonés.
- Les catégories et référentiels associés peuvent aussi nécessiter une duplication guidée, mais avec code/slug/chemin unique recalculé.

### Recommandation

- Ajouter une action “Dupliquer” sur liste produit, fiche produit et éventuellement catégorie.
- Créer un wizard multi-étapes : aperçu de la source, choix des éléments à copier, champs uniques vidés/régénérés, champs obligatoires à compléter, récapitulatif final.
- Afficher clairement les champs qui ne seront jamais copiés : stock, mouvements, unités, IMEI/VIN/licences, garanties émises, audit, commandes.
- Laisser le serveur appliquer la politique de duplication, jamais uniquement le front.
- Proposer une duplication de catégorie qui copie parent/description/règles d'attributs mais vide ou régénère code/slug/chemin matérialisé.

### Tests UX à ajouter

- Dupliquer un produit affiche un brouillon avec champs non uniques préremplis.
- Les champs uniques sont vides ou marqués “à régénérer”.
- Le wizard bloque la validation tant que les champs obligatoires vidés ne sont pas complétés.
- Un utilisateur clavier peut compléter toutes les étapes et revenir sans perte de données.
- Dupliquer une catégorie ne réutilise pas le même slug/code unique.

---

## 5. Audit par parcours

### 5.1 Landing → register

**Points positifs**

- Positionnement public et pricing localisé déjà amorcés.
- Service public pricing séparé du client auth.

**Risques UX**

- Prix fallback côté front potentiellement différent du backend.
- Sélecteur marché/devise à rendre plus rassurant.
- CTA register doit conserver contexte pays/devise/plan.

**Priorités**

1. Transmettre plan + marché depuis landing vers register/onboarding.
2. Clarifier prix contractuel vs indicatif.
3. Ajouter FAQ paiement/devise.

### 5.2 Register/login

**Points positifs**

- Login simple.
- Redirection après auth via query `redirect`.

**Risques UX**

- Messages d'erreurs probablement techniques selon réponse API.
- Pas de MFA ou confiance appareil visible.
- Pas de récupération mot de passe observée dans les routes listées.

**Priorités**

1. Ajouter “mot de passe oublié”.
2. Ajouter affichage session expirée vs identifiants invalides.
3. Préparer MFA pour super-admin/admin.

### 5.3 Onboarding

**Points positifs**

- Wizard structuré.
- Capture besoins métier et paramètres entreprise.

**Risques UX**

- Long sans résumé persistant.
- Effet des besoins non matérialisé.
- Peut devenir frustrant si API provisioning échoue.

**Priorités**

1. Résumé latéral / final.
2. Sauvegarde progression.
3. Checklist post-onboarding.

### 5.4 Catalogue

**Points positifs**

- Liste produits avec recherche, filtres, sélection, impression batch.
- États empty/loading présents.

**Risques UX**

- Placeholder hacké avec espaces dans recherche.
- Actions batch icon-only partielles.
- Mobile table-first.

**Priorités**

1. Champ recherche avec icône positionnée CSS, sans espaces dans placeholder.
2. Cards mobiles.
3. Confirmation/descriptions pour impression batch massive.
4. Action “Dupliquer” avec wizard qui copie les champs utiles mais vide/régénère les champs uniques.

### 5.5 Commandes / POS / Paiements

**Points positifs**

- Routes dédiées création/ détail commande, paiement, POS.
- Paiement idempotent côté backend.

**Risques UX**

- Workflows financiers critiques sans pattern UX de confirmation homogène.
- Droits cashier/manager non suffisamment visibles côté UI.
- État impayé/partiel/payé à renforcer.

**Priorités**

1. Timeline commande.
2. Badge de solde paiement clair.
3. Confirm dialog accessible pour annulation/void/refund.

### 5.6 Inventory

**Points positifs**

- Modules stock, alertes, entrepôts, transferts, fiscal periods.

**Risques UX**

- Risque d'erreurs fortes sur mouvements stock.
- Besoin de différencier consultation vs action irréversible.

**Priorités**

1. Double confirmation pour ajustements importants.
2. Visualisation avant/après stock.
3. Historique contextualisé par produit/entrepôt.

### 5.7 Settings / équipe

**Points positifs**

- Tabs entreprise/équipe/abonnement.
- Gestion équipe et session timeout.

**Risques UX**

- Rôle et accès entrepôt à rendre plus compréhensibles.
- Effet de désactivation utilisateur et dernier admin à expliquer.

**Priorités**

1. Matrice rôle → permissions visible.
2. Dialog d'invitation avec explication du rôle.
3. Confirmation désactivation utilisateur.

### 5.8 Back-office admin

**Points positifs**

- Périmètre admin clair avec layout dédié.
- Vues modules/plans/promotions/audit.

**Risques UX**

- Actions plateforme critiques sans confirmation forte.
- Statuts techniques bruts.
- Navigation “Retour à l'app” ambiguë.

**Priorités**

1. Confirmations typed confirmation pour suspendre tenant/changer plan/désactiver module.
2. Journal audit intégré à chaque fiche tenant.
3. Libellés métier en français.

---

## 6. Matrice de remédiation priorisée

| Priorité | Chantier | Objectif | Fichiers cibles |
|---|---|---|---|
| P0 | Accessibilité navigation/modales/toggles | Clavier + screen reader utilisables | `AppLayout.vue`, `AdminLayout.vue`, tab navs, modales métier |
| P0 | Navigation modules/permissions | Sidebar alignée backend | `AppLayout.vue`, `usePermission.ts`, `router/guards.ts` |
| P1 | Design system composants | Réduire styles inline et variations | `main.css`, `shared/components/*`, vues métier |
| P1 | États standardisés | Loading/empty/error/forbidden cohérents | toutes vues list/detail/form |
| P1 | Formulaires critiques | Prévention erreurs + dirty guard | ProductForm, OrderCreate, Settings, Onboarding |
| P1 | Responsive data | Cards mobiles et actions tactiles | ProductList, OrderList, StockList, Admin lists |
| P2 | Pricing trust | Prix backend contractuel | Landing, UpgradeView, BillingView |
| P2 | I18n métier | Statuts/rôles/modules traduits | utils i18n ou dictionnaires |
| P2 | Recherche globale | Productivité opérateur | AppLayout/topbar + services recherche |

---

## 7. Checklist UX/UI avant production

### Accessibilité

- [ ] Focus visible global.
- [ ] Navigation clavier complète sidebar, tabs, modales.
- [ ] Tous les boutons icône ont nom accessible.
- [ ] Switches avec `role="switch"` et `aria-checked`.
- [ ] Modales avec focus trap, Escape, retour focus.
- [ ] États d'erreur liés aux champs par `aria-describedby`.
- [ ] Tests axe sur pages critiques.

### Cohérence visuelle

- [ ] Tous les boutons passent par `AppButton` ou classes standard.
- [ ] Tous les empty/loading/error states passent par composants partagés.
- [ ] Aucun statut technique brut visible.
- [ ] Icônes via `AppIcon`, pas HTML injecté.
- [ ] Pas de styles inline hors exceptions documentées.

### Parcours métier

- [ ] Onboarding avec résumé + reprise.
- [ ] Product/order/settings forms avec dirty guard.
- [ ] Actions critiques confirmées.
- [ ] Paiement/abonnement avec prix backend confirmé.
- [ ] Permission/module/quota expliqués en langage utilisateur.

### Responsive

- [ ] Toutes les listes critiques utilisables à 360px.
- [ ] Cibles tactiles >= 44px.
- [ ] Sidebar mobile ferme au changement route et Escape.
- [ ] Pas de scroll horizontal obligatoire hors tables explicitement scrollables.

### Observabilité UX

- [ ] Événements analytics : onboarding drop-off, upgrade click, import failure, form validation error.
- [ ] Feedback utilisateur après chaque action create/update/delete.
- [ ] Messages d'erreurs API harmonisés.

---

## 8. Tests recommandés pour l'agent d'implémentation

### Vitest / Testing Library

- `AppLayout.accessibility.spec.ts`
- `AdminLayout.accessibility.spec.ts`
- `OnboardingView.ux.spec.ts`
- `ProductFormView.validation.spec.ts`
- `ProductDuplicationWizard.spec.ts`
- `OrderCreateView.workflow.spec.ts`
- `SettingsTeam.roles.spec.ts`
- `ForbiddenStates.spec.ts`

### Playwright

- Login → onboarding → dashboard.
- Création produit mobile 390px.
- Création commande + paiement cashier.
- Admin suspend tenant avec confirmation.
- Navigation clavier sidebar + tabs + modal.

### Audit automatisé

- axe-core sur pages critiques.
- Test statique sans `v-html` dynamique.
- Test statique sur boutons icon-only sans label.
- Test statique styles inline avec allowlist.

---

## 9. Définition de terminé UX/UI

Une refonte UX/UI est validée uniquement si :

1. Les parcours P0/P1 sont testés en desktop et mobile.
2. Les états loading/empty/error/forbidden sont standardisés.
3. Les formulaires critiques empêchent la perte accidentelle.
4. Les actions destructrices ont une confirmation accessible.
5. Les permissions/modules/quotas sont expliqués côté UI et vérifiés côté serveur.
6. Les tests Vitest/Playwright/accessibilité passent.
7. La documentation utilisateur est mise à jour pour les workflows modifiés.
