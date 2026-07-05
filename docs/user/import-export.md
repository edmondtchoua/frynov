# Import / Export — Guide utilisateur

## Vue d'ensemble

Le module **Import / Export** vous permet de :
- **Importer** des produits, clients ou fournisseurs depuis un fichier Excel (.xlsx ou .csv)
- **Exporter** vos données vers Excel ou PDF
- **Télécharger des templates** pré-formatés pour faciliter la saisie

---

## Accéder au module

Navigation : **Import / Export**

---

## Importer des données

### Étape 1 — Nouvelle importation

Cliquez sur **Nouvel import** (bouton en haut à droite de l'historique).

### Étape 2 — Choisir le type et le mode

**Type de données :**
- **Produits** : catalogue produits avec prix, catégorie, fournisseur
- **Clients** : carnet d'adresses clients
- **Fournisseurs** : liste des fournisseurs

**Mode d'import :**

| Mode | Comportement |
|------|-------------|
| **Créer + Mettre à jour** | Crée les nouveaux, met à jour les existants (recommandé) |
| **Créer uniquement** | Ignore les doublons, n'importe que les nouvelles lignes |
| **Mettre à jour uniquement** | Met à jour les existants, ignore les nouveaux |
| **Simulation** | Vérifie votre fichier sans rien enregistrer |

> 💡 **Conseil :** Utilisez le mode **Simulation** pour vérifier votre fichier sans risque avant l'import réel.

### Étape 3 — Télécharger le template

Cliquez sur **Télécharger le template** pour obtenir un fichier Excel avec :
- Les colonnes attendues
- Des exemples de données
- Une légende des champs obligatoires/optionnels

Remplissez ce fichier avec vos données.

### Étape 4 — Uploader votre fichier

Glissez-déposez votre fichier Excel (`.xlsx`) ou cliquez pour le sélectionner.

- Formats acceptés : `.xlsx`, `.xls`, `.csv`
- Taille max : 10 Mo

Le système analyse automatiquement vos colonnes et propose un **mapping**.

### Étape 5 — Vérifier le mapping des colonnes

Le système tente de reconnaître automatiquement vos colonnes (les noms en français et en anglais sont reconnus).

Si une colonne n'est pas reconnue, utilisez le menu déroulant pour l'associer manuellement au bon champ.

Cliquez **Ré-analyser** après modification du mapping.

### Étape 6 — Validation et exécution

Le résumé affiche :
- ✅ Lignes valides
- ⚠️ Avertissements (données imprécises mais importables)
- ❌ Erreurs (lignes bloquantes)

**Filtres** disponibles : Toutes / Valides / Erreurs / Avertissements / À créer / À mettre à jour

Si les erreurs sont acceptables, cliquez **Approuver et importer** pour lancer l'exécution.

### Résultat

À la fin, un résumé affiche combien de lignes ont été importées, mises à jour, ou ignorées. Vous pouvez **télécharger le rapport PDF** de l'import.

---

## Exporter vos données

Depuis la page **Import / Export**, cliquez sur les boutons d'export en haut :

| Bouton | Format | Contenu |
|--------|--------|---------|
| Produits XLSX | Excel | Tous les produits actifs |
| Clients XLSX | Excel | Tous les clients |
| Fournisseurs XLSX | Excel | Tous les fournisseurs |

> Les exports au format PDF sont disponibles depuis les pages de liste de chaque module.

---

## Historique des imports

L'historique liste tous vos imports précédents avec :
- Date et type
- Statut (en cours, terminé, erreur...)
- Statistiques (lignes importées, erreurs)

**Actions disponibles :**
- ▶ **Continuer** : reprendre un import en attente d'approbation
- 📄 **Rapport** : télécharger le rapport PDF d'un import terminé
- ✕ **Annuler** : annuler un import en cours

---

## Gestion des doublons

Le système détecte automatiquement les doublons :
- **Produits** : par SKU et code-barres
- **Clients** : par email et téléphone
- **Fournisseurs** : par code et email

En mode **Créer + Mettre à jour**, les doublons sont mis à jour avec les nouvelles données.  
En mode **Créer uniquement**, les doublons sont ignorés (marqués "skipped").

---

## Gros volumes

Pour les fichiers de plus de 200 lignes, l'import est traité en **arrière-plan**. La page se met à jour automatiquement (toutes les 2 secondes) jusqu'à la fin du traitement.
