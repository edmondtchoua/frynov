<template>
  <nav class="catalog-tabs" aria-label="Sections du catalogue">
    <RouterLink
      v-for="tab in visibleTabs"
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
import { usePermission } from '@/composables/usePermission'

interface Tab {
  to: string
  label: string
  icon: string
  badge?: number | string
  exact?: boolean
}

const props = withDefaults(defineProps<{
  counts?: { products?: number; categories?: number; variants?: number; attributes?: number }
  allowedTabs?: string[] // if provided, only these tabs are shown
}>(), {
  counts: () => ({}),
  allowedTabs: undefined,
})

const route = useRoute()
const { catalogTabs: rbacTabs } = usePermission()

const tabs = computed<Tab[]>(() => [
  {
    to: '/catalog',
    label: 'Produits',
    exact: true,
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="1" y="9" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    to: '/catalog/categories',
    label: 'Catégories',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 3h12M2 8h8M2 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  },
  {
    to: '/catalog/variants',
    label: 'Déclinaisons',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="4" cy="4" r="2.5" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="4" r="2.5" stroke="currentColor" stroke-width="1.4"/><circle cx="4" cy="12" r="2.5" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 4h3M4 6.5v3M12 6.5v3M6.5 12h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>',
    badge: (props.counts.variants ?? 0) > 0 ? props.counts.variants : undefined,
  },
  {
    to: '/catalog/attributes',
    label: 'Attributs',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="4" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="4" r="2.5" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 8h3.5M9 4H6.5M9 12H6.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>',
  },
  {
    to: '/catalog/labels',
    label: 'Étiquettes',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 4a1 1 0 011-1h5.586a1 1 0 01.707.293l3.414 3.414A1 1 0 0114 7.414V12a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" stroke="currentColor" stroke-width="1.4"/><circle cx="6" cy="8" r="1" fill="currentColor"/></svg>',
  },
])

const visibleTabs = computed(() => {
  // Props override > RBAC computed > show all
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
