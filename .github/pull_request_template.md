## Description
<!-- Décris ce que cette PR accomplit et pourquoi. Sois concis. -->


## Type de changement
- [ ] `feat` — Nouvelle fonctionnalité
- [ ] `fix` — Correction de bug
- [ ] `test` — Ajout ou correction de tests
- [ ] `refactor` — Refactoring sans changement de comportement
- [ ] `docs` — Documentation uniquement
- [ ] `chore` — Tâche technique (dépendances, config)

## Module(s) impacté(s)
<!-- Ex: Inventory, Orders, Sync/ShopifyConnector, Frontend/OrderTable -->


## Checklist avant de demander une review

### Code
- [ ] Le code respecte les conventions du projet (Pint / ESLint passent)
- [ ] PHPStan niveau 6 ne rapporte aucune erreur
- [ ] Pas de `dd()`, `dump()`, `var_dump()`, `console.log()` oubliés
- [ ] Pas de `TODO` ou `FIXME` non tracés dans un ticket
- [ ] Les migrations ont une méthode `down()` correcte

### Tests
- [ ] Tests unitaires écrits et passent localement
- [ ] Tests d'intégration écrits si la PR touche une API ou une DB
- [ ] La couverture du module n'a pas baissé

### Sécurité
- [ ] Aucun secret ou credential dans le code
- [ ] Les entrées utilisateur sont validées via FormRequest
- [ ] Les politiques d'autorisation (Policy) sont en place

## Comment tester manuellement
<!-- Étapes précises pour que le reviewer puisse vérifier sans chercher -->
1.
2.
3.

## Captures d'écran (si changements UI)
| Avant | Après |
|---|---|
|   |   |

## Liens
<!-- Ticket, issue, doc technique liés -->
- Issue : #
