<template>
  <div class="tenant-detail">

    <!-- Back + title -->
    <div class="detail-header">
      <RouterLink to="/admin/tenants" class="back-link">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Tous les tenants
      </RouterLink>

      <div class="detail-title-row">
        <div>
          <h2 class="detail-name">{{ tenant?.name ?? '…' }}</h2>
          <span class="detail-slug">{{ tenant?.slug }}</span>
        </div>
        <div class="header-actions" v-if="tenant">
          <span :class="`status-badge status-badge--${tenant.status}`">{{ tenant.status }}</span>
          <button v-if="tenant.status === 'active'"    class="btn-action btn-warn" @click="doSuspend">Suspendre</button>
          <button v-if="tenant.status === 'suspended'" class="btn-action btn-ok"   @click="doReactivate">Réactiver</button>
        </div>
      </div>
    </div>

    <!-- Loading splash -->
    <div v-if="loading" class="state-msg">Chargement…</div>

    <template v-else>

      <!-- Info row: details + subscription side by side -->
      <div class="detail-row">

        <!-- Tenant info -->
        <div class="detail-card">
          <div class="detail-card-title">Informations</div>
          <dl class="info-dl">
            <dt>Nom</dt>           <dd>{{ tenant!.name }}</dd>
            <dt>Slug</dt>          <dd class="mono">{{ tenant!.slug }}</dd>
            <dt>Domaine</dt>       <dd>{{ tenant!.domain ?? '—' }}</dd>
            <dt>Plan</dt>          <dd><span class="plan-chip">{{ tenant!.plan }}</span></dd>
            <dt>Créé le</dt>       <dd>{{ fmtDate(tenant!.created_at) }}</dd>
          </dl>
        </div>

        <!-- Subscription + change plan -->
        <div class="detail-card">
          <div class="detail-card-title">Abonnement</div>

          <div v-if="subscription">
            <dl class="info-dl">
              <dt>Plan</dt>
              <dd>{{ subscription.plan?.name ?? tenant!.plan }}</dd>
              <dt>Statut</dt>
              <dd><span :class="`sub-badge sub-badge--${subscription.status}`">{{ subscription.status }}</span></dd>
              <template v-if="subscription.trial_ends_at">
                <dt>Fin essai</dt><dd>{{ fmtDate(subscription.trial_ends_at) }}</dd>
              </template>
              <template v-if="subscription.current_period_end">
                <dt>Période</dt><dd>{{ fmtDate(subscription.current_period_end) }}</dd>
              </template>
            </dl>

            <div class="change-plan-row">
              <select v-model="selectedPlanCode" class="plan-select">
                <option v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }}</option>
              </select>
              <button
                class="btn-action btn-primary"
                :disabled="changingPlan || selectedPlanCode === tenant!.plan"
                @click="doChangePlan"
              >
                {{ changingPlan ? '…' : 'Changer' }}
              </button>
            </div>
          </div>

          <div v-else class="no-data">Aucun abonnement trouvé.</div>
        </div>

      </div>

      <!-- Users -->
      <div class="detail-card full-width">
        <div class="detail-card-title">
          Utilisateurs
          <span class="count-chip">{{ users.length }}</span>
        </div>
        <table class="mini-table" v-if="users.length">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Email</th>
              <th>Super admin</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>{{ u.name }}</td>
              <td class="mono">{{ u.email }}</td>
              <td class="center">{{ u.is_super_admin ? '✓' : '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="no-data">Aucun utilisateur.</div>
      </div>

      <!-- Modules -->
      <div class="detail-card full-width">
        <div class="detail-card-title">
          Modules ERP
          <span class="count-chip">{{ modules.length }}</span>
        </div>

        <div v-if="loadingModules" class="state-msg">Chargement…</div>

        <div v-else class="modules-grid">
          <div v-for="mod in modules" :key="mod.code" class="mod-row">
            <div class="mod-info">
              <span class="mod-name">{{ mod.name }}</span>
              <span class="mod-code mono">{{ mod.code }}</span>
            </div>
            <div class="mod-actions">
              <span class="mod-badge" :class="mod.tenant_active ? 'mod-badge--on' : 'mod-badge--off'">
                {{ mod.tenant_active ? 'Actif' : 'Inactif' }}
              </span>
              <span v-if="mod.is_core" class="core-chip">Core</span>
              <button
                v-else
                class="btn-action btn-xs"
                :class="mod.tenant_active ? 'btn-warn' : 'btn-ok'"
                @click="toggleModule(mod)"
              >
                {{ mod.tenant_active ? 'Désactiver' : 'Activer' }}
              </button>
            </div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfirm } from '@/composables/useConfirm'
import { formatDate } from '@/shared/utils/date'
import { useRoute, RouterLink } from 'vue-router'
import { adminService, type AdminTenant, type AdminPlan } from '../services/adminService'
import type { ErpModule } from '@/modules/auth/types'

// ── Route param ───────────────────────────────────────────────────────────────
const route = useRoute()
const id    = route.params.id as string

// ── State ─────────────────────────────────────────────────────────────────────
const loading        = ref(true)
const loadingModules = ref(true)
const changingPlan   = ref(false)

const tenant          = ref<AdminTenant | null>(null)
const subscription    = ref<any>(null)
const users           = ref<AdminTenant['users']>([])
const modules         = ref<ErpModule[]>([])
const plans           = ref<AdminPlan[]>([])
const selectedPlanCode = ref('')

// ── Loaders ───────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const [detail, planList] = await Promise.all([
      adminService.getTenant(id),
      adminService.getPlans(),
    ])
    tenant.value          = detail.tenant
    subscription.value    = detail.subscription
    users.value           = detail.tenant.users ?? []
    plans.value           = planList
    selectedPlanCode.value = detail.tenant.plan
  } finally {
    loading.value = false
  }
}

async function loadModules() {
  loadingModules.value = true
  try {
    modules.value = await adminService.getTenantModules(id)
  } finally {
    loadingModules.value = false
  }
}

// ── Actions ───────────────────────────────────────────────────────────────────
const { confirm } = useConfirm()

async function doSuspend() {
  if (!(await confirm({
    title: 'Suspendre',
    message: `Suspendre "${tenant.value?.name}" ?`,
    confirmLabel: 'Suspendre',
    danger: true,
  }))) return
  await adminService.suspendTenant(id)
  await load()
}

async function doReactivate() {
  await adminService.reactivateTenant(id)
  await load()
}

async function doChangePlan() {
  if (!selectedPlanCode.value || selectedPlanCode.value === tenant.value?.plan) return
  changingPlan.value = true
  try {
    await adminService.changeTenantPlan(id, selectedPlanCode.value)
    await load()
  } finally {
    changingPlan.value = false
  }
}

async function toggleModule(mod: ErpModule) {
  if (mod.tenant_active) {
    await adminService.deactivateModuleForTenant(id, mod.code)
  } else {
    await adminService.activateModuleForTenant(id, mod.code)
  }
  await loadModules()
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmtDate = formatDate

onMounted(() => Promise.all([load(), loadModules()]))
</script>

<style scoped>
.tenant-detail { display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Header ──────────────────────────────────────────────────────────────── */
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.8125rem;
  color: #64748b;
  text-decoration: none;
  margin-bottom: 0.75rem;
}
.back-link:hover { color: #0f172a; }

.detail-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.detail-name {
  font-size: 1.375rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0 0 2px;
}
.detail-slug {
  font-size: 0.75rem;
  color: #94a3b8;
  font-family: monospace;
}

.header-actions { display: flex; align-items: center; gap: 0.5rem; }

/* ── Status / sub badges ─────────────────────────────────────────────────── */
.status-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.status-badge--active    { background: #dcfce7; color: #166534; }
.status-badge--suspended { background: #fef9c3; color: #854d0e; }
.status-badge--cancelled { background: #f1f5f9; color: #94a3b8; }

.sub-badge {
  font-size: 0.6875rem;
  font-weight: 500;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
}
.sub-badge--trialing  { background: #dbeafe; color: #1d4ed8; }
.sub-badge--active    { background: #dcfce7; color: #166534; }
.sub-badge--suspended { background: #fef9c3; color: #854d0e; }
.sub-badge--cancelled { background: #f1f5f9; color: #94a3b8; }

/* ── Cards ───────────────────────────────────────────────────────────────── */
.detail-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}
@media (max-width: 768px) { .detail-row { grid-template-columns: 1fr; } }

.detail-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
}
.detail-card.full-width { grid-column: 1 / -1; }

.detail-card-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ── Info DL ─────────────────────────────────────────────────────────────── */
.info-dl {
  display: grid;
  grid-template-columns: auto 1fr;
  column-gap: 1.25rem;
  row-gap: 0.5rem;
  margin: 0;
}
.info-dl dt {
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
  align-self: center;
}
.info-dl dd {
  font-size: 0.8125rem;
  color: #e2e8f0;
  margin: 0;
  align-self: center;
}

.plan-chip {
  display: inline-block;
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  background: #1d4ed8;
  color: #bfdbfe;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.count-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  font-size: 0.6875rem;
  font-weight: 700;
  background: #334155;
  color: #94a3b8;
}

/* ── Change plan ─────────────────────────────────────────────────────────── */
.change-plan-row {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #334155;
}
.plan-select {
  flex: 1;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 6px;
  color: #e2e8f0;
  font-size: 0.8125rem;
  padding: 0.375rem 0.625rem;
  outline: none;
}
.plan-select:focus { border-color: #475569; }

/* ── Action buttons ──────────────────────────────────────────────────────── */
.btn-action {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 5px 12px;
  border-radius: 6px;
  border: 1px solid;
  cursor: pointer;
  transition: opacity 0.15s;
  white-space: nowrap;
}
.btn-action:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-action:not(:disabled):hover { opacity: 0.85; }
.btn-action.btn-xs { padding: 3px 9px; font-size: 0.6875rem; }

.btn-warn    { background: #450a0a; border-color: #991b1b; color: #fca5a5; }
.btn-ok      { background: #052e16; border-color: #166534; color: #86efac; }
.btn-primary { background: #1d4ed8; border-color: #1e40af; color: white; }

/* ── Users table ─────────────────────────────────────────────────────────── */
.mini-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.mini-table th {
  text-align: left;
  font-weight: 500;
  color: #64748b;
  font-size: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #334155;
}
.mini-table td {
  padding: 0.625rem 0.75rem;
  color: #e2e8f0;
  border-bottom: 1px solid #1e293b;
}
.mini-table tr:last-child td { border-bottom: none; }
.center { text-align: center; }

/* ── Modules grid ────────────────────────────────────────────────────────── */
.modules-grid {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}
.mod-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.625rem 0.75rem;
  border-radius: 7px;
  background: #0f172a;
}
.mod-row:hover { background: #162032; }

.mod-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mod-name { font-size: 0.8125rem; font-weight: 600; color: #e2e8f0; }
.mod-code { font-size: 0.6875rem; color: #64748b; }

.mod-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }

.mod-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.mod-badge--on  { background: #052e16; color: #86efac; }
.mod-badge--off { background: #1e293b; color: #64748b; }

.core-chip {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  background: #1e3a5f;
  color: #93c5fd;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* ── Misc ────────────────────────────────────────────────────────────────── */
.mono { font-family: ui-monospace, monospace; font-size: 0.8em; }
.no-data { font-size: 0.8125rem; color: #64748b; padding: 1rem 0; }
.state-msg { padding: 1.5rem; text-align: center; color: #64748b; }
</style>
