<template>
  <div class="form-field" :class="{ 'form-field--invalid': !!error }">
    <label v-if="label" :for="fieldId" class="form-field__label">
      {{ label }}<span v-if="required" class="form-field__req" aria-hidden="true"> *</span>
    </label>

    <!-- Consumer binds these to the real control:
         <input :id="id" :aria-describedby="errorId" :aria-invalid="invalid" /> -->
    <slot :id="fieldId" :error-id="error ? errorId : undefined" :invalid="!!error || undefined" />

    <p v-if="hint && !error" class="form-field__hint">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="form-field__error" role="alert">{{ error }}</p>
  </div>
</template>

<script lang="ts">
// Module-scoped counter (runs once) → unique, stable ids per field instance.
let _uid = 0
</script>

<script setup lang="ts">
/** Labeled form field with an error linked to the control via aria-describedby
 *  (audit UX-07). Use the scoped slot to wire { id, errorId, invalid } onto the input. */
defineProps<{ label?: string; error?: string | null; hint?: string; required?: boolean }>()

const uid = ++_uid
const fieldId = `ff-${uid}`
const errorId = `ff-${uid}-err`
</script>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: 0.35rem; }
.form-field__label { font-size: var(--text-sm, 0.875rem); font-weight: 500; color: var(--gray-600); }
.form-field__req { color: var(--color-error, #ef4444); font-weight: 600; }
.form-field__hint { font-size: var(--text-xs, 0.75rem); color: var(--gray-400); margin: 0; }
.form-field__error { font-size: var(--text-xs, 0.75rem); color: var(--color-error, #ef4444); margin: 0; }
.form-field--invalid :deep(input),
.form-field--invalid :deep(select),
.form-field--invalid :deep(textarea) { border-color: var(--color-error, #ef4444); }
</style>
