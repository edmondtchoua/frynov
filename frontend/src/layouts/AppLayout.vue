<template>
  <div class="app-layout">

    <!-- ── Mobile overlay ────────────────────────────────── -->
    <div
      v-if="mobileMenuOpen"
      class="sidebar-overlay"
      @click="mobileMenuOpen = false"
    ></div>

    <!-- ── Sidebar ─────────────────────────────────────── -->
    <aside
      class="sidebar"
      :class="{
        'sidebar--collapsed': sidebarCollapsed,
        'sidebar--mobile-open': mobileMenuOpen,
      }"
    >
      <!-- Header -->
      <div class="sidebar-header">
        <FrynovLogo
          v-if="!sidebarCollapsed"
          variant="dark"
          :show-text="true"
          :show-erp="true"
        />
        <div v-else class="logo-collapsed">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <rect width="20" height="20" rx="5" fill="url(#lg2)"/>
            <path d="M5 14.5V5.5L9.5 11.5V5.5H15V14.5L10.5 8.5V14.5H5Z" fill="white"/>
            <defs>
              <linearGradient id="lg2" x1="0" y1="0" x2="20" y2="20" gradientUnits="userSpaceOnUse">
                <stop stop-color="#10b981"/>
                <stop offset="1" stop-color="#3b82f6"/>
              </linearGradient>
            </defs>
          </svg>
        </div>

        <!-- Desktop collapse button -->
        <button
          class="collapse-btn hide-mobile"
          :title="sidebarCollapsed ? 'Expand' : 'Collapse'"
          @click="sidebarCollapsed = !sidebarCollapsed"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path
              :d="sidebarCollapsed ? 'M6 3l5 5-5 5' : 'M10 3l-5 5 5 5'"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>

        <!-- Mobile close button -->
        <button class="collapse-btn show-mobile-only" @click="mobileMenuOpen = false">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <!-- Nav items -->
      <nav class="sidebar-nav">
        <div class="nav-section-label" v-if="!sidebarCollapsed">Principal</div>

        <template v-for="item in mainNavItems" :key="item.name">
          <!-- Item WITH children → collapsible group -->
          <template v-if="item.children">
            <!-- Parent toggle -->
            <button
              class="nav-item nav-group-toggle"
              :class="{ 'nav-item--active': isGroupActive(item) }"
              :title="sidebarCollapsed ? item.label : ''"
              @click="toggleGroup(item.name)"
            >
              <span class="nav-icon" v-html="item.icon"></span>
              <span v-if="!sidebarCollapsed" class="nav-label">{{ item.label }}</span>
              <svg
                v-if="!sidebarCollapsed"
                class="nav-chevron"
                :class="{ open: openGroups.has(item.name) }"
                width="12" height="12" viewBox="0 0 12 12" fill="none"
              >
                <path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </button>
            <!-- Sub-items (shown when group is open or route is active) -->
            <div
              v-if="!sidebarCollapsed && (openGroups.has(item.name) || isGroupActive(item))"
              class="nav-sub"
            >
              <RouterLink
                v-for="child in item.children"
                :key="child.name"
                :to="child.to"
                class="nav-sub-item"
                active-class="nav-sub-item--active"
                :exact-active-class="child.to === '/catalog' ? 'nav-sub-item--active' : ''"
                @click="mobileMenuOpen = false"
              >
                {{ child.label }}
              </RouterLink>
            </div>
            <!-- Collapsed: show parent link only -->
            <RouterLink
              v-if="sidebarCollapsed"
              :to="item.to"
              class="nav-item"
              active-class="nav-item--active"
              :title="item.label"
              @click="mobileMenuOpen = false"
            >
              <span class="nav-icon" v-html="item.icon"></span>
            </RouterLink>
          </template>

          <!-- Item WITHOUT children → simple link -->
          <RouterLink
            v-else
            :to="item.to"
            class="nav-item"
            active-class="nav-item--active"
            :title="sidebarCollapsed ? item.label : ''"
            @click="mobileMenuOpen = false"
          >
            <span class="nav-icon" v-html="item.icon"></span>
            <span v-if="!sidebarCollapsed" class="nav-label">{{ item.label }}</span>
            <span v-if="item.name === 'marketplace.listings' && marketplaceAlertCount > 0" class="nav-alert-badge">
              {{ marketplaceAlertCount }}
            </span>
          </RouterLink>
        </template>

        <div v-if="!sidebarCollapsed" class="nav-section-label" style="margin-top: 0.75rem;">Configuration</div>
        <RouterLink
          to="/settings"
          class="nav-item"
          active-class="nav-item--active"
          :title="sidebarCollapsed ? 'Paramètres' : ''"
          @click="mobileMenuOpen = false"
        >
          <span class="nav-icon">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M8 10a2 2 0 100-4 2 2 0 000 4z" stroke="currentColor" stroke-width="1.4"/>
              <path d="M12.7 6.3l.8-1.4-1.4-1.4-1.4.8a4.9 4.9 0 00-1.3-.5L9 2H7l-.4 1.8a4.9 4.9 0 00-1.3.5l-1.4-.8L2.5 4.9l.8 1.4a4.9 4.9 0 000 1.4l-.8 1.4 1.4 1.4 1.4-.8c.4.2.8.4 1.3.5L7 12h2l.4-1.8c.5-.1.9-.3 1.3-.5l1.4.8 1.4-1.4-.8-1.4c.1-.5.1-.9 0-1.4z" stroke="currentColor" stroke-width="1.4"/>
            </svg>
          </span>
          <span v-if="!sidebarCollapsed" class="nav-label">Paramètres</span>
        </RouterLink>
      </nav>

      <!-- Footer — user + profile + logout -->
      <div class="sidebar-footer">
        <!-- Expanded: avatar + name + tenant + profile link -->
        <div v-if="!sidebarCollapsed" class="user-info">
          <RouterLink to="/profile" class="user-avatar-sm user-avatar-link" :title="'Profil — ' + (auth.user?.name ?? '')">
            {{ userInitials }}
          </RouterLink>
          <div class="user-meta">
            <RouterLink to="/profile" class="user-name user-name-link">{{ auth.user?.name ?? '…' }}</RouterLink>
            <div class="user-tenant">{{ tenantName }}</div>
          </div>
        </div>
        <!-- Collapsed: just the avatar -->
        <RouterLink
          v-else
          to="/profile"
          class="user-avatar-sm"
          :title="auth.user?.name"
          style="margin: 0 auto;"
        >{{ userInitials }}</RouterLink>

        <RouterLink
          v-if="!sidebarCollapsed"
          to="/profile"
          class="nav-item"
          active-class="nav-item--active"
        >
          <span class="nav-icon">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <circle cx="8" cy="5.5" r="3" stroke="currentColor" stroke-width="1.4"/>
              <path d="M2 14c0-3.3 2.7-5 6-5s6 1.7 6 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="nav-label">Mon profil</span>
        </RouterLink>

        <button class="logout-btn" :title="sidebarCollapsed ? 'Déconnexion' : ''" @click="handleLogout">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span v-if="!sidebarCollapsed">Déconnexion</span>
        </button>
      </div>
    </aside>

    <!-- ── Main ───────────────────────────────────────────── -->
    <div class="main-wrapper">
      <header class="topbar">
        <!-- Mobile hamburger -->
        <button class="hamburger show-mobile-only" @click="mobileMenuOpen = true" aria-label="Open menu">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </button>

        <!-- Page title -->
        <h1 class="page-title">{{ pageTitle }}</h1>

        <!-- Topbar right -->
        <div class="topbar-right">
          <span class="tenant-chip hide-mobile">{{ tenantName }}</span>
          <!-- Notification bell with toasts -->
          <NotificationCenter />
          <div class="user-avatar-top" :title="auth.user?.name">{{ userInitials }}</div>
        </div>
      </header>

      <main class="page-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import FrynovLogo from '@/shared/components/FrynovLogo.vue'
import NotificationCenter from '@/shared/components/NotificationCenter.vue'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

// Marketplace alert count is now handled by NotificationCenter component
// which uses the useNotifications() composable (polls every 30s)
import { useNotifications } from '@/composables/useNotifications'
const { unreadCount: marketplaceAlertCount } = useNotifications()

const sidebarCollapsed = ref(false)
const mobileMenuOpen   = ref(false)

// Collapsible nav groups — auto-open when current route is inside the group
const openGroups = reactive(new Set<string>())

function isGroupActive(item: { to: string; children?: { to: string }[] }): boolean {
  return route.path === item.to || route.path.startsWith(item.to + '/')
}

function toggleGroup(name: string): void {
  if (openGroups.has(name)) {
    openGroups.delete(name)
  } else {
    openGroups.add(name)
  }
}

// Auto-open the group of the current route on mount and route change
function syncOpenGroups(): void {
  mainNavItems.forEach(item => {
    if (item.children && isGroupActive(item)) {
      openGroups.add(item.name)
    }
  })
}

// We call syncOpenGroups after mainNavItems is defined (below)

const mainNavItems = [
  {
    name: 'dashboard',
    to: '/dashboard',
    label: 'Tableau de bord',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="1" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="1" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    name: 'catalog',
    to: '/catalog',
    label: 'Catalogue',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2zM9 9h5v5H9z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>',
    children: [
      { name: 'catalog.products',   to: '/catalog',            label: 'Produits' },
      { name: 'catalog.categories', to: '/catalog/categories', label: 'Catégories' },
      { name: 'catalog.variants',   to: '/catalog/variants',   label: 'Variantes' },
      { name: 'catalog.labels',     to: '/catalog/labels',     label: 'Étiquettes' },
    ],
  },
  {
    name: 'inventory',
    to: '/inventory',
    label: 'Inventaire',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 5l6-3 6 3v6l-6 3-6-3V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M8 2v12M2 5l6 3 6-3" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    name: 'orders',
    to: '/orders',
    label: 'Commandes',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 2h12v10a1 1 0 01-1 1H3a1 1 0 01-1-1V2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5 6h6M5 9h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    name: 'customers',
    to: '/customers',
    label: 'Clients',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="6" cy="5" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M1 14c0-3.3 2.2-5 5-5s5 1.7 5 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M12 7a2 2 0 010-4M15 14c0-2.2-1.3-3.5-3-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    name: 'suppliers',
    to: '/suppliers',
    label: 'Fournisseurs',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 12V5l5-3 5 3v7" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 16v-4h4v4" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M11 12h4V7l-4-2" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>',
  },
  {
    name: 'catalog.labels',
    to: '/catalog/labels',
    label: 'Étiquettes',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 4a1 1 0 011-1h5.586a1 1 0 01.707.293l3.414 3.414A1 1 0 0114 7.414V12a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" stroke="currentColor" stroke-width="1.4"/><circle cx="6" cy="8" r="1" fill="currentColor"/></svg>',
  },
  {
    name: 'inventory.warehouses',
    to: '/inventory/warehouses',
    label: 'Entrepôts',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 14V6l6-4 6 4v8H2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 14v-4h4v4" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>',
  },
  {
    name: 'marketplace.listings',
    to: '/marketplace',
    label: 'Marketplace',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/><path d="M2 8h12M8 2c-1.5 2-2 4-2 6s.5 4 2 6M8 2c1.5 2 2 4 2 6s-.5 4-2 6" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
  {
    name: 'import',
    to: '/import/history',
    label: 'Import / Export',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2v8M5 7l3 3 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 11v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    name: 'reports.sales',
    to: '/reports/sales',
    label: 'Rapport ventes',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="10" width="3" height="4" rx="1" fill="currentColor" opacity=".5"/><rect x="6" y="7" width="3" height="7" rx="1" fill="currentColor" opacity=".7"/><rect x="10" y="3" width="3" height="11" rx="1" fill="currentColor"/></svg>',
  },
  {
    name: 'reports.stock',
    to: '/reports/stock',
    label: 'Rapport stock',
    icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 4l6-2 6 2v8l-6 2-6-2V4z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 4l6 2 6-2M8 6v8" stroke="currentColor" stroke-width="1.4"/></svg>',
  },
]

const pageTitles: Record<string, string> = {
  dashboard:                'Tableau de bord',
  'catalog.products':       'Catalogue',
  'catalog.products.create':'Nouveau produit',
  'catalog.products.show':  'Produit',
  'catalog.categories':     'Catégories',
  'inventory.stock':        'Inventaire',
  'inventory.alerts':       'Alertes stock',
  'inventory.movements':    'Mouvements',
  'inventory.warehouses':  'Entrepôts & Boutiques',
  'marketplace.listings':  'Marketplace',
  'orders.list':            'Commandes',
  'orders.create':          'Nouvelle commande',
  'orders.show':            'Commande',
  'customers.list':         'Clients',
  'customers.show':         'Client',
  'suppliers.list':         'Fournisseurs',
  'catalog.labels':         'Impression d\'étiquettes',
  'import.history':         'Import / Export',
  'import.wizard':          'Nouvel import',
  'payments.list':          'Paiements',
  'deliveries.list':        'Livraisons',
  'reports.sales':          'Rapport des ventes',
  'reports.stock':          'Rapport de stock',
  settings:                 'Paramètres',
}

const pageTitle    = computed(() => pageTitles[String(route.name)] ?? 'Frynov ERP')
const tenantName   = computed(() => auth.user?.tenant?.name ?? '')
const userInitials = computed(() => {
  const name = auth.user?.name ?? ''
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || 'U'
})

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

// Auto-open active group on mount
syncOpenGroups()
</script>

<style scoped>
/* ── Layout — full-viewport, no body scroll ──────────────── */
.app-layout {
  display: flex;
  height: 100vh;        /* exact viewport height */
  overflow: hidden;     /* prevent body from ever scrolling */
  background: var(--gray-50);
}

/* ── Sidebar — sticky column ─────────────────────────────── */
.sidebar {
  width: var(--sidebar-width);
  height: 100vh;        /* always full height */
  background: var(--sidebar-bg);
  color: white;
  display: flex;
  flex-direction: column;
  transition: width 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  flex-shrink: 0;
  z-index: 40;
  position: sticky;     /* fixes sidebar in place on desktop */
  top: 0;
  overflow: hidden;     /* clip content during collapse animation */
}

.sidebar--collapsed { width: var(--sidebar-collapsed-width); }

.sidebar-header {
  display: flex;
  align-items: center;
  padding: 1.1rem 1rem;
  gap: 0.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  min-height: var(--topbar-height);
}

.logo-collapsed {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.collapse-btn {
  margin-left: auto;
  background: none;
  border: none;
  color: rgba(255,255,255,0.4);
  cursor: pointer;
  padding: 0.35rem;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}
.collapse-btn:hover { background: rgba(255,255,255,0.08); color: white; }

/* .sidebar-nav — defined later in the file */

.nav-section-label {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: rgba(255,255,255,0.3);
  padding: 0.25rem 0.75rem 0.4rem;
  margin-top: 0.25rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  border-radius: var(--radius-md);
  color: rgba(255,255,255,0.65);
  text-decoration: none;
  font-size: var(--text-sm);
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
  overflow: hidden;
}

.nav-item:hover { background: rgba(255,255,255,0.07); color: white; }

.nav-item--active {
  background: rgba(16,185,129,0.15);
  color: #6ee7b7;
}
.nav-item--active:hover { background: rgba(16,185,129,0.2); }

/* ── Collapsible nav groups ──────────────────────────────────────── */
.nav-group-toggle {
  width: 100%;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
}
.nav-chevron {
  margin-left: auto;
  flex-shrink: 0;
  color: rgba(255,255,255,0.4);
  transition: transform 0.2s;
}
.nav-chevron.open { transform: rotate(180deg); }

.nav-sub {
  padding-left: 8px;
  margin-bottom: 2px;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.nav-sub-item {
  display: block;
  padding: 5px 10px 5px 28px;
  border-radius: var(--radius-md);
  color: rgba(255,255,255,0.5);
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
  border-left: 2px solid rgba(255,255,255,0.08);
  white-space: nowrap;
}
.nav-sub-item:hover { color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.05); }
.nav-sub-item--active {
  color: #6ee7b7;
  border-left-color: #6ee7b7;
  background: rgba(16,185,129,0.08);
  font-weight: 600;
}

.nav-icon {
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.nav-label { overflow: hidden; text-overflow: ellipsis; }

.nav-alert-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  font-size: 0.6rem;
  font-weight: 700;
  background: #ef4444;
  color: white;
  margin-left: auto;
}

/* Footer */
.sidebar-footer {
  padding: 0.75rem 0.5rem;
  border-top: 1px solid rgba(255,255,255,0.06);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.4rem 0.5rem;
}

.user-avatar-sm {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
}

.user-meta { overflow: hidden; }
.user-name { font-size: var(--text-xs); font-weight: 600; color: rgba(255,255,255,0.9); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-name-link { text-decoration: none; transition: color 0.15s; }
.user-name-link:hover { color: white; }
.user-avatar-link { text-decoration: none; transition: opacity 0.15s; }
.user-avatar-link:hover { opacity: 0.85; }
.user-tenant { font-size: 0.65rem; color: rgba(255,255,255,0.4); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  padding: 0.55rem 0.75rem;
  border: none;
  background: none;
  color: rgba(255,255,255,0.45);
  cursor: pointer;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  text-align: left;
  transition: background 0.15s, color 0.15s;
}
.logout-btn:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }

/* Nav scrolls within the sidebar if there are many items */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0.5rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.08) transparent;
}

/* ── Main wrapper — fills remaining width, controls its own scroll ── */
.main-wrapper {
  flex: 1;
  min-width: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;     /* topbar stays fixed, content scrolls */
}

/* ── Topbar — naturally pinned at top (no position:sticky needed) ─── */
.topbar {
  height: var(--topbar-height);
  background: white;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  gap: 1rem;
  flex-shrink: 0;       /* never shrinks */
  z-index: 20;
  box-shadow: 0 1px 0 var(--gray-200);
}

.hamburger {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.4rem;
  color: var(--gray-600);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
}
.hamburger:hover { background: var(--gray-100); color: var(--gray-900); }

.page-title {
  flex: 1;
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.tenant-chip {
  font-size: var(--text-xs);
  background: var(--brand-primary-bg);
  color: #065f46;
  padding: 0.2rem 0.7rem;
  border-radius: 20px;
  font-weight: 500;
  white-space: nowrap;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-avatar-top {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
  cursor: default;
}

/* ── Page content — the only scrollable region ───────────── */
.page-content {
  flex: 1;
  padding: 1.5rem;
  overflow-y: auto;
  overflow-x: hidden;
  /* Smooth scroll feel */
  scroll-behavior: smooth;
  /* Custom thin scrollbar */
  scrollbar-width: thin;
  scrollbar-color: var(--gray-300) transparent;
}

.page-content::-webkit-scrollbar       { width: 6px; }
.page-content::-webkit-scrollbar-track { background: transparent; }
.page-content::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 3px; }
.page-content::-webkit-scrollbar-thumb:hover { background: var(--gray-400); }

/* ── Overlay (mobile) ────────────────────────────────────── */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 39;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 768px) {
  .app-layout {
    height: auto;       /* let mobile layout grow */
    overflow: visible;
  }

  .main-wrapper {
    height: 100dvh;     /* use dynamic viewport on mobile */
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100dvh;
    width: var(--sidebar-width) !important;
    transform: translateX(-100%);
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1),
                box-shadow 0.28s ease;
    box-shadow: none;
  }

  .sidebar--mobile-open {
    transform: translateX(0);
    box-shadow: var(--shadow-lg);
  }

  .page-content {
    padding: 1rem;
  }

  .topbar {
    padding: 0 1rem;
  }
}

@media (min-width: 769px) {
  .sidebar-overlay { display: none; }
}
</style>
