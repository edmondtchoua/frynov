# Identifiants produit : SKU, code-barres interne et GTIN

## Les trois types d'identifiants

### SKU (Stock Keeping Unit)
- Identifiant **interne** créé par votre entreprise.
- Format libre : lettres, chiffres, tirets (ex. `CHEM-BLC-XL-001`).
- Utilisé pour la gestion de stock, les rapports et les commandes internes.
- **Obligatoire** : chaque produit doit avoir un SKU unique.

### Code-barres interne
- Code-barres généré par le système pour usage **interne à votre entrepôt**.
- Non reconnu à l'extérieur de votre organisation.
- Imprimable sur des étiquettes pour scanner en réception / expédition.
- Format : Code 128 ou QR code selon la configuration.

### GTIN (Global Trade Item Number)
- Standard international : EAN-13, UPC-A, EAN-8, ITF-14, etc.
- Reconnu par les grandes surfaces, marketplaces et systèmes douaniers.
- **Jamais fictif** : un GTIN doit être officiellement attribué par GS1 (https://www.gs1.org).
- Saisir uniquement si vous possédez un préfixe GS1 valide.

## Auto-génération

| Identifiant         | Auto-généré ? | Modifiable ? |
|---------------------|---------------|--------------|
| SKU                 | Oui (optionnel) | Oui        |
| Code-barres interne | Oui automatiquement | Non    |
| GTIN                | **Non** — saisie manuelle uniquement | Oui |

Pour activer l'auto-génération du SKU : **Paramètres > Produits > Format SKU**.
Définissez un préfixe et un compteur (ex. `PROD-0001`, `PROD-0002`…).

## Remplacement manuel (override)

- **SKU** : modifiable depuis la fiche produit à tout moment. Un changement de SKU met à jour les références dans les commandes futures (pas rétroactif).
- **Code-barres interne** : non modifiable — re-imprimer l'étiquette si le produit est réassigné.
- **GTIN** : saisir ou corriger depuis la fiche produit > onglet **Identifiants**. Le système valide la longueur et le chiffre de contrôle, mais ne vérifie pas l'appartenance GS1.

## Bonnes pratiques

- Ne jamais inventer un GTIN : cela cause des conflits dans les marketplaces et les systèmes EDI.
- Utiliser le SKU comme clé primaire dans vos imports/exports CSV.
- Conserver une cohérence de format SKU sur toute la gamme pour faciliter les filtres et rapports.
