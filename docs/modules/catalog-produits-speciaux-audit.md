# Audit catalogue/inventaire — services, produits digitaux, garanties et produits sérialisés

## 1. Réponse courte

**Non, pas encore en production sans refactorisation.** Le catalogue sait aujourd'hui décrire un produit simple, un produit à variantes, un service et un kit au niveau du champ `product_type`, et l'inventaire sait compter des quantités agrégées. En revanche, l'ensemble catalogue + inventaire + commandes ne permet pas encore de gérer correctement les subtilités suivantes :

- un **service** vendable sans stock de bout en bout ;
- un **produit digital** avec livraison immatérielle, fichier, licence, droit de téléchargement ou activation ;
- un **produit physique sérialisé** comme un téléphone identifié par IMEI ou une voiture identifiée par VIN/numéro de châssis ;
- une **garantie** rattachée au produit vendu, au client, à la date de vente et éventuellement à une unité sérialisée ;
- une traçabilité fiable de cycle de vie : réception, stock, réservation, vente, retour, réparation, remplacement, casse/perte.

La capacité actuelle est donc **suffisante pour cataloguer le modèle commercial** d'un téléphone ou d'une voiture, par exemple “iPhone 15 128 Go noir” ou “Toyota Corolla 2022”, mais **insuffisante pour gérer chaque unité réelle** : IMEI, VIN, plaque, état, garantie, historique et affectation à une commande.

## 2. Cartographie de l'existant observé

### 2.1 Typologie produit côté back-end

Le back-end introduit `product_type` avec les valeurs `simple`, `variable`, `service` et `kit` dans la migration du catalogue. Le commentaire de migration indique que `service` doit être non stockable et que `product_type` devient le discriminateur d'autorité, tandis que `has_variants` reste conservé pour compatibilité.

Le modèle `Product` expose les constantes correspondantes et les helpers `isVariable()`, `isService()` et `isStockable()`. Toutefois, la règle actuelle de stockabilité se limite à `product_type !== service`. Cela signifie qu'il n'existe pas de distinction serveur entre :

- produit physique en stock agrégé ;
- produit physique sérialisé ;
- produit digital ;
- service ;
- kit/bundle ;
- produit sous garantie obligatoire.

### 2.2 Typologie produit côté front-end

Les types TypeScript déclarent bien `ProductType = 'simple' | 'variable' | 'service' | 'kit'`. La page détail produit affiche un badge de type et masque plusieurs actions de stock lorsque `product_type === 'service'`.

En revanche, le formulaire de création/édition produit ne présente pas de sélection claire de type produit et ne sérialise pas `product_type` dans le payload principal. L'utilisateur peut activer les variantes, saisir les prix, les codes-barres et un stock initial, mais il ne peut pas créer proprement un service, un produit digital ou un produit sérialisé depuis l'interface actuelle.

### 2.3 Variantes et attributs

Le catalogue permet de gérer des variantes et des attributs comme couleur, RAM, stockage, taille ou autres caractéristiques. Ce modèle est adapté pour décrire des **variantes commerciales** :

- téléphone : couleur, capacité, RAM, ROM ;
- voiture : motorisation, couleur, finition ;
- vêtement : taille, couleur ;
- ordinateur : CPU, RAM, stockage.

Mais ces attributs ne sont pas conçus pour identifier une **unité vendue unique**. Un IMEI, un VIN ou un numéro de châssis ne doit pas être stocké comme une simple variante ou dans un champ libre du produit, car il doit être unique, auditable, recherché, scanné, réservé, vendu et lié à une ligne de commande.

### 2.4 Inventaire actuel

L'inventaire repose principalement sur une table `stocks` qui stocke des quantités agrégées par tenant, produit, variante et éventuellement entrepôt. Les mouvements de stock modifient `quantity` et `reserved_quantity` avec des opérations de type entrée, sortie, ajustement, réservation et libération.

Ce modèle convient à un stock standard : “il reste 12 unités de ce SKU”. Il ne suffit pas pour répondre à la question : “quelles sont les 12 unités, avec quels IMEI/VIN, quels états, quelles garanties, quels historiques et quelles commandes associées ?”.

### 2.5 Ébauche de lots et numéros de série

Une migration `product_batches` existe et contient un champ `serial_number` nullable, puis ajoute `batch_id` aux mouvements de stock. Cette migration est une base utile mais elle n'est pas suffisante pour des produits sérialisés :

- le numéro de série est porté par le lot, pas par une unité individuelle normalisée ;
- il n'y a pas de contrainte unique dédiée au numéro de série/IMEI/VIN par tenant ;
- il n'y a pas de modèle métier `ProductBatch` exploité de bout en bout dans les flux catalogue, inventaire et commandes ;
- les mouvements de stock restent essentiellement agrégés ;
- il n'y a pas d'allocation d'une unité sérialisée à une ligne de commande.

Conclusion : la migration est un **scaffold partiel**, pas une fonctionnalité opérationnelle de sérialisation.

### 2.6 Commandes et ventes

Le service de commande réserve du stock à la confirmation et sort du stock à la livraison/fulfillment pour chaque ligne de commande. Il ne lit pas une politique de stock par produit et ne saute pas explicitement la réservation pour les services ou les produits digitaux.

Conséquence : un service peut être marqué non stockable dans le catalogue, mais le flux commande peut encore tenter de réserver/sortir du stock. Cela rend la vente de services fragile, oblige à créer un faux stock ou provoque des erreurs métier.

### 2.7 Garanties

Aucun modèle métier dédié n'a été identifié pour :

- politique de garantie par produit ou variante ;
- garantie contractuelle créée lors de la vente ;
- rattachement garantie ⇄ client ⇄ ligne de commande ⇄ unité sérialisée ;
- dates de début/fin de garantie ;
- exclusions, extensions, réparations, retours ou remplacements ;
- preuve d'achat et historique SAV.

Un champ `metadata` pourrait techniquement recevoir des valeurs ad hoc, mais ce serait insuffisant pour une fonctionnalité métier fiable.

### 2.8 Produits digitaux

Aucun modèle dédié n'a été identifié pour :

- fichiers digitaux privés ;
- droits de téléchargement ;
- clés de licence ;
- expiration d'accès ;
- livraison automatique après paiement ;
- protection contre les accès inter-tenant ;
- révocation d'accès ou rotation de licence.

Le type produit actuel ne contient pas `digital` et l'inventaire ne distingue pas un produit digital d'un produit physique simple.

## 3. Matrice de capacité par cas d'usage

| Cas d'usage | Capacité actuelle | Niveau | Commentaire |
| --- | --- | --- | --- |
| Produit physique simple stocké en quantité | Oui | Acceptable | Modèle `stocks` adapté aux quantités agrégées. |
| Produit avec variantes commerciales | Oui partiel | Acceptable avec réserves | Adapté aux axes couleur/RAM/stockage, mais pas aux identifiants unitaires. |
| Service non stockable | Partiel | Insuffisant | `product_type=service` existe, mais création UI et commandes ne sont pas alignées de bout en bout. |
| Produit digital | Non | Bloquant | Pas de type `digital`, pas d'assets privés, pas de licences, pas d'entitlements. |
| Téléphone avec IMEI | Non | Bloquant | Le modèle peut décrire le téléphone, pas chaque appareil avec IMEI unique. |
| Voiture avec VIN/numéro de châssis | Non | Bloquant | Le modèle peut décrire le véhicule, pas chaque unité/actif avec VIN, état, historique et garantie. |
| Produits avec garantie | Non | Bloquant | Pas de politique de garantie ni contrat généré à la vente. |
| Lots avec expiration | Scaffold partiel | Insuffisant | Migration de lots présente, intégration métier incomplète. |
| Kit/bundle | Partiel annoncé | Insuffisant | Type `kit` existe, mais nomenclature/composition et consommation stock ne sont pas complètes. |

## 4. Risques si l'on force ces usages dans l'état actuel

### 4.1 Risques métier

- Vente d'un service bloquée par une réservation de stock inutile.
- Produit digital vendu sans livraison automatique ni contrôle d'accès.
- Garantie impossible à prouver ou à calculer correctement.
- Retour SAV impossible à relier à l'unité réellement vendue.
- Double vente possible d'une même unité si l'identifiant unique n'est pas verrouillé.
- Confusion entre variante commerciale et unité physique réelle.

### 4.2 Risques opérationnels

- Recherche impossible par IMEI/VIN si ces valeurs sont dispersées dans `metadata`.
- Inventaire faussement correct : quantité juste, mais unités inconnues.
- Difficulté de rapprochement entre réception, vente, livraison, retour et réparation.
- Scan code-barres insuffisant : un code-barres produit identifie le modèle, pas forcément l'unité.

### 4.3 Risques sécurité et multitenant

- Un IMEI/VIN stocké dans un JSON libre risque de contourner les validations, l'unicité et l'audit.
- Les fichiers digitaux sans entitlements serveur exposent un risque d'accès direct ou inter-tenant.
- Une garantie rattachée uniquement côté front ou dans des notes peut être modifiée sans piste d'audit robuste.
- Des identifiants unitaires sensibles peuvent être exposés dans des listes non filtrées ou exportés sans contrôle de permissions.

## 5. Décision d'audit

### 5.1 Ce que le système sait faire aujourd'hui

Le système sait gérer :

1. des produits simples avec SKU, prix, catégorie, statut et codes-barres ;
2. des produits à variantes commerciales ;
3. un indicateur serveur `product_type=service` ;
4. des quantités stock/réservées agrégées ;
5. des mouvements de stock agrégés ;
6. une amorce de table de lots.

### 5.2 Ce que le système ne sait pas encore garantir

Le système ne garantit pas encore :

1. la vente d'un service sans stock ;
2. la livraison d'un produit digital ;
3. l'unicité d'un IMEI, VIN ou numéro de châssis ;
4. la réservation/vente d'une unité sérialisée précise ;
5. la création automatique d'une garantie lors de la vente ;
6. la traçabilité SAV d'une unité ;
7. la protection fine des assets digitaux ;
8. la cohérence entre catalogue, inventaire, commandes, paiements, livraison et reporting.

## 6. Modèle cible recommandé

### 6.1 Étendre la classification produit

Ajouter une politique explicite plutôt qu'un simple type :

- `product_type` ou `product_kind` : `physical`, `service`, `digital`, `kit` ;
- `stock_tracking` : `none`, `aggregate`, `batch`, `serialized` ;
- `fulfillment_type` : `none`, `manual`, `delivery`, `download`, `license`, `appointment` ;
- `warranty_policy_id` nullable ;
- `requires_serial_on_receipt` boolean ;
- `requires_serial_on_sale` boolean ;
- `serial_schema` : `imei`, `vin`, `serial`, `custom` ;
- `is_returnable`, `is_warranty_eligible`, `is_downloadable`.

Cette séparation évite de mélanger la nature commerciale du produit, sa gestion de stock et son mode de livraison.

### 6.2 Ajouter des caractéristiques spéciales dynamiques

Il ne faut **pas** hardcoder uniquement `imei` et `vin`. IMEI et VIN doivent être des **modèles prédéfinis** livrés par défaut, mais l'architecture doit permettre d'ajouter d'autres caractéristiques spéciales selon le métier : numéro de série constructeur, MAC address, numéro de plaque, numéro moteur, numéro de certificat, référence équipement médical, code licence, etc.

Créer une couche de définition dynamique, par exemple `special_attribute_definitions` :

- `id` ;
- `tenant_id` nullable pour les définitions globales ou personnalisées par tenant ;
- `code` : `imei`, `vin`, `serial_number`, `mac_address`, `engine_number`, `license_key`, etc. ;
- `label` ;
- `scope` : `product`, `variant`, `inventory_unit`, `order_line`, `customer_asset` ;
- `data_type` : `string`, `number`, `date`, `boolean`, `enum`, `json` ;
- `is_required_on_receipt` ;
- `is_required_on_sale` ;
- `is_unique` ;
- `unique_scope` : `tenant`, `product`, `variant`, `global` ;
- `is_filterable` ;
- `is_searchable` ;
- `is_scannable` ;
- `is_sensitive` ;
- `validation_regex` nullable ;
- `normalization_strategy` : `uppercase`, `digits_only`, `trim`, `vin`, `imei`, `none` ;
- `allowed_values` JSON nullable pour les enums ;
- `help_text` ;
- `sort_order` ;
- `is_active`.

Exemple de définition pour un téléphone :

```json
{
  "code": "imei",
  "label": "IMEI",
  "scope": "inventory_unit",
  "data_type": "string",
  "is_required_on_receipt": true,
  "is_required_on_sale": true,
  "is_unique": true,
  "unique_scope": "tenant",
  "is_filterable": true,
  "is_searchable": true,
  "is_scannable": true,
  "normalization_strategy": "imei"
}
```

Exemple de définition pour une voiture :

```json
{
  "code": "vin",
  "label": "VIN / numéro de châssis",
  "scope": "inventory_unit",
  "data_type": "string",
  "is_required_on_receipt": true,
  "is_required_on_sale": true,
  "is_unique": true,
  "unique_scope": "tenant",
  "is_filterable": true,
  "is_searchable": true,
  "is_scannable": true,
  "normalization_strategy": "vin"
}
```

Le produit ou la variante doit référencer les définitions applicables via une table pivot, par exemple `product_special_attributes`, afin de déclarer qu'un produit exige IMEI, VIN, numéro moteur ou tout autre attribut métier sans changement de code.

### 6.3 Ajouter des unités sérialisées

Créer une table dédiée, par exemple `inventory_units` ou `product_units` :

- `id` ;
- `tenant_id` ;
- `product_id` ;
- `variant_id` nullable ;
- `warehouse_id` nullable ;
- `batch_id` nullable ;
- `condition` : `new`, `used`, `refurbished`, `damaged` ;
- `status` : `in_stock`, `reserved`, `sold`, `returned`, `repair`, `quarantine`, `lost`, `scrapped` ;
- `received_at`, `reserved_at`, `sold_at` ;
- `order_id`, `order_line_id`, `customer_id` ;
- `warranty_started_at`, `warranty_ends_at` ;
- timestamps et soft deletes si nécessaire.

Stocker les valeurs dynamiques dans une table normalisée, par exemple `inventory_unit_attribute_values` :

- `id` ;
- `tenant_id` ;
- `inventory_unit_id` ;
- `special_attribute_definition_id` ;
- `value_string`, `value_number`, `value_date`, `value_boolean`, `value_json` selon `data_type` ;
- `normalized_value` pour recherche, scan et unicité ;
- `created_by`, `updated_by`.

Contraintes minimales :

- unicité conditionnelle selon la définition : `(tenant_id, special_attribute_definition_id, normalized_value)` quand `is_unique=true` et `unique_scope=tenant` ;
- index par tenant, produit, variante, statut ;
- index par `order_line_id` et `customer_id` ;
- index de recherche sur `normalized_value` seulement si `is_searchable` ou `is_filterable` ;
- normalisation serveur selon la stratégie de la définition, pas selon une liste hardcodée.

### 6.4 Ajouter les garanties

Créer :

- `warranty_policies` : durée, unité, conditions, couverture, exclusions ;
- `warranty_contracts` : garantie effective après vente, liée à `tenant_id`, `customer_id`, `order_line_id`, `product_id`, `variant_id`, `inventory_unit_id` nullable ;
- `warranty_claims` : demandes SAV, diagnostic, réparations, pièces, remplacement, résolution.

Règles serveur :

- une garantie ne démarre pas seulement à la création catalogue, mais généralement à la vente/livraison/facturation ;
- une garantie sérialisée doit pointer vers l'unité vendue ;
- une extension de garantie doit être auditable et facturable si nécessaire.

### 6.5 Ajouter les produits digitaux

Créer :

- `digital_assets` : fichiers privés, version, checksum, visibilité, stockage ;
- `license_keys` : clé, statut, produit/variante, date d'activation, commande ;
- `digital_entitlements` : droits accordés au client après paiement/fulfillment ;
- endpoints de téléchargement signés, expirables et filtrés par tenant/client.

Règles serveur :

- ne jamais exposer directement un chemin de fichier privé ;
- vérifier paiement, tenant, client, commande et entitlement ;
- journaliser téléchargement, activation et révocation.

### 6.6 Adapter les commandes et le fulfillment

Le service commande doit appliquer une stratégie selon `stock_tracking` et `fulfillment_type` :

- `stock_tracking=none` : pas de réservation de stock pour service/digital ;
- `stock_tracking=aggregate` : logique actuelle de `stocks.quantity/reserved_quantity` ;
- `stock_tracking=batch` : allocation par lot et dates d'expiration ;
- `stock_tracking=serialized` : réservation d'unités précises ;
- `fulfillment_type=download/license` : création d'entitlement ou attribution de licence après paiement ;
- `fulfillment_type=appointment` : workflow de service sans stock.

## 7. UX/UI cible

### 7.1 Formulaire produit

Ajouter un assistant de création avec questions métier :

1. “Que vendez-vous ?” : produit physique, service, produit digital, kit ;
2. “Comment le stock est-il suivi ?” : pas de stock, quantité globale, lots, unités sérialisées ;
3. “Faut-il un identifiant unique ?” : aucun, IMEI, VIN/châssis, numéro de série, personnalisé ;
4. “Y a-t-il une garantie ?” : aucune, garantie standard, extension possible ;
5. “Comment se fait la livraison ?” : expédition, retrait, téléchargement, licence, rendez-vous.

### 7.2 Fiche produit

Ajouter selon le type :

- onglet “Unités” pour produits sérialisés ;
- onglet “Lots” pour produits batchés ;
- onglet “Assets digitaux” pour produits digitaux ;
- onglet “Garanties” pour politiques et contrats ;
- badges visibles : non stockable, digital, sérialisé, garantie incluse.

### 7.3 Réception et vente

Pour un téléphone ou une voiture :

- scan ou saisie obligatoire de l'IMEI/VIN à la réception ;
- validation d'unicité immédiate ;
- statut `in_stock` ;
- réservation d'une unité précise lors de la commande ;
- statut `sold` après fulfillment ;
- génération de garantie liée à cette unité.

Pour un service :

- aucune demande de stock ;
- éventuellement planning, intervenant, durée, lieu ;
- fulfillment par réalisation/prestation.

Pour un produit digital :

- upload fichier privé ou import de clés ;
- entitlement généré après paiement ;
- bouton de téléchargement côté client uniquement si droit actif.

## 8. Tests d'acceptation à ajouter pour valider la refactorisation

### 8.1 Services

- Créer un produit `service` depuis l'API et depuis l'UI.
- Confirmer une commande contenant uniquement un service sans créer de stock.
- Fulfill une commande de service sans mouvement de stock.
- Refuser toute entrée/sortie de stock manuelle sur un produit `stock_tracking=none`.

### 8.2 Produits digitaux

- Créer un produit `digital` sans stock.
- Associer un asset privé au produit.
- Refuser l'accès au fichier avant paiement/entitlement.
- Accorder un entitlement après paiement confirmé.
- Refuser l'accès depuis un autre tenant ou un autre client.
- Journaliser téléchargement et révocation.

### 8.3 Produits sérialisés

- Créer un produit `physical` avec `stock_tracking=serialized` et `serial_schema=imei`.
- Réceptionner deux téléphones avec deux IMEI distincts.
- Refuser un IMEI dupliqué dans le même tenant.
- Autoriser le même IMEI seulement si la règle métier choisie l'autorise explicitement hors tenant ; par défaut, préférer l'unicité par tenant.
- Réserver une unité précise sur une commande.
- Empêcher la vente de la même unité sur deux commandes concurrentes.
- Rechercher une unité par IMEI/VIN.
- Masquer les unités d'un autre tenant.

### 8.4 Caractéristiques spéciales dynamiques

- Créer une définition `imei` avec `is_unique=true`, `is_filterable=true`, `is_searchable=true` et `scope=inventory_unit`.
- Créer une définition métier personnalisée, par exemple `engine_number`, sans modifier le code applicatif.
- Attacher ces définitions à une catégorie, un produit ou une variante.
- Refuser la réception d'une unité si une caractéristique `is_required_on_receipt=true` est absente.
- Refuser deux valeurs identiques quand `is_unique=true` selon le `unique_scope`.
- Autoriser les doublons quand `is_unique=false`.
- Normaliser la valeur côté serveur avant comparaison d'unicité et recherche.
- Filtrer/rechercher uniquement sur les attributs `is_filterable` ou `is_searchable`.
- Masquer ou restreindre les attributs `is_sensitive` selon permission serveur.

### 8.5 Garanties

- Créer une politique de garantie de 12 mois sur un produit.
- Générer un contrat de garantie lors du fulfillment ou de la facturation.
- Lier la garantie à `customer_id`, `order_line_id` et `inventory_unit_id` si sérialisé.
- Calculer correctement `warranty_ends_at`.
- Refuser une réclamation SAV hors période sauf override autorisé et audité.

### 8.6 Reporting

- Exclure les services/digitaux des rapports de valorisation de stock.
- Valoriser les produits sérialisés par unité et non uniquement par quantité agrégée.
- Conserver les rapports agrégés pour les produits `stock_tracking=aggregate`.

## 9. Priorisation de remédiation

| Priorité | Chantier | Pourquoi |
| --- | --- | --- |
| P0 | Ajouter une politique serveur `stock_tracking` et corriger commandes/services | Évite faux stock, erreurs de vente et incohérences majeures. |
| P0 | Tests d'acceptation services non stockables | Verrouille le comportement attendu avant refactorisation. |
| P1 | Définitions dynamiques de caractéristiques spéciales | Évite de hardcoder IMEI/VIN et permet d'ajouter des identifiants métier configurables. |
| P1 | Modèle `inventory_units` + valeurs d'attributs dynamiques pour IMEI/VIN/séries | Nécessaire pour téléphones, voitures, équipements et garanties. |
| P1 | Contraintes d'unicité et isolation tenant sur identifiants unitaires | Évite doublons, IDOR et fuites inter-tenant. |
| P1 | Lien commande ⇄ unité sérialisée ⇄ client | Base de la traçabilité vente/SAV. |
| P1 | Garanties politiques + contrats | Nécessaire pour produits à garantie commerciale/légale. |
| P2 | Produits digitaux + entitlements + fichiers privés | Nécessaire pour logiciels, ebooks, licences et accès client. |
| P2 | UX de création par assistant métier | Réduit les erreurs opérateur et rend la fonctionnalité exploitable. |
| P3 | Reporting avancé par unité, lot, garantie et statut | Améliore pilotage et conformité après stabilisation métier. |

## 10. Fichiers à modifier en priorité pour l'agent de code

### Back-end

- `backend/app/Modules/Catalog/Models/Product.php` : ajouter les politiques explicites de produit et de stock.
- `backend/app/Modules/Catalog/Http/Controllers/CatalogController.php` : valider les nouveaux champs et les invariants métier.
- `backend/app/Modules/Catalog/Services/CatalogService.php` : normaliser création/update selon le type.
- `backend/app/Modules/Inventory/Services/StockService.php` : refuser stock sur `stock_tracking=none`, supporter `serialized`.
- `backend/app/Modules/Inventory/Http/Controllers/InventoryController.php` : ajouter réception/recherche/vente d'unités sérialisées.
- `backend/app/Modules/Orders/Services/OrderService.php` : appliquer la stratégie de réservation/fulfillment selon la politique produit.
- `backend/app/Modules/Inventory/database/migrations/*` : créer `inventory_units`, `special_attribute_definitions`, `product_special_attributes`, `inventory_unit_attribute_values` et contraintes uniques dynamiques.
- `backend/app/Modules/Catalog/database/migrations/*` : enrichir `products` ou créer tables de policies.
- `backend/app/Modules/*/Tests/*` : ajouter les tests d'acceptation décrits ci-dessus.

### Front-end

- `frontend/src/modules/catalog/views/ProductFormView.vue` : exposer type, tracking stock, sérialisation, digital, garantie et caractéristiques spéciales configurables.
- `frontend/src/modules/catalog/views/ProductShowPage.vue` : ajouter onglets unités/lots/assets/garanties.
- `frontend/src/modules/catalog/types.ts` : typer les nouvelles politiques.
- `frontend/src/modules/inventory/views/*` : ajouter réception et recherche d'unités par IMEI/VIN.
- `frontend/src/modules/orders/*` ou vues POS concernées : permettre sélection/scan d'une unité sérialisée.

## 11. Checklist “prêt pour voitures, téléphones, services et digital”

- [ ] Un service peut être créé, vendu et fulfilled sans stock.
- [ ] Un produit digital peut être vendu avec entitlement serveur et accès fichier privé.
- [ ] Un téléphone peut être reçu avec une définition dynamique IMEI obligatoire et unique.
- [ ] Une voiture peut être reçue avec une définition dynamique VIN/numéro de châssis obligatoire et unique.
- [ ] Une unité sérialisée peut être réservée puis vendue une seule fois.
- [ ] Les unités d'un tenant sont invisibles depuis un autre tenant.
- [ ] Une garantie est créée automatiquement à la vente selon une politique produit.
- [ ] Une réclamation SAV peut retrouver la vente, le client, l'unité et la période de garantie.
- [ ] Les rapports de stock excluent services/digitaux et valorisent correctement les unités physiques.
- [ ] Les tests API couvrent définitions dynamiques, concurrence, unicité, IDOR et changements de statut.

## 12. Conclusion

Le catalogue actuel est un bon début pour gérer des produits standards et des variantes commerciales, mais il ne doit pas être présenté comme compatible avec des produits complexes tels que téléphones à IMEI, véhicules à VIN, produits digitaux ou garanties. Pour ces usages, il faut ajouter une couche métier explicite : politiques produit, stock tracking, définitions dynamiques de caractéristiques spéciales, unités sérialisées, garanties, entitlements digitaux et stratégies de fulfillment côté serveur.

La priorité est de corriger l'écart entre “type de produit” et “comportement réel du système”. Tant que les commandes et l'inventaire ne respectent pas une politique serveur de stock/livraison, les services et produits spéciaux resteront incohérents malgré les champs de catalogue existants.
