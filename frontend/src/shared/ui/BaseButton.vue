<template>
  <button
    :type="type"
    class="btn-base"
    :class="[`btn-base--${variant}`, `btn-base--${size}`, { 'btn-base--block': block, 'btn-base--loading': loading }]"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
  >
    <span v-if="loading" class="btn-base__spinner" aria-hidden="true"></span>
    <slot name="icon" />
    <span class="btn-base__label"><slot /></span>
  </button>
</template>

<script setup lang="ts">
/** Shared button primitive (audit UX-03). Variants/sizes are token-driven; the
 *  global :focus-visible ring (main.css) gives accessible keyboard focus. */
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md'
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  block?: boolean
}>(), { variant: 'primary', size: 'md', type: 'button' })
</script>

<style scoped>
.btn-base {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md, 8px);
  font-family: inherit;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}
.btn-base--md { padding: 0.55rem 1.1rem; font-size: var(--text-sm, 0.875rem); }
.btn-base--sm { padding: 0.35rem 0.75rem; font-size: var(--text-xs, 0.75rem); }
.btn-base--block { width: 100%; }
.btn-base:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-base--primary { background: var(--brand-primary); color: #fff; }
.btn-base--primary:hover:not(:disabled) { background: var(--brand-primary-hover, #047857); }

.btn-base--secondary { background: var(--brand-secondary, #3b82f6); color: #fff; }
.btn-base--secondary:hover:not(:disabled) { background: var(--brand-secondary-dark, #2563eb); }

.btn-base--danger { background: #ef4444; color: #fff; }
.btn-base--danger:hover:not(:disabled) { background: #dc2626; }

.btn-base--ghost { background: transparent; border-color: var(--gray-300); color: var(--gray-700); }
.btn-base--ghost:hover:not(:disabled) { background: var(--gray-50); }

.btn-base__spinner {
  width: 0.85em;
  height: 0.85em;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: btn-spin 0.6s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }
</style>
