<template>
  <label class="lang-switch">
    <span class="sr-only">{{ t('language.label') }}</span>
    <select
      class="lang-select"
      :value="locale"
      :aria-label="t('language.label')"
      @change="onChange"
    >
      <option v-for="l in availableLocales" :key="l" :value="l">{{ localeLabels[l] }}</option>
    </select>
  </label>
</template>

<script setup lang="ts">
/** Compact locale selector (audit UX-13) — switches the in-app language reactively. */
import { useI18n, type Locale } from '@/i18n'

const { locale, availableLocales, localeLabels, setLocale, t } = useI18n()

function onChange(e: Event) {
  setLocale((e.target as HTMLSelectElement).value as Locale)
}
</script>

<style scoped>
.lang-switch { display: inline-flex; align-items: center; }
.lang-select {
  appearance: none;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md, 8px);
  background: white;
  color: var(--gray-700);
  font-size: var(--text-xs, 0.8rem);
  font-weight: 600;
  padding: 0.3rem 1.4rem 0.3rem 0.6rem;
  cursor: pointer;
  /* chevron */
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%23737373' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.5rem center;
  transition: border-color 0.12s;
}
.lang-select:hover { border-color: var(--gray-300); }
.lang-select:focus-visible { outline: 2px solid var(--brand-primary); outline-offset: 1px; }
</style>
