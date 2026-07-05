<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="modal-overlay"
      :class="`modal-overlay--${variant}`"
      @click.self="close"
    >
      <div
        class="modal"
        :class="[`modal--${variant}`, `modal--${size}`]"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        v-focus-trap="close"
      >
        <div class="modal-header">
          <div class="modal-heading">
            <h3 class="modal-title">{{ title }}</h3>
            <p v-if="subtitle || $slots.subtitle" class="modal-subtitle">
              <slot name="subtitle">{{ subtitle }}</slot>
            </p>
          </div>
          <button class="modal-close" type="button" aria-label="Fermer" @click="close">✕</button>
        </div>
        <div class="modal-body"><slot /></div>
        <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
/** Dialogue partagé (audit UX-03/UX-04 + refonte Side-Drawer).
 *
 *  - `variant="drawer"` (défaut) → **volet latéral droit** plein écran (100vh),
 *    largeur fixe selon `size`, glissé depuis la droite. Standard de l'app pour
 *    les formulaires et fenêtres contextuelles.
 *  - `variant="center"` → boîte **centrée** arrondie, réservée aux confirmations
 *    critiques / cas courts.
 *
 *  Chrome commun : voile sombre, focus-trap (`v-focus-trap`) + Échap + restauration
 *  du focus, en-tête (titre + sous-titre optionnel) / corps défilant / pied collé.
 *  `v-model` contrôle la visibilité. Le style vit dans `main.css` (source unique),
 *  ce composant reste un primitif mince. */
withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    subtitle?: string
    size?: 'sm' | 'md' | 'lg'
    variant?: 'drawer' | 'center'
  }>(),
  { size: 'md', variant: 'drawer' },
)
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void }>()
function close() { emit('update:modelValue', false) }
</script>
