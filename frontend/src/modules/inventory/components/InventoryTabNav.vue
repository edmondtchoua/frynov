<template>
  <nav class="catalog-tabs">
    <RouterLink
      v-for="tab in tabs"
      :key="tab.to"
      :to="tab.to"
      :class="['catalog-tab', { active: isActive(tab) }]"
      :aria-current="isActive(tab) ? 'page' : undefined"
    >
      <span class="tab-icon" v-html="tab.icon"></span>
      <span class="tab-label">{{ tab.label }}</span>
      <span v-if="tab.badge" class="tab-badge">{{ tab.badge }}</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

interface Tab {
  to: string
  label: string
  icon: string
  badge?: number | string
  exact?: boolean
}

const props = withDefaults(defineProps<{
  counts?: { alerts?: number }
}>(), {
  counts: () => ({}),
})

const route = useRoute()

const tabs = computed((): Tab[] => [
  {
    to: '/inventory',
    label: 'Stock',
    exact: true,
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="1" y="9" width="14" height="6" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="3" y="5" width="10" height="4" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="5" y="1" width="6" height="4" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    to: '/inventory/alerts',
    label: 'Alertes',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 1.5a5 5 0 00-5 5v3.5l-1 1.5h12l-1-1.5V6.5a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6.5 13a1.5 1.5 0 003 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
    badge: (props.counts?.alerts ?? 0) > 0 ? props.counts!.alerts : undefined,
  },
  {
    to: '/inventory/warehouses',
    label: 'Entrepots',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M1 14V7L8 2l7 5v7H1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><rect x="5" y="9" width="6" height="5" rx="0.5" stroke="currentColor" stroke-width="1.3"/></svg>',
  },
  {
    to: '/inventory/transfers',
    label: 'Transferts',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 5h12M11 2l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11H2M5 8l-3 3 3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
  {
    to: '/inventory/fiscal-periods',
    label: 'Cloture de periode',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 1v4M11 1v4M1 7h14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M5 10l2 2 4-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
])

function isActive(tab: Tab): boolean {
  if (tab.exact) return route.path === tab.to
  return route.path.startsWith(tab.to)
}
</script>

<style scoped>
.catalog-tabs {
  display: flex;
  gap: 2px;
  padding: 0 0 0 0;
  border-bottom: 2px solid var(--gray-100);
  margin-bottom: 20px;
  overflow-x: auto;
  scrollbar-width: none;
}
.catalog-tabs::-webkit-scrollbar { display: none; }

.catalog-tab {
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
.catalog-tab:hover {
  color: var(--gray-800);
  border-bottom-color: var(--gray-300);
}
.catalog-tab.active {
  color: var(--brand-primary);
  border-bottom-color: var(--brand-primary);
  font-weight: 600;
}
.tab-icon { display: flex; align-items: center; opacity: 0.75; }
.catalog-tab.active .tab-icon { opacity: 1; }

.tab-badge {
  background: var(--gray-200);
  color: var(--gray-600);
  font-size: 0.7rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}
.catalog-tab.active .tab-badge {
  background: var(--brand-primary-bg, #ecfdf5);
  color: var(--brand-primary);
}

@media (max-width: 768px) {
  .catalog-tab { padding: 8px 12px; }
  .tab-label { font-size: 0.8125rem; }
}
</style>
