<template>
  <nav class="sales-tabs">
    <RouterLink
      v-for="tab in visibleTabs"
      :key="tab.to"
      :to="tab.to"
      :class="['sales-tab', { active: isActive(tab) }]"
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
  matchMode: 'orders-root' | 'exact-prefix'
}

const props = withDefaults(defineProps<{
  counts?: { returns?: number; payments_pending?: number }
  allowedTabs?: string[] // if provided, only these tabs are shown
}>(), {
  counts: () => ({}),
  allowedTabs: undefined,
})

const route = useRoute()

const tabs = computed<Tab[]>(() => [
  {
    to: '/orders',
    label: 'Commandes',
    matchMode: 'orders-root',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 6h6M5 9h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    to: '/orders/returns',
    label: 'Retours & SAV',
    matchMode: 'exact-prefix',
    badge: props.counts?.returns ?? undefined,
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8a5 5 0 105-5H5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M5 5L3 8l2 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
  {
    to: '/payments',
    label: 'Paiements',
    matchMode: 'exact-prefix',
    badge: props.counts?.payments_pending ?? undefined,
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="1" y="4" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 7h14" stroke="currentColor" stroke-width="1.4"/><circle cx="4.5" cy="10" r="1" fill="currentColor"/></svg>',
  },
  {
    to: '/deliveries',
    label: 'Livraisons',
    matchMode: 'exact-prefix',
    icon: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M1 10V5l4-3h6l4 3v5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><rect x="1" y="10" width="14" height="3" rx="1" stroke="currentColor" stroke-width="1.4"/><circle cx="4.5" cy="13.5" r="1.5" fill="currentColor"/><circle cx="11.5" cy="13.5" r="1.5" fill="currentColor"/></svg>',
  },
])

const visibleTabs = computed(() => {
  if (!props.allowedTabs) return tabs.value
  return tabs.value.filter(tab => props.allowedTabs!.includes(tab.to))
})

function isActive(tab: Tab): boolean {
  const path = route.path
  if (tab.matchMode === 'orders-root') {
    // Active on /orders and /orders/* but NOT on /orders/returns
    if (path === '/orders') return true
    if (path.startsWith('/orders/') && !path.startsWith('/orders/returns')) return true
    return false
  }
  // exact-prefix: active when path starts with tab.to
  return path === tab.to || path.startsWith(tab.to + '/')
}
</script>

<style scoped>
.sales-tabs {
  display: flex;
  gap: 2px;
  border-bottom: 2px solid var(--gray-100);
  margin-bottom: 20px;
  overflow-x: auto;
  scrollbar-width: none;
}
.sales-tabs::-webkit-scrollbar { display: none; }

.sales-tab {
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
.sales-tab:hover {
  color: var(--gray-800);
  border-bottom-color: var(--gray-300);
}
.sales-tab.active {
  color: var(--brand-primary);
  border-bottom-color: var(--brand-primary);
  font-weight: 600;
}
.tab-icon { display: flex; align-items: center; opacity: 0.75; }
.sales-tab.active .tab-icon { opacity: 1; }

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
.sales-tab.active .tab-badge {
  background: var(--brand-primary-bg, #ecfdf5);
  color: var(--brand-primary);
}

@media (max-width: 768px) {
  .sales-tab { padding: 8px 12px; }
  .tab-label { font-size: 0.8125rem; }
}
</style>
