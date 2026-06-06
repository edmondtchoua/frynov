<template>
  <nav class="reports-tabs">
    <RouterLink
      v-for="tab in visibleTabs"
      :key="tab.to"
      :to="tab.to"
      :class="['reports-tab', { active: isActive(tab) }]"
      :aria-current="isActive(tab) ? 'page' : undefined"
    >
      <span class="tab-icon" v-html="tab.icon"></span>
      <span class="tab-label">{{ tab.label }}</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { usePermission } from '@/composables/usePermission'

interface Tab {
  to: string
  label: string
  icon: string
  exact?: boolean
}

const props = withDefaults(defineProps<{
  allowedTabs?: string[] // if provided, only these tabs are shown
}>(), {
  allowedTabs: undefined,
})

const route = useRoute()
const { reportsTabs: rbacTabs } = usePermission()

const tabs = computed<Tab[]>(() => [
  {
    to: '/reports/sales',
    label: 'Rapport des ventes',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 12L6 8l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 14h12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>',
  },
  {
    to: '/reports/stock',
    label: 'Rapport de stock',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="1" y="9" width="3" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="6" y="6" width="3" height="8" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="11" y="3" width="3" height="11" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
])

const visibleTabs = computed(() => {
  const allowed = props.allowedTabs ?? rbacTabs.value
  if (!allowed) return tabs.value
  return tabs.value.filter(tab => allowed.includes(tab.to))
})

function isActive(tab: Tab): boolean {
  if (tab.exact) return route.path === tab.to
  return route.path.startsWith(tab.to)
}
</script>

<style scoped>
.reports-tabs {
  display: flex;
  gap: 2px;
  padding: 0 0 0 0;
  border-bottom: 2px solid var(--gray-100);
  margin-bottom: 20px;
  overflow-x: auto;
  scrollbar-width: none;
}
.reports-tabs::-webkit-scrollbar { display: none; }

.reports-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-500);
  text-decoration: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
}
.reports-tab:hover {
  color: var(--gray-800);
  border-bottom-color: var(--gray-300);
}
.reports-tab.active {
  color: var(--brand-primary);
  border-bottom-color: var(--brand-primary);
  font-weight: 600;
}
.tab-icon { display: flex; align-items: center; opacity: 0.75; }
.reports-tab.active .tab-icon { opacity: 1; }

@media (max-width: 768px) {
  .reports-tab { padding: 8px 12px; }
  .tab-label { font-size: 0.8125rem; }
}
</style>
