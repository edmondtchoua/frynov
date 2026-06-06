<template>
  <div class="admin-layout">
    <!-- ── Sidebar ──────────────────────────────────────── -->
    <aside class="admin-sidebar">
      <div class="admin-sidebar__header">
        <div class="admin-brand">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect width="24" height="24" rx="6" fill="#1e293b"/>
            <path d="M6 17V7l5.5 7.5V7H18v10l-5.5-7.5V17H6Z" fill="white"/>
          </svg>
          <div>
            <span class="admin-brand__name">Frynov ERP</span>
            <span class="admin-brand__badge">Admin</span>
          </div>
        </div>
      </div>

      <nav class="admin-nav">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="admin-nav__item"
          active-class="admin-nav__item--active"
        >
          <span class="admin-nav__icon" v-html="item.icon"></span>
          <span class="admin-nav__label">{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="admin-sidebar__footer">
        <RouterLink to="/dashboard" class="admin-nav__item admin-nav__item--subtle">
          <span class="admin-nav__icon">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="admin-nav__label">Retour à l'app</span>
        </RouterLink>
        <button class="admin-nav__item admin-nav__item--subtle" @click="handleLogout">
          <span class="admin-nav__icon">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="admin-nav__label">Déconnexion</span>
        </button>
      </div>
    </aside>

    <!-- ── Main content ─────────────────────────────────── -->
    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar__title">{{ pageTitle }}</div>
        <div class="admin-topbar__user">
          <span class="admin-topbar__badge">Super Admin</span>
          <span class="admin-topbar__name">{{ user?.name }}</span>
        </div>
      </header>

      <main class="admin-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route   = useRoute()
const router  = useRouter()
const auth    = useAuthStore()
const user    = computed(() => auth.user)

const navItems = [
  {
    to: '/admin',
    label: 'Tableau de bord',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="1" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="1" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    to: '/admin/tenants',
    label: 'Tenants',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 14v-1.5A3.5 3.5 0 015.5 9h5A3.5 3.5 0 0114 12.5V14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    to: '/admin/modules',
    label: 'Modules ERP',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="1" width="6" height="6" stroke="currentColor" stroke-width="1.4"/><rect x="1" y="9" width="6" height="6" stroke="currentColor" stroke-width="1.4"/><path d="M9 12h6M12 9v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    to: '/admin/plans',
    label: 'Plans & Tarifs',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 6h14" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    to: '/admin/manual-payments',
    label: 'Paiements',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 6h14" stroke="currentColor" stroke-width="1.4"/><path d="M4 9.5h3M11 9.5h1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    to: '/admin/promotions',
    label: 'Promotions',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 8h12M8 2v12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="5" cy="5" r="2" stroke="currentColor" stroke-width="1.4"/><circle cx="11" cy="11" r="2" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    to: '/admin/country-rules',
    label: 'Règles pays',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 8h13" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.5c1.9 1.8 2.9 4.1 2.9 6.5S9.9 12.7 8 14.5C6.1 12.7 5.1 10.4 5.1 8S6.1 3.3 8 1.5z" stroke="currentColor" stroke-width="1.2"/></svg>',
  },
  {
    to: '/admin/audit',
    label: 'Journal d\'audit',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 4h8M4 8h6M4 12h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><rect x="1" y="1" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
]

const pageTitles: Record<string, string> = {
  'admin.dashboard':       'Tableau de bord',
  'admin.tenants':         'Gestion des tenants',
  'admin.tenants.detail':  'Détail tenant',
  'admin.modules':         'Modules ERP',
  'admin.plans':           'Plans & Tarifs',
  'admin.manual-payments': 'Paiements manuels',
  'admin.promotions':      'Promotions',
  'admin.country-rules':   'Règles pays',
  'admin.audit':           'Journal d\'audit',
}

const pageTitle = computed(() => pageTitles[route.name as string] ?? 'Administration')

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.admin-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: var(--gray-50, #f8fafc);
}

/* ── Sidebar ──────────────────────────────────────────────────── */
.admin-sidebar {
  width: 220px;
  flex-shrink: 0;
  background: #0f172a;
  color: #94a3b8;
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.08) transparent;
}

.admin-sidebar__header {
  padding: 1.25rem 1rem;
  border-bottom: 1px solid #1e293b;
}

.admin-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.admin-brand__name {
  display: block;
  font-size: 0.8125rem;
  font-weight: 700;
  color: #f1f5f9;
  line-height: 1.2;
}

.admin-brand__badge {
  display: inline-block;
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #ef4444;
  color: white;
  border-radius: 3px;
  padding: 1px 5px;
}

/* ── Nav ────────────────────────────────────────────────────────── */
.admin-nav {
  flex: 1;
  padding: 0.75rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.admin-nav__item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.625rem;
  border-radius: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #94a3b8;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
  background: none;
  border: none;
  cursor: pointer;
  width: 100%;
  text-align: left;
}

.admin-nav__item:hover {
  background: #1e293b;
  color: #f1f5f9;
}

.admin-nav__item--active {
  background: #1e293b;
  color: #38bdf8;
}

.admin-nav__item--subtle {
  color: #64748b;
  font-size: 0.75rem;
}

.admin-nav__icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.admin-sidebar__footer {
  padding: 0.75rem 0.5rem;
  border-top: 1px solid #1e293b;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* ── Main ───────────────────────────────────────────────────────── */
.admin-main {
  flex: 1;
  min-width: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.admin-topbar {
  background: white;
  border-bottom: 1px solid #e2e8f0;
  padding: 0 1.5rem;
  height: 56px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  /* position:sticky no longer needed — topbar is pinned by flex column */
  z-index: 10;
}

.admin-topbar__title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #0f172a;
}

.admin-topbar__user {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.admin-topbar__badge {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #fef2f2;
  color: #ef4444;
  border: 1px solid #fecaca;
  border-radius: 4px;
  padding: 2px 6px;
}

.admin-topbar__name {
  font-size: 0.8125rem;
  color: #475569;
}

.admin-content {
  flex: 1;
  padding: 1.5rem;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: #334155 transparent;
}
</style>
