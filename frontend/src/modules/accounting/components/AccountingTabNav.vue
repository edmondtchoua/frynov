<template>
  <nav class="acc-tabs" :aria-label="$t('accounting.navLabel')">
    <RouterLink
      v-for="tab in tabs"
      :key="tab.to"
      :to="tab.to"
      :class="['acc-tab', { active: route.path.startsWith(tab.to) }]"
      :aria-current="route.path.startsWith(tab.to) ? 'page' : undefined"
    >
      {{ tab.label }}
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { t } from '@/i18n'

const route = useRoute()

const tabs = computed(() => [
  { to: '/accounting/invoices', label: t('accounting.tabInvoices') },
  { to: '/accounting/credit-notes', label: t('accounting.tabCreditNotes') },
  { to: '/accounting/entries', label: t('accounting.tabEntries') },
  { to: '/accounting/balance', label: t('accounting.tabBalance') },
  { to: '/accounting/lettrage', label: t('accounting.tabLettrage') },
  { to: '/accounting/bank-reconciliation', label: t('accounting.tabBankRec') },
  { to: '/accounting/statements', label: t('accounting.tabStatements') },
  { to: '/accounting/chart', label: t('accounting.tabChart') },
  { to: '/accounting/taxes', label: t('accounting.tabTaxes') },
  { to: '/accounting/periods', label: t('accounting.tabPeriods') },
  { to: '/accounting/settings', label: t('accounting.tabSettings') },
])
</script>

<style scoped>
.acc-tabs { display: flex; gap: 2px; border-bottom: 2px solid var(--gray-100); margin-bottom: 20px; overflow-x: auto; scrollbar-width: none; }
.acc-tabs::-webkit-scrollbar { display: none; }
.acc-tab { padding: 10px 16px; font-size: 0.875rem; font-weight: 500; color: var(--gray-500); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; white-space: nowrap; transition: color .15s, border-color .15s; }
.acc-tab:hover { color: var(--gray-800); border-bottom-color: var(--gray-300); }
.acc-tab.active { color: var(--brand-primary); border-bottom-color: var(--brand-primary); font-weight: 600; }
</style>
