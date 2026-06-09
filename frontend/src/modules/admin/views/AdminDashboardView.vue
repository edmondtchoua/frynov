<template>
  <div class="admin-dashboard">

    <!-- KPI grid -->
    <div class="kpi-grid" v-if="data">
      <div class="kpi-card">
        <div class="kpi-label">{{ $t('admin.dash.tenantsTotal') }}</div>
        <div class="kpi-value">{{ data.overview.tenants }}</div>
        <div class="kpi-sub">{{ $t('admin.dash.activeCount', { count: data.overview.active_tenants }) }}</div>
      </div>
      <div class="kpi-card kpi-card--warn" v-if="data.overview.suspended_tenants > 0">
        <div class="kpi-label">{{ $t('admin.dash.suspended') }}</div>
        <div class="kpi-value">{{ data.overview.suspended_tenants }}</div>
        <div class="kpi-sub">{{ $t('admin.dash.attentionRequired') }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">{{ $t('admin.dash.users') }}</div>
        <div class="kpi-value">{{ data.overview.total_users }}</div>
        <div class="kpi-sub">{{ $t('admin.dash.allTenants') }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">{{ $t('admin.dash.erpModules') }}</div>
        <div class="kpi-value">{{ data.overview.total_modules }}</div>
        <div class="kpi-sub">{{ $t('admin.dash.activePlans', { count: data.overview.total_plans }) }}</div>
      </div>
    </div>

    <div class="admin-cols" v-if="data">

      <!-- Subscriptions by status -->
      <section class="admin-card">
        <h2 class="admin-card__title">{{ $t('admin.dash.subsByStatus') }}</h2>
        <div class="stat-list">
          <div
            v-for="(count, status) in data.subscriptions"
            :key="status"
            class="stat-row"
          >
            <span class="stat-badge" :class="`stat-badge--${status}`">{{ $t('billing.subStatus.' + status) }}</span>
            <span class="stat-count">{{ count }}</span>
          </div>
        </div>
      </section>

      <!-- By plan -->
      <section class="admin-card">
        <h2 class="admin-card__title">{{ $t('admin.dash.byPlan') }}</h2>
        <div class="stat-list">
          <div v-for="p in data.by_plan" :key="p.code" class="stat-row">
            <span class="stat-name">{{ p.name }}</span>
            <span class="stat-count">{{ p.total }}</span>
          </div>
        </div>
      </section>

      <!-- Recent tenants -->
      <section class="admin-card admin-card--wide">
        <h2 class="admin-card__title">{{ $t('admin.dash.recentTenants') }}</h2>
        <table class="admin-table">
          <thead>
            <tr>
              <th>{{ $t('common.name') }}</th>
              <th>{{ $t('admin.dash.colPlan') }}</th>
              <th>{{ $t('common.status') }}</th>
              <th>{{ $t('admin.dash.createdOn') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in data.recent_tenants" :key="t.id">
              <td>
                <div class="tenant-name">{{ t.name }}</div>
                <div class="tenant-slug">{{ t.slug }}</div>
              </td>
              <td><span class="plan-badge">{{ t.plan }}</span></td>
              <td><span class="status-dot" :class="`status-dot--${t.status}`"></span> {{ $t('admin.tenantStatus.' + t.status) }}</td>
              <td>{{ formatDate(t.created_at) }}</td>
              <td>
                <RouterLink :to="`/admin/tenants/${t.id}`" class="btn-link">{{ $t('admin.dash.view') }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Audit log -->
      <section class="admin-card admin-card--wide">
        <h2 class="admin-card__title">{{ $t('admin.dash.recentActivity') }}</h2>
        <div class="log-list">
          <div v-for="log in data.recent_logs" :key="log.id" class="log-row">
            <span class="log-action">{{ log.action }}</span>
            <span class="log-user">{{ log.user?.name ?? '—' }}</span>
            <span class="log-ip">{{ log.ip_address ?? '' }}</span>
            <span class="log-time">{{ formatDate(log.created_at) }}</span>
          </div>
        </div>
      </section>

    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="admin-loading">{{ $t('common.loading') }}</div>
    <div v-else-if="error" class="admin-error">{{ error }}</div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import { RouterLink } from 'vue-router'
import { adminService } from '../services/adminService'
import { t } from '@/i18n'

const data    = ref<Awaited<ReturnType<typeof adminService.getDashboard>> | null>(null)
const loading = ref(true)
const error   = ref('')

onMounted(async () => {
  try {
    data.value = await adminService.getDashboard()
  } catch (e: any) {
    error.value = e.message ?? t('admin.dash.loadError')
  } finally {
    loading.value = false
  }
})

</script>

<style scoped>
.admin-dashboard { display: flex; flex-direction: column; gap: 1.5rem; }

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
}

.kpi-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem 1rem;
}

.kpi-card--warn { border-color: #fecaca; background: #fff5f5; }

.kpi-label { font-size: 0.75rem; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-value { font-size: 2rem; font-weight: 700; color: #0f172a; margin: 0.25rem 0; line-height: 1; }
.kpi-sub   { font-size: 0.75rem; color: #94a3b8; }

.admin-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.admin-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
}

.admin-card--wide { grid-column: 1 / -1; }

.admin-card__title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 1rem;
}

.stat-list { display: flex; flex-direction: column; gap: 0.5rem; }
.stat-row  { display: flex; align-items: center; justify-content: space-between; }

.stat-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
}
.stat-badge--active    { background: #dcfce7; color: #166534; }
.stat-badge--trialing  { background: #dbeafe; color: #1d4ed8; }
.stat-badge--suspended { background: #fef9c3; color: #854d0e; }
.stat-badge--cancelled { background: #f1f5f9; color: #475569; }
.stat-name  { font-size: 0.875rem; color: #334155; }
.stat-count { font-weight: 600; color: #0f172a; }

.admin-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.admin-table th { text-align: left; font-weight: 500; color: #64748b; font-size: 0.75rem; padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; }
.admin-table td { padding: 0.625rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.admin-table tr:last-child td { border-bottom: none; }

.tenant-name { font-weight: 500; color: #0f172a; }
.tenant-slug { font-size: 0.6875rem; color: #94a3b8; }
.plan-badge  { font-size: 0.6875rem; font-weight: 600; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }

.status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 4px; }
.status-dot--active    { background: #22c55e; }
.status-dot--suspended { background: #f59e0b; }
.status-dot--cancelled { background: #e2e8f0; }

.btn-link { font-size: 0.8125rem; color: #3b82f6; text-decoration: none; }
.btn-link:hover { text-decoration: underline; }

.log-list { display: flex; flex-direction: column; gap: 0.375rem; }
.log-row  { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.5rem; font-size: 0.8125rem; color: #334155; padding: 0.375rem 0; border-bottom: 1px solid #f1f5f9; }
.log-action { font-weight: 500; font-family: monospace; font-size: 0.75rem; color: #6366f1; }
.log-user   { color: #475569; }
.log-ip     { color: #94a3b8; font-size: 0.75rem; font-family: monospace; }
.log-time   { color: #94a3b8; font-size: 0.75rem; text-align: right; }

.admin-loading { padding: 2rem; text-align: center; color: #64748b; }
.admin-error   { padding: 2rem; text-align: center; color: #ef4444; }
</style>
