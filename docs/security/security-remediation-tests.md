# Plan de validation sécurité — tests d'acceptation audit

> Objectif : ce document guide l'agent IA qui implémente les corrections de sécurité. Il ne doit pas considérer une correction comme terminée tant que les tests ci-dessous ne passent pas avec la suite existante.

## Commandes obligatoires

```bash
cd backend && php artisan test --filter=SecurityRemediationTest
cd backend && php artisan test --filter=ModuleGatingTest
cd frontend && npm run test:unit -- src/security/__tests__/frontendSecurity.spec.ts src/stores/__tests__/auth.spec.ts
cd backend && composer audit --locked
cd frontend && npm audit --omit=dev
```

## Tests ajoutés ou durcis

### Backend — `SecurityRemediationTest`

Fichier : `backend/app/Modules/Security/Tests/SecurityRemediationTest.php`.

Cette suite encode les exigences minimales issues de l'audit :

1. **Modules/plans fail-closed**
   - Chaque route sensible d'un module désactivé doit répondre `403` avec le code module.
   - Couverture : catalogue, inventaire, commandes, clients, paiements, livraisons, fournisseurs, import/export, rapports.
   - Un tenant sans aucune ligne `tenant_modules` doit aussi être refusé, jamais autorisé par défaut.

2. **RBAC serveur par permission métier**
   - Un `viewer` ne peut pas créer client, paiement ou commande, même si les modules sont actifs.
   - Un `cashier` peut créer un paiement mais ne peut pas annuler/supprimer un paiement.

3. **Hiérarchie des rôles workspace**
   - Un `manager` ne peut pas inviter un autre `manager`.
   - Un `manager` ne peut pas accorder temporairement le rôle `manager`.

4. **Isolation multitenant**
   - Une catégorie d'un tenant ne peut pas être rattachée à une catégorie parent d'un autre tenant.

5. **Uploads sensibles privés**
   - Les justificatifs de paiement ne doivent pas être stockés sur le disque public.
   - L'API ne doit pas exposer d'URL `/storage/...` publique pour les preuves de paiement.

6. **Audit trail vérifiable**
   - Une chaîne d'audit propre doit être vérifiée comme intègre par `/api/admin/audit-logs/verify-chain`.

### Backend — `ModuleGatingTest`

Fichier : `backend/app/Modules/Platform/Tests/Integration/ModuleGatingTest.php`.

Le test historique fail-open a été durci : un tenant non provisionné doit maintenant être **fail-closed**. L'agent doit donc modifier le middleware module et éventuellement les fixtures existantes pour créer explicitement les modules nécessaires dans les tests qui ne portent pas sur la sécurité.

### Frontend — `frontendSecurity.spec.ts`

Fichier : `frontend/src/security/__tests__/frontendSecurity.spec.ts`.

Ces tests statiques empêchent deux régressions critiques :

1. `auth_token` ne doit plus être lu/écrit dans `localStorage` ou `sessionStorage`.
2. Les SVG/HTML venant de données modules (`mod.icon_svg`) ne doivent plus être rendus via `v-html`.

### Frontend — `auth.spec.ts`

Fichier : `frontend/src/stores/__tests__/auth.spec.ts`.

Les attentes historiques ont été changées :

- `setToken()` doit conserver le token en mémoire réactive, pas le persister dans `localStorage`.
- Un ancien token trouvé dans `localStorage` doit être ignoré.

## Conseils d'implémentation pour faire passer les tests

### Modules/plans

- Ajouter `module:<code>` à toutes les routes métier optionnelles.
- Transformer `EnsureTenantHasModule` en fail-closed hors environnement de test très explicite, ou mieux : mettre à jour les fixtures de tests pour provisionner les modules requis.
- Ne jamais considérer l'UI comme contrôle d'accès.

### RBAC

- Utiliser les permissions existantes dans `RolesAndPermissionsSeeder` : `customers.create`, `payments.create`, `orders.create`, etc.
- Préférer `permission:*` ou des policies métier aux simples checks de rôles.
- Conserver les rôles comme groupes de permissions, pas comme contrôle métier unique.

### Workspace

- Centraliser une méthode du type `canAssignRole(User $actor, string $role): bool`.
- Autoriser `admin -> manager`, mais interdire `manager -> manager`.

### Multitenancy

- Tout `withoutTenantScope()` doit être accompagné d'un `where('tenant_id', ...)` ou d'un contexte super-admin prouvé.
- Valider `parent_id` de catégories avec l'appartenance au tenant courant.

### Uploads billing

- Stocker les preuves sur un disque privé.
- Remplacer `proof_url` public par un endpoint authentifié ou une URL signée courte.

### Frontend

- Remplacer les icônes module dynamiques par des composants ou une whitelist de noms d'icônes.
- Si un rendu HTML est indispensable, sanitizer côté serveur et côté client, mais les tests actuels ciblent la suppression de `v-html` sur les données modules.
- Migrer la stratégie de token vers mémoire + refresh contrôlé ou cookies `HttpOnly` selon l'architecture retenue.

## Définition de terminé

Une remédiation est validée uniquement si :

- tous les tests ajoutés passent ;
- les tests historiques restent verts ;
- les audits dépendances production restent sans vulnérabilités connues ;
- aucune correction ne déplace une règle de sécurité uniquement côté frontend.
