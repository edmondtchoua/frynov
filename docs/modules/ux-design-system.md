# Design system partagé (audit UX-03 / UX-05)

> Composants UI réutilisables sous `frontend/src/shared/ui/`, construits sur les tokens
> CSS de `src/assets/main.css` (couleurs `--brand-*`/`--gray-*`, `--radius-*`, `--text-*`).
> Objectif : arrêter de redéfinir boutons/cartes/états/modales dans chaque vue.

## Composants

| Composant | Rôle | Points clés |
|---|---|---|
| `StateBlock.vue` | États **loading / empty / error / forbidden** standardisés | `variant`, `title`, `message`, slot `#action` ; `role="status"`, `aria-busy` en loading ; respecte `prefers-reduced-motion` |
| `BaseButton.vue` | Bouton primitif | `variant` (primary/secondary/danger/ghost), `size` (sm/md), `loading` (spinner + `aria-busy`), `block` ; anneau focus clavier global |
| `BaseModal.vue` | Dialogue | `v-model`, `title`, `size` ; `role="dialog"` + `aria-modal` + **`v-focus-trap`** (piège Tab, Échap, restauration focus) ; slots défaut + `#footer` ; `Teleport` vers `body` |

Page transverse : `pages/AccessUnavailableView.vue` (route `/unavailable`) — page
« accès indisponible » contextualisée (module désactivé / permission manquante / quota),
construite avec `StateBlock` (forbidden) + `BaseButton`. Query : `?reason=module|permission|quota&module=<label>`.

## Accessibilité (UX-04, livré en P0)
- `.sr-only` + `:focus-visible` globaux (`main.css`).
- Directive globale **`v-focus-trap`** (`src/directives/focusTrap.ts`) — à poser sur tout
  conteneur de modale (`<div class="modal" v-focus-trap="close">`).
- Sidebar : landmark `aria-label`, toggles `aria-expanded`/`aria-controls`.
- Onglets de navigation : `<nav aria-label>` + `aria-current` (pattern correct pour des
  onglets-liens — `role=tab` est réservé aux widgets à panneaux internes).
- Toggles : `role="switch"` sur les interrupteurs.

## Adoption
Les composants sont disponibles immédiatement. La **migration des vues existantes**
(≈ 36 `empty-state` ad hoc, boutons et modales locaux) vers ces primitives est
incrémentale — à faire vue par vue lors des prochains passages.

## Tests
- `shared/ui/__tests__/ui.spec.ts` — StateBlock (loading/forbidden + action), BaseButton
  (variant/loading/disabled/aria), BaseModal (dialog + close).
- `directives/__tests__/focusTrap.spec.ts` — Échap + focus-trap.
