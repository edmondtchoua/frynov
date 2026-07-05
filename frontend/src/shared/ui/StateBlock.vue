<template>
  <div
    class="state-block"
    :class="`state-block--${variant}`"
    role="status"
    :aria-busy="variant === 'loading' ? 'true' : undefined"
    aria-live="polite"
  >
    <span v-if="variant === 'loading'" class="state-block__spinner" aria-hidden="true"></span>
    <svg v-else class="state-block__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path v-for="(d, i) in icon" :key="i" :d="d" />
    </svg>

    <p class="state-block__title">{{ title ?? defaultTitle }}</p>
    <p v-if="message" class="state-block__msg">{{ message }}</p>

    <!-- Optional action (e.g. retry / upgrade / create) -->
    <div v-if="$slots.action" class="state-block__action"><slot name="action" /></div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type Variant = 'loading' | 'empty' | 'error' | 'forbidden'

const props = withDefaults(defineProps<{
  variant?: Variant
  title?: string
  message?: string
}>(), { variant: 'empty' })

const ICONS: Record<Exclude<Variant, 'loading'>, string[]> = {
  empty:     ['M4 7h16v13H4z', 'M4 7l2-3h12l2 3', 'M9 11h6'],
  error:     ['M12 8v5', 'M12 16h.01', 'M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z'],
  forbidden: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M5.6 5.6l12.8 12.8'],
}

const DEFAULT_TITLES: Record<Variant, string> = {
  loading:   'Chargement…',
  empty:     'Rien à afficher',
  error:     'Une erreur est survenue',
  forbidden: 'Accès non autorisé',
}

const icon = computed(() => ICONS[props.variant as Exclude<Variant, 'loading'>] ?? ICONS.empty)
const defaultTitle = computed(() => DEFAULT_TITLES[props.variant])
</script>

<style scoped>
.state-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 3rem 1.5rem;
  text-align: center;
}
.state-block__icon { width: 40px; height: 40px; color: var(--gray-300); }
.state-block--error .state-block__icon { color: var(--color-error, #ef4444); }
.state-block--forbidden .state-block__icon { color: var(--gray-400); }

.state-block__spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--gray-200);
  border-top-color: var(--brand-primary);
  border-radius: 50%;
  animation: state-spin 0.7s linear infinite;
}
@keyframes state-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
  .state-block__spinner { animation-duration: 1.6s; }
}

.state-block__title { font-size: var(--text-base, 1rem); font-weight: 600; color: var(--gray-700); margin: 0; }
.state-block__msg { font-size: var(--text-sm, 0.875rem); color: var(--gray-500); margin: 0; max-width: 44ch; }
.state-block__action { margin-top: 0.5rem; }
</style>
