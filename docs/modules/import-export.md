# Module Import/Export — Documentation technique

## Vue d'ensemble

Pipeline complet d'import Excel avec machine d'état, validation par entité, mode simulation, jobs Horizon pour les gros fichiers. Export Excel et PDF pour tous les types d'entités.

## Architecture

```
app/Modules/ImportExport/
├── Providers/ImportExportServiceProvider.php
├── Models/
│   ├── ImportSession.php       ← Machine d'état
│   └── ImportRow.php           ← Ligne individuelle
├── Parsers/
│   ├── ColumnMapper.php        ← Auto-mapping des en-têtes FR/EN
│   ├── ProductImportParser.php
│   ├── CustomerImportParser.php
│   └── SupplierImportParser.php
├── Services/
│   ├── ImportService.php       ← Orchestrateur du pipeline
│   ├── TemplateService.php     ← Génération templates Excel
│   ├── ExcelExporter.php       ← Export données → Excel
│   └── PdfExporter.php         ← Export données → PDF
├── Jobs/
│   ├── AnalyzeImportJob.php    ← Queue 'imports', tries=3, timeout=300s
│   └── ExecuteImportJob.php    ← Queue 'imports', tries=1, timeout=600s
├── Http/
│   ├── Controllers/ImportExportController.php
│   └── Resources/ImportSessionResource.php
├── database/migrations/
│   ├── 2026_05_30_800001_create_import_sessions_table.php
│   └── 2026_05_30_800002_create_import_rows_table.php
├── routes/api.php
└── Tests/
    ├── Unit/
    │   ├── ColumnMapperTest.php     (5 tests)
    │   └── ProductImportParserTest.php (9 tests)
    └── Integration/
        ├── ImportApiTest.php        (8 tests)
        └── ImportModuleTest.php     (8 tests)
```

## Machine d'état — ImportSession

```
draft → analyzing → analyzed / awaiting_approval → importing → completed / partial / failed / cancelled
```

| Statut | Description |
|--------|-------------|
| `draft` | Session créée, fichier uploadé |
| `analyzing` | Analyse en cours (job Horizon ou inline) |
| `analyzed` | Analyse terminée, mapping auto suggéré |
| `awaiting_approval` | Mapping validé, prêt pour exécution |
| `importing` | Exécution en cours |
| `completed` | Toutes les lignes importées |
| `partial` | Certaines lignes ont échoué |
| `failed` | Échec critique |
| `cancelled` | Annulé par l'utilisateur |

## Modes d'import

| Mode | Comportement |
|------|-------------|
| `create_only` | Ignore les doublons existants |
| `update_only` | Met à jour les existants, ignore les nouveaux |
| `create_update` | Créé ou met à jour selon l'existence |
| `simulate` | Valide tout sans écrire en base |

## Seuil synchrone/asynchrone

`ImportService::LARGE_ROW_THRESHOLD = 200`

- ≤ 200 lignes : traitement inline (réponse synchrone)
- > 200 lignes : dispatch `AnalyzeImportJob` ou `ExecuteImportJob` sur queue `imports`

## ColumnMapper

### `normalize(string $header): string`
```php
mb_strtolower(trim($header))
→ iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', ...)  // Catégorie → Cat'egorie
→ preg_replace('/[^a-z0-9]/', '', ...)             // → categorie
```

**Note :** Utilise `iconv` (pas `intl`) car ext-intl n'est pas disponible sur tous les systèmes.

### `autoMap(array $headers, string $entityType): array`
Retourne `[rawHeader => systemField|null]` en cherchant dans le dictionnaire d'alias par entité (produits, clients, fournisseurs).

## Parsers d'import

### ProductImportParser
- **Requis :** sku, name, price
- **Optionnel :** category (résolution par nom), supplier (findOrCreateByName), barcode, cost, status
- **Doublons :** détection par SKU et barcode
- **Prix :** `1 234,56` → `123456` centimes

### CustomerImportParser
- **Requis :** name
- **Doublons :** email + téléphone normalisé
- **Warning :** si ni email ni téléphone

### SupplierImportParser
- **Doublons :** code + email

## Services

### ImportService
- `upload(file, type, mode, tenantId, userId)` → stocke dans `storage/app/imports/{tenantId}/{uuid}.ext`
- `analyze(session)` → inline ou dispatch selon LARGE_ROW_THRESHOLD
- `runAnalysis(session)` → lit le fichier, auto-mappe, crée les ImportRow
- `updateMapping(session, mapping)` → re-analyse avec le nouveau mapping
- `approve(session)` → passe en `awaiting_approval`
- `execute(session)` → exécute les lignes valid/warning
- `cancel(session)` → supprime le fichier + passe en cancelled

### TemplateService
Templates Excel stylisés par entité : en-tête bleu, exemple ligne bleu clair, légende.

### ExcelExporter
Export des données d'une entité (products/customers/suppliers) en Excel avec lignes alternées.

### PdfExporter
Export PDF via dompdf. A4 paysage, CSS DejaVu Sans, couleur #1a56db.

## Routes

```
POST   /api/import/upload             → upload
GET    /api/import/history            → liste paginée
GET    /api/import/template/{type}    → télécharger le template
GET    /api/import/{id}               → statut + lignes
PATCH  /api/import/{id}/mapping       → mettre à jour le mapping
POST   /api/import/{id}/approve       → approuver
POST   /api/import/{id}/execute       → exécuter
DELETE /api/import/{id}               → annuler
GET    /api/import/{id}/report        → rapport PDF
GET    /api/export/{type}?format=xlsx|pdf → exporter
```

**Important :** `history` et `template/{type}` sont déclarés AVANT `/{id}` pour éviter les conflits de routage.

## Tests

**ColumnMapper (5 tests) :** normalize accents, normalize espaces, autoMap produits, autoMap clients, applyMapping.

**ProductImportParser (9 tests) :** ligne valide, ligne invalide (SKU manquant), doublon SKU en create_only, mode update_only, prix avec séparateur FR, catégorie créée, fournisseur trouvé, simulation sans écriture, warning sans catégorie.

**ImportApi (8 tests) :** auth guard, upload valide 201, upload fichier invalide 422, show session, updateMapping re-analyse, approve, execute, cancel 204.

**ImportModule (8 tests) :** pipeline complet products (upload→approve→execute), isolation tenant, mode simulate, doublons create_only, export Excel 200, export PDF 200, download template 200, rapport PDF session terminée.
