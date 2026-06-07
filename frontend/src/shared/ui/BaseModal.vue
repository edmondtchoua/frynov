<template>
  <Teleport to="body">
    <div v-if="modelValue" class="modal-overlay" @click.self="close">
      <div
        class="modal"
        :class="`modal--${size}`"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        v-focus-trap="close"
      >
        <div class="modal-header">
          <h3 class="modal-title">{{ title }}</h3>
          <button class="modal-close" type="button" aria-label="Fermer" @click="close">✕</button>
        </div>
        <div class="modal-body"><slot /></div>
        <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
/** Shared dialog (audit UX-03/UX-04): overlay + focus-trap (v-focus-trap) + Escape +
 *  restore-focus, with header/body/footer slots. v-model controls visibility. */
withDefaults(defineProps<{ modelValue: boolean; title?: string; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' })
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void }>()
function close() { emit('update:modelValue', false) }
</script>

<style scoped>
/* Size modifiers layered on top of the global .modal styling (main.css). */
.modal--sm { max-width: 420px; }
.modal--md { max-width: 520px; }
.modal--lg { max-width: 680px; }
.modal-title { font-size: var(--text-base, 1rem); font-weight: 700; color: var(--gray-900); margin: 0; }
</style>
