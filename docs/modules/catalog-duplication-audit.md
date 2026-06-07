# Audit P1 — Duplication assistée sécurisée des produits et catégories

## 1. Résumé exécutif

L’existant ne contient **aucune fonctionnalité de duplication produit ou catégorie**. Les parcours actuels couvrent la création, l’édition, l’affichage, les variantes, les codes/étiquettes et les catégories, mais il n’existe pas de route, service, endpoint front ou composant wizard permettant de créer un produit similaire depuis un produit source.

La structure actuelle est exploitable pour une implémentation incrémentale, mais la duplication sûre ne doit pas être ajoutée par simple préremplissage frontend. Elle nécessite une **politique serveur dédiée** pour empêcher la copie de champs uniques, sensibles, opérationnels ou historiques même si le frontend est manipulé.

Décision d’audit : **refonte partielle recommandée, validation produit requise avant implémentation**. Une refonte complète du catalogue n’est pas nécessaire à ce stade, mais ajouter le wizard et les endpoints de duplication est structurant. Il faut valider les arbitrages UX/API avant de coder.

## 2. Cartographie de l’existant

### 2.1 Routes catalogue backend

Le module catalogue expose aujourd’hui :

- lecture produits : `GET /api/catalog/products`, `GET /api/catalog/products/{id}`, `GET /api/catalog/products/{id}/stock-summary` ;
- lookup SKU : `GET /api/catalog/products/sku/{sku}` ;
- création/édition/archive/activation produit ;
- stock initial produit ;
- génération/crud variantes ;
- catégories : liste, création, édition, suppression ;
- codes-barres, QR codes et étiquettes.

Les routes sont protégées par `auth:sanctum` et `EnsureUserBelongsToTenant`, puis les écritures sont limitées à `role:manager|admin`.

**Absent** : aucun endpoint `duplicate`, `clone`, `copy`, `preview` ou “produit similaire” n’existe pour les produits ou catégories.

### 2.2 Création produit backend

`CatalogController::store()` valide les champs de création produit et applique une unicité de `sku` par tenant. Les champs acceptés incluent notamment `name`, `sku`, `sku_prefix`, `description`, prix, `status`, `category_id`, `barcode`, `internal_barcode`, `gtin`, `barcode_type`, `weight_kg`, `metadata` et `has_variants`.

`CatalogService::createProduct()` centralise une partie métier importante :

- génère un SKU si absent ;
- génère un `internal_barcode` si absent ;
- valide le GTIN si présent ;
- crée le produit ;
- déclenche l’événement et l’audit `product.created`.

Point positif : la création réelle produit passe déjà par un service. C’est le bon point d’accroche pour une future politique de duplication, ou pour déléguer vers un `ProductDuplicationService`.

### 2.3 Édition produit backend

`CatalogController::update()` autorise une mise à jour partielle des champs descriptifs/prix/statut/catégorie/barcode/poids/metadata. En revanche, il ne valide pas explicitement `sku`, `internal_barcode`, `gtin`, `barcode_type` ou `has_variants` dans l’update. Le formulaire front peut envoyer certains champs, mais le backend ne les prend pas tous en compte.

Cette asymétrie est acceptable pour l’édition actuelle si elle est voulue, mais elle doit être clarifiée avant duplication : le wizard ne doit pas laisser croire que des champs seront persistés si l’endpoint final les ignore ou les régénère.

### 2.4 Affichage produit backend/frontend

La page détail utilise `GET /api/catalog/products/{id}?detail=1`, et `CatalogService::findProductDetail()` charge la catégorie, le fournisseur, les variantes et les attributs/valeurs. `CatalogResource` expose les identifiants catalogue (`sku`, `barcode`, `internal_barcode`, `gtin`), les prix, le type produit, la catégorie, le fournisseur, les variantes, le poids et les métadonnées.

La page `ProductShowPage` affiche le nom, le SKU, le type produit, le statut et des actions rapides comme entrée stock, ajustement et édition. Elle ne propose pas d’action “Dupliquer” ou “Créer un produit similaire”.

### 2.5 Variantes

Les variantes sont gérées par :

- `ProductVariantController::generate()` pour générer des combinaisons N-axes ;
- `CatalogService::createVariant()` pour créer une variante manuelle ;
- `ProductVariantResource` pour exposer SKU, label, attributs, prix, barcode et statut.

La génération de variantes contient déjà une logique anti-collision SKU qui tient compte des variantes soft-deleted. C’est un élément utile pour la duplication, mais la logique de génération/recréation de variantes est actuellement dispersée entre le contrôleur, le service et le formulaire front.

### 2.6 Catégories

`CategoryController` gère liste, création, édition et suppression. `CatalogService::createCategory()` génère automatiquement un slug si absent. `Category` maintient `depth` et `path` automatiquement à la création et lors des changements de parent.

Le front `CategoryListView` fournit une modale simple de création/édition/suppression. Il n’existe pas d’action de duplication catégorie.

### 2.7 Formulaire produit frontend

`ProductFormView` sert à la fois à créer et éditer un produit. Il gère :

- informations générales ;
- identifiants SKU/code-barres/GTIN ;
- prix ;
- catégorie ;
- stock initial ;
- variantes ;
- génération N-axes ;
- mouvements de stock initiaux après création.

Le formulaire peut être réutilisé partiellement pour la duplication, mais il est déjà dense. Un wizard complet intégré directement dans `ProductFormView` risquerait de rendre le composant plus fragile. L’option saine est de créer un composant dédié `ProductDuplicationWizard` et de réutiliser des sous-composants/composables du formulaire au fur et à mesure, sans refonte globale immédiate.

### 2.8 Service API frontend

`productService` couvre les appels produits, variantes, attributs, catégories, stock summary et impressions. Aucun appel `duplicatePreview`, `duplicate`, `categoryDuplicatePreview` ou `categoryDuplicate` n’existe.

### 2.9 Stock, lots et mouvements

Le stock est stocké dans `stocks` via `quantity` et `reserved_quantity`. Les mouvements sont dans `stock_movements`. Une migration `product_batches` existe avec `batch_number` et `serial_number`, mais cette fonctionnalité reste un scaffold partiel.

Conclusion duplication : le stock, les mouvements, les lots et les séries sont bien des données opérationnelles séparées du produit catalogue. La duplication ne doit jamais les copier.

### 2.10 Garanties, digital, licences, médias

L’audit précédent a déjà identifié que les garanties, les produits digitaux, les licences et les unités sérialisées ne sont pas encore modélisés de bout en bout comme fonctionnalités matures. La duplication doit donc prévoir une politique future-proof : copier uniquement les politiques/configurations générales quand elles existeront, jamais les contrats/entitlements/licences individuelles déjà émis.

### 2.11 Tests existants

Les tests catalogue couvrent création/liste/archive/lookup SKU, codes, labels, variantes, identifiants et sécurité catalogue. Aucun test ne couvre :

- preview duplication produit ;
- création produit depuis duplication ;
- duplication variantes ;
- duplication catégorie ;
- non-copie de stock/mouvements ;
- non-copie d’identifiants uniques ;
- rollback transactionnel d’une duplication.

## 3. État actuel demandé par l’exigence

| Point demandé | État actuel | Verdict |
| --- | --- | --- |
| Créer un produit | Présent via `POST /api/catalog/products` + `ProductFormView` | Conforme pour création standard |
| Éditer un produit | Présent via `PUT /api/catalog/products/{id}` + même formulaire | Partiellement conforme, certains identifiants ne sont pas update côté backend |
| Afficher un produit | Présent via `ProductShowPage` + `getDetail` | Conforme |
| Créer/éditer catégorie | Présent via `CategoryController` + `CategoryListView` | Conforme pour CRUD simple |
| Duplication produit | Aucun endpoint/service/UI | Absent |
| Duplication catégorie | Aucun endpoint/service/UI | Absent |
| Formulaire réutilisable pour wizard | Réutilisable partiellement, mais composant déjà dense | Partiellement conforme / à risque |
| Logique métier centralisée | Produit partiellement centralisé dans `CatalogService`; variantes et stock partiellement ailleurs | Partiellement conforme |
| Duplication existante sûre | Aucune duplication existante | Non applicable, fonctionnalité absente |

## 4. Risques détectés

| Risque | Niveau | Analyse |
| --- | --- | --- |
| Duplication de SKU produit | Critique | `products` impose unicité tenant/SKU ; copier tel quel échouerait ou créerait collision si contrôle contourné. |
| Duplication de SKU variante | Critique | `product_variants` impose unicité tenant/SKU ; les variantes doivent être recréées sans SKU source ou avec régénération serveur. |
| Duplication de code-barres interne | Critique | `internal_barcode` est unique par tenant ; il doit être vidé ou régénéré. |
| Duplication de barcode externe | Élevé | Pas d’unicité DB évidente sur `barcode`, mais métier/étiquettes/scans peuvent devenir incohérents. |
| Duplication de GTIN | Élevé | GTIN valide ne signifie pas unique ; il peut être partagé par modèle commercial ou non selon politique. Arbitrage requis. |
| Duplication de stock réel | Critique | Le front crée des mouvements de stock initiaux après création ; le wizard doit forcer stock à 0/non copié. |
| Duplication de lots/séries | Critique | `product_batches` contient batch/serial ; ne doit jamais être cloné vers un nouveau produit. |
| Duplication de mouvements d’inventaire | Critique | Mouvements = historique opérationnel/audit stock, jamais copiable. |
| Duplication garanties émises | Critique | Les garanties émises sont rattachées à ventes passées, jamais clonables. |
| Duplication licences digitales individuelles | Critique | Les clés/licences/entitlements individuels ne doivent jamais être copiés. |
| Duplication audit trail/logs | Critique | Il faut journaliser une nouvelle action de duplication, pas copier l’historique source. |
| Logique uniquement frontend | Critique | Un utilisateur pourrait POSTer SKU/stock/serial source si la politique n’est pas côté serveur. |
| Incohérence produit/variantes/stock | Élevé | Le formulaire actuel mélange produit, variantes et mouvements initiaux ; la duplication doit dissocier catalogue et stock. |
| Cross-tenant source/relations | Critique | Les endpoints devront charger la source par `tenant_id` et vérifier parent/category/relations dans le même tenant. |
| Transaction partielle | Élevé | Produit créé sans toutes ses variantes si erreur au milieu ; duplication finale doit être transactionnelle. |

## 5. Écart avec l’exigence P1

| Exigence P1 | Verdict | Commentaire |
| --- | --- | --- |
| Endpoint preview produit | Absent | À créer. |
| Endpoint confirmation produit | Absent | À créer avec transaction. |
| Endpoint preview catégorie | Absent | À créer ou reporter explicitement. |
| Endpoint confirmation catégorie | Absent | À créer avec génération slug/path. |
| Politique serveur de duplication | Absent | À créer, ne pas dépendre du front. |
| Wizard multi-étapes | Absent | À créer côté frontend. |
| Étape “Champs à compléter” obligatoire | Absent | À créer. |
| Copie champs descriptifs produit | Absent | À implémenter via preview sûre. |
| Non-copie SKU/barcode/internal/GTIN | Absent mais faisable | À imposer côté serveur. |
| Non-copie stock réel | À risque | Le formulaire actuel sait créer du stock initial ; duplication doit neutraliser ce chemin. |
| Duplication structure variantes sans SKU/barcode | Absent | À implémenter avec policy dédiée. |
| Non-copie séries/IMEI/VIN | Partiellement couvert par absence de modèle mature | À verrouiller future-proof. |
| Non-copie garanties émises/licences | Future-proof requis | Fonctionnalités pas encore complètes, mais policy doit les exclure. |
| Permissions manager/admin | Partiellement conforme | Routes write actuelles ont `role:manager|admin`; endpoints duplication devront suivre le même modèle + tests. |
| Tenant isolation | Partiellement conforme | Services existants filtrent souvent par tenant ; duplication devra le faire partout, y compris relations. |
| Tests backend | Absent | À ajouter. |
| Tests frontend | Absent | À ajouter. |

## 6. Proposition d’architecture

### 6.1 Principe directeur

Ne pas implémenter la duplication comme une copie du payload front. Le backend doit être source de vérité : il prépare une preview sûre, indique ce qui est copié/vidé/régénéré/exclu, puis crée le nouveau produit depuis un payload strictement validé.

### 6.2 Services proposés

Option recommandée : créer des services dédiés, appelés par les contrôleurs catalogue existants :

- `ProductDuplicationService` ;
- `CategoryDuplicationService` ou une section dédiée dans `CatalogService` si l’équipe préfère limiter le nombre de classes ;
- éventuellement des DTO/arrays structurés : `DuplicateProductPreview`, `DuplicateProductInput`, `DuplicateCategoryPreview`.

Le service doit exposer au minimum :

- `previewProductDuplicate(string $tenantId, string $productId, array $options): array` ;
- `duplicateProduct(string $tenantId, string $productId, array $input, string $actorId): Product` ;
- `previewCategoryDuplicate(string $tenantId, string $categoryId, array $options): array` ;
- `duplicateCategory(string $tenantId, string $categoryId, array $input, string $actorId): Category`.

### 6.3 Endpoints proposés

À ajouter sous le même groupe `auth:sanctum` + `EnsureUserBelongsToTenant` + `role:manager|admin` :

- `GET /api/catalog/products/{id}/duplicate/preview` ;
- `POST /api/catalog/products/{id}/duplicate` ;
- `GET /api/catalog/categories/{id}/duplicate/preview` ;
- `POST /api/catalog/categories/{id}/duplicate`.

### 6.4 Politique de copie produit

Copier/préremplir :

- `name` sous forme de brouillon : `Copie de {name}` par défaut ;
- `description` ;
- `product_type` / `has_variants` selon support existant ;
- `category_id` si la catégorie appartient au tenant ;
- `supplier_id` si le fournisseur appartient au tenant ;
- prix, devise, comparaison, coût ;
- poids, dimensions futures, metadata non sensible si allowlist ;
- attributs et axes ;
- structure de variantes sans identifiants uniques ;
- politiques stock/garantie futures, pas leurs instances opérationnelles.

Vider/régénérer/exclure :

- produit : `sku`, `barcode`, `internal_barcode`, `gtin` selon politique ;
- variantes : `sku`, `barcode` ;
- stock : aucune ligne `stocks`, aucun mouvement ;
- batches/séries/IMEI/VIN/licences individuelles ;
- garanties déjà émises ;
- commandes, factures, lignes de vente/achat ;
- audit trail source ;
- fichiers de preuve et logs.

### 6.5 Preview sûre

La preview doit retourner un objet lisible par le wizard :

```json
{
  "source": { "id": "...", "name": "...", "sku": "..." },
  "draft": { "name": "Copie de ...", "description": "...", "category_id": "..." },
  "copied": ["description", "category_id", "price", "attributes", "variants.structure"],
  "to_complete": ["sku", "variant_skus"],
  "regenerated": ["internal_barcode"],
  "excluded": ["stock", "stock_movements", "serial_numbers", "issued_warranties", "audit_trail"],
  "warnings": []
}
```

### 6.6 Validation finale

Le `POST duplicate` doit :

1. recharger le produit source côté serveur ;
2. revalider tenant et permissions ;
3. ignorer/refuser tout champ interdit envoyé par le frontend ;
4. valider les champs obligatoires complétés ;
5. créer le produit en transaction ;
6. recréer les variantes sans stock ;
7. synchroniser les attributs/axes autorisés ;
8. journaliser `product.duplicated` avec source et destination ;
9. retourner le produit créé.

### 6.7 Wizard frontend proposé

Créer un composant dédié : `ProductDuplicationWizard.vue`.

Étapes recommandées :

1. Type de duplication : simple, avec variantes, avec/sans médias, structure seulement ;
2. Informations générales ;
3. Variantes/attributs ;
4. Stock/sérialisation/garanties/digital : uniquement visibilité des exclusions et options futures ;
5. Champs à compléter : obligatoire ;
6. Résumé et confirmation.

Le wizard doit consommer la preview serveur et afficher des badges : `copié`, `à compléter`, `régénéré`, `exclu`.

### 6.8 Catégories

Pour les catégories, l’approche minimale :

- preview avec `name = "Copie de {name}"`, `parent_id`, `description`, `sort_order`, `is_active` ;
- slug/code/path vidés ou régénérés côté serveur ;
- pas de copie automatique des enfants sans option validée ;
- pas de copie de statistiques, produits liés, historique ou données calculées.

## 7. Options et besoin de validation

### Option minimale

Ajouter uniquement duplication produit simple et catégorie simple, sans variantes dans un premier temps.

- Impact : faible à moyen.
- Avantage : livraison rapide.
- Limite : ne couvre pas le P1 complet.

### Option recommandée

Ajouter preview + confirmation backend pour produits simples, produits à variantes et catégories, avec wizard frontend dédié. Les produits sérialisés/garantie/digital sont supportés par policy d’exclusion future-proof, sans créer les modules complets manquants.

- Impact : moyen.
- Avantage : couvre l’usage quotidien et verrouille la sécurité serveur.
- Limite : demande une nouvelle surface API et des tests complets.

### Option refonte complète

Refactoriser profondément ProductForm, variantes, stock, politiques produit, garanties, digital et sérialisation avant duplication.

- Impact : élevé.
- Avantage : architecture cible plus pure.
- Limite : trop large pour ce P1 ; risque de régression élevé.

### Décision recommandée

Je recommande **l’option recommandée** : refonte partielle ciblée, pas refonte complète. Avant de coder, il faut valider les arbitrages listés en section 10.

## 8. Plan d’implémentation proposé après validation

1. Ajouter tests backend d’acceptation en échec pour preview/duplicate produit simple.
2. Ajouter `ProductDuplicationService` avec allowlist de champs copiables et blocklist de champs interdits.
3. Ajouter routes et méthodes contrôleur preview/duplicate produit.
4. Ajouter transaction de duplication produit simple + audit `product.duplicated`.
5. Ajouter tests backend variantes : copie structure, pas SKU/barcode/stock.
6. Étendre service pour variantes et attributs.
7. Ajouter tests et service catégorie duplicate.
8. Ajouter méthodes `productService.duplicatePreview()` et `productService.duplicate()` côté frontend.
9. Ajouter bouton “Dupliquer” sur `ProductShowPage` selon permission.
10. Ajouter `ProductDuplicationWizard.vue` avec étapes et badges.
11. Ajouter tests frontend wizard : ouverture, preview, champs à compléter, résumé, erreurs backend.
12. Exécuter tests ciblés backend/frontend.

## 9. Tests à ajouter

### Backend

- `ProductDuplicationTest::simple_product_preview_excludes_unique_and_operational_fields` ;
- `ProductDuplicationTest::simple_product_duplicate_regenerates_or_requires_identifiers` ;
- `ProductDuplicationTest::variant_product_duplicate_copies_structure_without_variant_skus_or_stock` ;
- `ProductDuplicationTest::serialized_product_duplicate_never_copies_batches_serials_or_stock` ;
- `ProductDuplicationTest::digital_product_duplicate_never_copies_individual_licenses` ;
- `ProductDuplicationTest::warranty_product_duplicate_never_copies_issued_warranties` ;
- `CategoryDuplicationTest::category_duplicate_regenerates_slug_and_path` ;
- permissions manager/admin vs viewer/cashier ;
- cross-tenant source ID rejected ;
- rollback si variante invalide après création produit.

### Frontend

- `ProductDuplicationWizard.spec.ts` ;
- bouton visible/masqué selon permission ;
- ouverture depuis `ProductShowPage` ;
- preview affichant copié/à compléter/régénéré/exclu ;
- blocage si champs requis invalides ;
- résumé final ;
- gestion erreur 403/422/404 backend.

## 10. Arbitrages à valider avant implémentation

1. Libellé du bouton : “Dupliquer”, “Créer un produit similaire”, ou les deux selon contexte ?
2. Convention du nom brouillon : `Copie de {nom}`, `{nom} (copie)`, ou champ vide obligatoire ?
3. SKU produit : toujours auto-généré serveur, toujours vide à compléter, ou choix par tenant ?
4. `internal_barcode` : toujours auto-généré serveur ou option wizard ?
5. `barcode` externe et `gtin` : vides par défaut ou copiables dans certains marchés où GTIN désigne le modèle fabricant ?
6. Médias/images : copier par défaut, optionnel, ou jamais en MVP ?
7. Variantes : générer automatiquement les nouveaux SKU variantes ou demander saisie dans “Champs à compléter” ?
8. Catégories enfants : exclues du MVP ou option “dupliquer sous-arbre” plus tard ?
9. Statut du produit dupliqué : toujours `draft` ou conserver le statut source ?
10. Audit : niveau de sévérité `low` ou `medium` pour `product.duplicated` ?

## 11. Conclusion de l’audit

La duplication assistée sécurisée est **absente** aujourd’hui. Les briques de création produit, variantes, catégories, SKU auto et audit existent, mais elles ne suffisent pas à garantir une duplication sûre.

Je ne recommande pas de refonte complète maintenant. Je recommande une **refonte partielle ciblée** autour d’un service de duplication serveur, de deux endpoints preview/confirm et d’un wizard frontend dédié. Cette décision est structurante ; elle doit être validée avant implémentation.
