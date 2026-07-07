<template>
  <div class="plan-list">
    <div class="plan-toolbar">
      <button class="btn btn-secondary" @click="toggleAnalytics">📊 {{ $t('admin.plan.analytics') }}</button>
      <button class="btn btn-primary" @click="openCreate">＋ {{ $t('admin.plan.create') }}</button>
    </div>

    <!-- Analytics — graphes (barres CSS, sans dépendance) -->
    <div class="analytics-panel" v-if="showAnalytics">
      <div class="analytics-loading" v-if="analyticsLoading">{{ $t('common.loading') }}</div>
      <template v-else-if="analytics">
        <div class="analytics-grid">
          <div class="chart-card">
            <h4>{{ $t('admin.plan.chartRevenue') }}</h4>
            <div v-for="row in analytics.plans" :key="row.code" class="bar-row">
              <span class="bar-label">{{ row.name }}</span>
              <div class="bar-track"><div class="bar-fill bar-fill--rev" :style="{ width: pct(row.revenue_minor, maxRevenue) + '%' }"></div></div>
              <span class="bar-val">{{ formatMoney(row.revenue_minor) }}</span>
            </div>
          </div>
          <div class="chart-card">
            <h4>{{ $t('admin.plan.chartAdoption') }}</h4>
            <div v-for="row in analytics.plans" :key="row.code" class="bar-row">
              <span class="bar-label">{{ row.name }}</span>
              <div class="bar-track"><div class="bar-fill bar-fill--adopt" :style="{ width: pct(row.active_subscriptions, maxAdoption) + '%' }"></div></div>
              <span class="bar-val">{{ row.active_subscriptions }}</span>
            </div>
          </div>
        </div>
        <div class="cr-status-row">
          <span v-for="(count, status) in analytics.change_requests_by_status" :key="status" class="cr-status-chip">
            {{ status }} : <strong>{{ count }}</strong>
          </span>
        </div>
      </template>
    </div>

    <div class="plan-grid" v-if="plans.length">
      <div v-for="plan in plans" :key="plan.id" class="plan-card" :class="{ 'plan-card--inactive': plan.status === 'archived' }">
        <div class="plan-card__header">
          <div>
            <div class="plan-name">
              {{ plan.name }}
              <span v-if="plan.badge" class="plan-badge">{{ plan.badge }}</span>
            </div>
            <div class="plan-code">{{ plan.code }}</div>
          </div>
          <div class="plan-card__badges">
            <span class="plan-status-badge" :class="`plan-status-badge--${plan.status ?? 'active'}`">{{ $t(`admin.plan.status.${plan.status ?? 'active'}`) }}</span>
            <span class="plan-vis-badge" :class="plan.is_public ? 'plan-vis-badge--public' : 'plan-vis-badge--private'">
              {{ plan.is_public ? $t('admin.public') : $t('admin.private') }}
            </span>
          </div>
        </div>

        <div class="plan-price">
          <template v-if="plan.price_monthly_cents > 0">
            <span class="plan-price__amount">{{ formatPrice(plan.price_monthly_cents) }}</span>
            <span class="plan-price__period">{{ $t('admin.perMonth') }}</span>
          </template>
          <template v-else>
            <span class="plan-price__free">{{ $t('admin.free') }}</span>
          </template>
        </div>

        <div class="plan-limits">
          <span>{{ plan.max_users || '∞' }} {{ $t('admin.usersUnit') }}</span>
          <span>{{ plan.max_products || '∞' }} {{ $t('admin.productsUnit') }}</span>
          <span>{{ plan.max_monthly_orders || '∞' }} {{ $t('admin.ordersPerMonthUnit') }}</span>
          <span>{{ plan.trial_days }}{{ $t('admin.trialDaysUnit') }}</span>
        </div>

        <ul class="plan-features">
          <li v-for="f in plan.features" :key="f">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M2 6l3 3 5-5" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ f }}
          </li>
        </ul>

        <button class="btn-edit-plan" @click="openEdit(plan)">{{ $t('admin.editLimits') }}</button>
      </div>
    </div>
    <div v-else-if="loading" class="state-msg">{{ $t('common.loading') }}</div>
    <div v-else class="state-msg">{{ $t('admin.noPlans') }}</div>

    <!-- Edit limits modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="modal.open" size="lg" :title="$t('admin.editPlan', { name: form.name })">
      <div v-if="modal.error" class="form-error">{{ modal.error }}</div>
      <p class="hint">{{ $t('admin.limitHint1') }} <strong>{{ $t('admin.limitHint2') }}</strong>. {{ $t('admin.limitHint3') }} <code>plan_limits</code>.</p>
      <div class="grid2">
            <label>{{ $t('common.name') }}<input v-model="form.name" class="form-input" /></label>
            <label>{{ $t('admin.includedUsers') }}<input v-model.number="form.max_users" type="number" min="0" class="form-input" /></label>
            <label>{{ $t('admin.trialDaysLabel') }}<input v-model.number="form.trial_days" type="number" min="0" class="form-input" /></label>
            <label>{{ $t('admin.products') }}<input v-model="limits.max_products" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.ordersPerMonth') }}<input v-model="limits.max_monthly_orders" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.customers') }}<input v-model="limits.max_customers" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.branches') }}<input v-model="limits.max_branches" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.warehouses') }}<input v-model="limits.max_warehouses" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.importsPerMonth') }}<input v-model="limits.max_imports_per_month" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.apiCallsPerMonth') }}<input v-model="limits.max_api_calls_per_month" type="number" min="0" class="form-input" placeholder="∞" /></label>
            <label>{{ $t('admin.storageMb') }}<input v-model="limits.storage_mb" type="number" min="0" class="form-input" placeholder="∞" /></label>
          </div>
      <div class="grid2" style="margin-top:0.9rem">
        <label>{{ $t('admin.plan.statusLabel') }}
          <select v-model="form.status" class="form-input">
            <option value="active">{{ $t('admin.plan.status.active') }}</option>
            <option value="draft">{{ $t('admin.plan.status.draft') }}</option>
            <option value="archived">{{ $t('admin.plan.status.archived') }}</option>
          </select>
        </label>
        <label>{{ $t('admin.plan.badgeLabel') }}
          <input v-model="form.badge" class="form-input" :placeholder="$t('admin.plan.badgePlaceholder')" />
        </label>
      </div>
      <div class="checks">
        <label><input v-model="form.is_active" type="checkbox" /> {{ $t('common.active') }}</label>
        <label><input v-model="form.is_public" type="checkbox" /> {{ $t('admin.public') }}</label>
      </div>

      <template #footer>
        <button class="btn btn-danger" :disabled="modal.saving" @click="archive">{{ $t('admin.plan.archive') }}</button>
        <button class="btn btn-secondary" @click="modal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="modal.saving" @click="save">{{ modal.saving ? $t('common.saving') : $t('common.save') }}</button>
      </template>
    </BaseModal>

    <!-- P5 — création d'un plan (brouillon) -->
    <BaseModal v-model="createModal.open" size="md" :title="$t('admin.plan.create')">
      <div v-if="createModal.error" class="form-error">{{ createModal.error }}</div>
      <div class="grid2">
        <label>{{ $t('admin.plan.codeLabel') }}<input v-model="createForm.code" class="form-input" placeholder="growth-plus" /></label>
        <label>{{ $t('common.name') }}<input v-model="createForm.name" class="form-input" /></label>
        <label>{{ $t('admin.plan.monthlyPrice') }}<input v-model.number="createForm.price_monthly_cents" type="number" min="0" class="form-input" /></label>
        <label>{{ $t('admin.plan.badgeLabel') }}<input v-model="createForm.badge" class="form-input" :placeholder="$t('admin.plan.badgePlaceholder')" /></label>
      </div>
      <p class="hint">{{ $t('admin.plan.createHint') }}</p>
      <template #footer>
        <button class="btn btn-secondary" @click="createModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="createModal.saving" @click="create">{{ createModal.saving ? $t('common.saving') : $t('common.save') }}</button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { formatMoney } from '@/shared/utils/money'
import { adminService, type AdminPlan, type AdminPlanAnalyticsRow } from '../services/adminService'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { t } from '@/i18n'

const plans   = ref<AdminPlan[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    plans.value = await adminService.getPlans()
  } finally {
    loading.value = false
  }
}

const formatPrice = (cents: number) => formatMoney(cents)

// ── Analytics (graphes) ───────────────────────────────────────────────────────
const showAnalytics = ref(false)
const analyticsLoading = ref(false)
const analytics = ref<{ plans: AdminPlanAnalyticsRow[]; change_requests_by_status: Record<string, number> } | null>(null)
const maxRevenue = computed(() => Math.max(1, ...(analytics.value?.plans.map(p => p.revenue_minor) ?? [0])))
const maxAdoption = computed(() => Math.max(1, ...(analytics.value?.plans.map(p => p.active_subscriptions) ?? [0])))
const pct = (v: number, max: number) => Math.round((v / max) * 100)

async function toggleAnalytics() {
  showAnalytics.value = !showAnalytics.value
  if (showAnalytics.value && !analytics.value) {
    analyticsLoading.value = true
    try {
      analytics.value = await adminService.getPlanAnalytics()
    } finally {
      analyticsLoading.value = false
    }
  }
}

// ── Edit plan limits (super-admin) ────────────────────────────────────────────
const LIMIT_KEYS = ['max_products', 'max_monthly_orders', 'max_customers', 'max_branches', 'max_warehouses', 'max_imports_per_month', 'max_api_calls_per_month', 'storage_mb'] as const

const modal  = reactive({ open: false, saving: false, error: '', id: '' })
const form   = reactive({ name: '', max_users: 0, trial_days: 0, is_active: true, is_public: true, status: 'active' as 'active' | 'draft' | 'archived', badge: '' })
const limits = reactive<Record<string, string>>(Object.fromEntries(LIMIT_KEYS.map(k => [k, ''])))

function openEdit(plan: AdminPlan) {
  modal.id = plan.id
  modal.error = ''
  form.name = plan.name
  form.max_users = plan.max_users
  form.trial_days = plan.trial_days
  form.is_active = plan.is_active
  form.is_public = plan.is_public
  form.status = plan.status ?? 'active'
  form.badge = plan.badge ?? ''
  const L = (plan.limits ?? {}) as Record<string, number | null>
  for (const k of LIMIT_KEYS) limits[k] = L[k] == null ? '' : String(L[k])
  modal.open = true
}

const toNum = (v: string): number | null => (v === '' ? null : Number(v))

async function save() {
  modal.saving = true
  modal.error = ''
  try {
    const limitsPayload: Record<string, number | null> = {}
    for (const k of LIMIT_KEYS) limitsPayload[k] = toNum(limits[k])
    const updated = await adminService.updatePlan(modal.id, {
      name: form.name,
      max_users: form.max_users,
      trial_days: form.trial_days,
      is_active: form.is_active,
      is_public: form.is_public,
      status: form.status,
      badge: form.badge || null,
      limits: limitsPayload,
    })
    const idx = plans.value.findIndex(p => p.id === modal.id)
    if (idx !== -1) plans.value[idx] = updated
    modal.open = false
  } catch (e: any) {
    modal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {})?.[0] as string[] | undefined)?.[0]
      ?? t('admin.savePlanError')
  } finally {
    modal.saving = false
  }
}

async function archive() {
  modal.saving = true
  modal.error = ''
  try {
    const updated = await adminService.archivePlan(modal.id)
    const idx = plans.value.findIndex(p => p.id === modal.id)
    if (idx !== -1) plans.value[idx] = updated
    modal.open = false
  } catch (e: any) {
    modal.error = e?.response?.data?.message ?? t('admin.savePlanError')
  } finally {
    modal.saving = false
  }
}

// ── Create plan (draft) ───────────────────────────────────────────────────────
const createModal = reactive({ open: false, saving: false, error: '' })
const createForm  = reactive({ code: '', name: '', price_monthly_cents: 0, badge: '' })

function openCreate() {
  Object.assign(createForm, { code: '', name: '', price_monthly_cents: 0, badge: '' })
  createModal.error = ''
  createModal.open = true
}

async function create() {
  createModal.saving = true
  createModal.error = ''
  try {
    const plan = await adminService.createPlan({
      code: createForm.code.trim(),
      name: createForm.name.trim(),
      price_monthly_cents: createForm.price_monthly_cents,
      badge: createForm.badge || null,
    })
    plans.value.push(plan)
    createModal.open = false
  } catch (e: any) {
    createModal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {})?.[0] as string[] | undefined)?.[0]
      ?? t('admin.savePlanError')
  } finally {
    createModal.saving = false
  }
}

onMounted(load)
</script>

<style scoped>
.plan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.plan-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.plan-card--inactive { opacity: 0.55; }

.plan-toolbar { display: flex; justify-content: flex-end; gap: 0.5rem; margin-bottom: 1rem; }

.analytics-panel { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem; }
.analytics-loading { color: #94a3b8; font-size: 0.85rem; }
.analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
.chart-card h4 { margin: 0 0 0.75rem; font-size: 0.85rem; color: #334155; }
.bar-row { display: grid; grid-template-columns: 90px 1fr auto; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; font-size: 0.75rem; }
.bar-label { color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bar-track { background: #f1f5f9; border-radius: 999px; height: 12px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 999px; transition: width .4s; min-width: 2px; }
.bar-fill--rev { background: #10b981; }
.bar-fill--adopt { background: #6366f1; }
.bar-val { color: #64748b; font-variant-numeric: tabular-nums; text-align: right; }
.cr-status-row { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; }
.cr-status-chip { font-size: 0.7rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px 8px; color: #475569; }
.plan-card__badges { display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem; }
.plan-badge { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; background: #fef3c7; color: #92400e; padding: 1px 6px; border-radius: 999px; margin-left: 0.4rem; vertical-align: middle; }
.plan-status-badge { font-size: 0.6rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; }
.plan-status-badge--active   { background: #dcfce7; color: #166534; }
.plan-status-badge--draft    { background: #e0e7ff; color: #3730a3; }
.plan-status-badge--archived { background: #f1f5f9; color: #64748b; }
.btn-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px; padding: 0.45rem 0.9rem; font-weight: 600; cursor: pointer; margin-right: auto; }
.btn-danger:hover { background: #fee2e2; }

.plan-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.plan-name { font-size: 1.0625rem; font-weight: 700; color: #0f172a; }
.plan-code { font-size: 0.6875rem; color: #94a3b8; font-family: monospace; margin-top: 2px; text-transform: uppercase; }

.plan-vis-badge {
  font-size: 0.625rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
}
.plan-vis-badge--public  { background: #dcfce7; color: #166534; }
.plan-vis-badge--private { background: #f1f5f9; color: #64748b; }

.plan-price { display: flex; align-items: baseline; gap: 0.25rem; }
.plan-price__amount { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
.plan-price__period { font-size: 0.8125rem; color: #64748b; }
.plan-price__free   { font-size: 1.25rem; font-weight: 700; color: #16a34a; }

.plan-limits {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}
.plan-limits span {
  font-size: 0.6875rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 2px 7px;
  color: #475569;
}

.plan-features { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.375rem; }
.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: #475569;
}

.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }

.btn-edit-plan {
  margin-top: auto; align-self: flex-start;
  font-size: 0.8125rem; font-weight: 600;
  color: var(--brand-primary, #10b981);
  background: var(--brand-primary-bg, #ecfdf5);
  border: 1px solid var(--brand-primary-light, #d1fae5);
  border-radius: 8px; padding: 0.4rem 0.75rem; cursor: pointer;
}
.btn-edit-plan:hover { background: #d1fae5; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem 0.9rem; }
.grid2 label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.8125rem; font-weight: 600; color: #475569; }
.checks { display: flex; gap: 1.25rem; margin-top: 0.9rem; }
.checks label { display: flex; align-items: center; gap: 0.4rem; font-size: 0.875rem; font-weight: 500; }
.hint { font-size: 0.8125rem; color: #64748b; margin: 0 0 0.75rem; }
.form-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.85rem; margin-bottom: 0.75rem; }
.form-input { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px; font: inherit; box-sizing: border-box; }
</style>
