<template>
  <div>

    <div class="page-header">
      <h2>Paramètres</h2>
      <p class="page-subtitle">Gérez votre espace de travail et vos préférences</p>
    </div>

    <div class="settings-layout">

      <!-- Sidebar nav -->
      <nav class="settings-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          class="settings-nav-item"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          <component :is="tab.icon" class="nav-icon" />
          {{ tab.label }}
        </button>
      </nav>

      <!-- Content panels -->
      <div class="settings-panel">

        <!-- Company -->
        <section v-if="activeTab === 'company'">
          <div class="panel-header">
            <h3>Informations entreprise</h3>
            <p>Ces informations apparaissent sur vos factures et documents.</p>
          </div>
          <div class="coming-soon">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
              <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
              <path d="M13 20h14M20 13v14" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p>Configuration entreprise — disponible dans la prochaine version.</p>
          </div>
        </section>

        <!-- Team -->
        <section v-else-if="activeTab === 'team'">
          <div class="panel-header">
            <h3>Équipe & permissions</h3>
            <p>Invitez des membres et gérez leurs accès.</p>
          </div>
          <div class="coming-soon">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
              <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-secondary-light)"/>
              <circle cx="16" cy="17" r="4" stroke="var(--brand-secondary)" stroke-width="2"/>
              <path d="M8 30c0-4.418 3.582-8 8-8" stroke="var(--brand-secondary)" stroke-width="2" stroke-linecap="round"/>
              <circle cx="26" cy="17" r="4" stroke="var(--brand-secondary)" stroke-width="2"/>
              <path d="M24 30c0-4.418 3.582-8 8-8" stroke="var(--brand-secondary)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p>Gestion d'équipe — disponible dans la prochaine version.</p>
          </div>
        </section>

        <!-- Billing -->
        <section v-else-if="activeTab === 'billing'">
          <div class="panel-header">
            <h3>Abonnement & facturation</h3>
            <p>Votre plan actuel et options de mise à niveau.</p>
          </div>

          <div class="plan-card">
            <div class="plan-badge">Plan actuel</div>
            <div class="plan-name">Starter</div>
            <div class="plan-price">Gratuit <span>pendant la période de bêta</span></div>
            <ul class="plan-features">
              <li>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8l4 4 6-7" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Jusqu'à 500 produits
              </li>
              <li>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8l4 4 6-7" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                3 utilisateurs inclus
              </li>
              <li>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8l4 4 6-7" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Support par email
              </li>
            </ul>
          </div>
        </section>

        <!-- Integrations -->
        <section v-else-if="activeTab === 'integrations'">
          <div class="panel-header">
            <h3>Intégrations</h3>
            <p>Connectez Nexora ERP à vos outils existants.</p>
          </div>
          <div class="coming-soon">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
              <rect x="4" y="4" width="32" height="32" rx="8" fill="#fdf4ff"/>
              <path d="M14 20h12M26 20l-4-4m4 4l-4 4" stroke="#9333ea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <p>Connecteurs Shopify, WooCommerce, Mobile Money — Phase 3.</p>
          </div>
        </section>

        <!-- Notifications -->
        <section v-else-if="activeTab === 'notifications'">
          <div class="panel-header">
            <h3>Notifications</h3>
            <p>Configurez vos alertes et rappels.</p>
          </div>
          <div class="coming-soon">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
              <rect x="4" y="4" width="32" height="32" rx="8" fill="#fff7ed"/>
              <path d="M20 10a7 7 0 017 7v4l2 3H11l2-3v-4a7 7 0 017-7z" stroke="#ea580c" stroke-width="2" stroke-linejoin="round"/>
              <path d="M17.5 27a2.5 2.5 0 005 0" stroke="#ea580c" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p>Notifications email et push — disponible dans la prochaine version.</p>
          </div>
        </section>

      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, defineComponent, h } from 'vue'

const activeTab = ref('company')

// Icon components
const IconCompany = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('path', { d: 'M2 14V5l6-3 6 3v9H2z', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M6 14v-4h4v4', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
  ]),
})

const IconTeam = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('circle', { cx: 6, cy: 6, r: 2.5, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M2 14c0-2.21 1.79-4 4-4s4 1.79 4 4', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
    h('path', { d: 'M11 4.5a2 2 0 010 4M13.5 14c0-1.93-1.27-3.57-3-4.09', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
  ]),
})

const IconBilling = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('rect', { x: 1, y: 4, width: 14, height: 9, rx: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M1 7h14', stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M4 10.5h2M10 10.5h2', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
  ]),
})

const IconIntegrations = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('circle', { cx: 4, cy: 8, r: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('circle', { cx: 12, cy: 8, r: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M6 8h4', stroke: 'currentColor', 'stroke-width': '1.4' }),
  ]),
})

const IconNotifications = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('path', { d: 'M8 2a5 5 0 015 5v3l1.5 2H1.5L3 10V7a5 5 0 015-5z', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M6.5 13a1.5 1.5 0 003 0', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
  ]),
})

const tabs = [
  { id: 'company',       label: 'Entreprise',    icon: IconCompany },
  { id: 'team',          label: 'Équipe',         icon: IconTeam },
  { id: 'billing',       label: 'Abonnement',     icon: IconBilling },
  { id: 'integrations',  label: 'Intégrations',   icon: IconIntegrations },
  { id: 'notifications', label: 'Notifications',  icon: IconNotifications },
]
</script>

<style scoped>
.page-subtitle {
  color: var(--gray-500);
  font-size: var(--text-sm);
  margin-top: 0.25rem;
}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1.5rem;
  align-items: flex-start;
}

@media (max-width: 768px) {
  .settings-layout {
    grid-template-columns: 1fr;
  }

  .settings-nav {
    display: flex;
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    gap: 0.25rem !important;
    border: none !important;
    -webkit-overflow-scrolling: touch;
  }

  .settings-nav-item {
    white-space: nowrap;
    flex-shrink: 0;
  }
}

/* ── Sidebar nav ─────────────────────────────────────────────────────────── */
.settings-nav {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  position: sticky;
  top: calc(var(--topbar-height) + 1rem);
}

.settings-nav-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 0.875rem;
  border-radius: var(--radius-md);
  border: none;
  background: none;
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--gray-600);
  text-align: left;
  transition: background 0.12s, color 0.12s;
  width: 100%;
}
.settings-nav-item:hover {
  background: var(--gray-50);
  color: var(--gray-900);
}
.settings-nav-item.active {
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
}

.nav-icon { flex-shrink: 0; }

/* ── Panel ───────────────────────────────────────────────────────────────── */
.settings-panel {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 2rem;
  min-height: 400px;
}

.panel-header {
  margin-bottom: 2rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--gray-100);
}
.panel-header h3 {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.3rem;
}
.panel-header p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

/* ── Coming soon state ───────────────────────────────────────────────────── */
.coming-soon {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 4rem 2rem;
  text-align: center;
}
.coming-soon p {
  font-size: var(--text-sm);
  color: var(--gray-400);
  max-width: 300px;
  margin: 0;
}

/* ── Plan card ───────────────────────────────────────────────────────────── */
.plan-card {
  border: 2px solid var(--brand-primary);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  max-width: 360px;
  background: var(--brand-primary-bg);
}
.plan-badge {
  display: inline-block;
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--brand-primary-dark);
  background: white;
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-full);
  margin-bottom: 0.75rem;
}
.plan-name {
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.25rem;
}
.plan-price {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--brand-primary-dark);
  margin-bottom: 1.25rem;
}
.plan-price span {
  font-size: var(--text-sm);
  font-weight: 400;
  color: var(--gray-500);
}
.plan-features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-sm);
  color: var(--gray-700);
}
</style>
