<template>
  <div class="app-layout">
    <!-- ── Sidebar ─────────────────────────────────────── -->
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <span class="logo-icon">🌍</span>
        <span v-if="!sidebarCollapsed" class="logo-text">ETech <strong>ERP</strong></span>
        <button class="collapse-btn" @click="sidebarCollapsed = !sidebarCollapsed">
          {{ sidebarCollapsed ? '›' : '‹' }}
        </button>
      </div>

      <nav class="sidebar-nav">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="nav-item"
          active-class="nav-item--active"
        >
          <span class="nav-icon">{{ item.icon }}</span>
          <span v-if="!sidebarCollapsed" class="nav-label">{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <button class="logout-btn" @click="handleLogout">
          <span>🚪</span>
          <span v-if="!sidebarCollapsed">Déconnexion</span>
        </button>
      </div>
    </aside>

    <!-- ── Main ───────────────────────────────────────── -->
    <div class="main-wrapper">
      <header class="topbar">
        <div class="topbar-left">
          <h1 class="page-title">{{ pageTitle }}</h1>
        </div>
        <div class="topbar-right">
          <span class="tenant-badge">{{ tenantName }}</span>
          <span class="user-avatar">{{ userInitials }}</span>
        </div>
      </header>

      <main class="page-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const sidebarCollapsed = ref(false)

const navItems = [
  { name: 'dashboard',   to: '/dashboard',   icon: '📊', label: 'Tableau de bord' },
  { name: 'catalog',     to: '/catalog',     icon: '📦', label: 'Catalogue' },
  { name: 'inventory',   to: '/inventory',   icon: '🏪', label: 'Stock' },
  { name: 'orders',      to: '/orders',      icon: '🛒', label: 'Commandes' },
  { name: 'customers',   to: '/customers',   icon: '👥', label: 'Clients' },
]

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    dashboard:               'Tableau de bord',
    'catalog.products':      'Produits',
    'catalog.categories':    'Catégories',
    'inventory.stock':       'Stock',
    'inventory.alerts':      'Alertes stock',
    'orders.list':           'Commandes',
    'orders.create':         'Nouvelle commande',
    'customers.list':        'Clients',
  }
  return titles[String(route.name)] ?? 'ETech ERP'
})

const tenantName   = computed(() => auth.user?.tenant?.name ?? '')
const userInitials = computed(() => {
  const name = auth.user?.name ?? ''
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
})

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}

/* ── Sidebar ─────────────────────────────────────────── */
.sidebar {
  width: 240px;
  background: #064e3b;
  color: white;
  display: flex;
  flex-direction: column;
  transition: width 0.2s ease;
  flex-shrink: 0;
}

.sidebar.collapsed { width: 64px; }

.sidebar-header {
  display: flex;
  align-items: center;
  padding: 1.25rem 1rem;
  gap: 0.6rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  min-height: 64px;
}

.logo-icon { font-size: 1.4rem; flex-shrink: 0; }

.logo-text {
  font-size: 1.1rem;
  white-space: nowrap;
  overflow: hidden;
}

.logo-text strong { color: #6ee7b7; }

.collapse-btn {
  margin-left: auto;
  background: none;
  border: none;
  color: rgba(255,255,255,0.6);
  cursor: pointer;
  font-size: 1.2rem;
  padding: 0.25rem;
  line-height: 1;
  flex-shrink: 0;
}

.collapse-btn:hover { color: white; }

.sidebar-nav {
  flex: 1;
  padding: 0.75rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.75rem;
  border-radius: 8px;
  color: rgba(255,255,255,0.75);
  text-decoration: none;
  font-size: 0.9rem;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
  overflow: hidden;
}

.nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
.nav-item--active { background: rgba(255,255,255,0.15); color: white; font-weight: 600; }

.nav-icon { font-size: 1.1rem; flex-shrink: 0; }
.nav-label { overflow: hidden; }

.sidebar-footer {
  padding: 0.75rem 0.5rem;
  border-top: 1px solid rgba(255,255,255,0.1);
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.65rem 0.75rem;
  border: none;
  background: none;
  color: rgba(255,255,255,0.6);
  cursor: pointer;
  border-radius: 8px;
  font-size: 0.9rem;
  text-align: left;
  transition: background 0.15s, color 0.15s;
}

.logout-btn:hover { background: rgba(255,255,255,0.1); color: white; }

/* ── Main ────────────────────────────────────────────── */
.main-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.topbar {
  height: 64px;
  background: white;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  justify-content: space-between;
  flex-shrink: 0;
}

.page-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.tenant-badge {
  font-size: 0.8rem;
  background: #ecfdf5;
  color: #065f46;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-weight: 500;
}

.user-avatar {
  width: 36px;
  height: 36px;
  background: #059669;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
}

.page-content {
  flex: 1;
  padding: 1.5rem;
  overflow-y: auto;
}
</style>
