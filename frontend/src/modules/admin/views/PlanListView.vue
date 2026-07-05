<template>
  <div class="plan-list">
    <div class="plan-grid" v-if="plans.length">
      <div v-for="plan in plans" :key="plan.id" class="plan-card" :class="{ 'plan-card--inactive': !plan.is_active }">
        <div class="plan-card__header">
          <div>
            <div class="plan-name">{{ plan.name }}</div>
            <div class="plan-code">{{ plan.code }}</div>
          </div>
          <span class="plan-vis-badge" :class="plan.is_public ? 'plan-vis-badge--public' : 'plan-vis-badge--private'">
            {{ plan.is_public ? $t('admin.public') : $t('admin.private') }}
          </span>
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
      <div class="checks">
        <label><input v-model="form.is_active" type="checkbox" /> {{ $t('common.active') }}</label>
        <label><input v-model="form.is_public" type="checkbox" /> {{ $t('admin.public') }}</label>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="modal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="modal.saving" @click="save">{{ modal.saving ? $t('common.saving') : $t('common.save') }}</button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { formatMoney } from '@/shared/utils/money'
import { adminService, type AdminPlan } from '../services/adminService'
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

// ── Edit plan limits (super-admin) ────────────────────────────────────────────
const LIMIT_KEYS = ['max_products', 'max_monthly_orders', 'max_customers', 'max_branches', 'max_warehouses', 'max_imports_per_month', 'max_api_calls_per_month', 'storage_mb'] as const

const modal  = reactive({ open: false, saving: false, error: '', id: '' })
const form   = reactive({ name: '', max_users: 0, trial_days: 0, is_active: true, is_public: true })
const limits = reactive<Record<string, string>>(Object.fromEntries(LIMIT_KEYS.map(k => [k, ''])))

function openEdit(plan: AdminPlan) {
  modal.id = plan.id
  modal.error = ''
  form.name = plan.name
  form.max_users = plan.max_users
  form.trial_days = plan.trial_days
  form.is_active = plan.is_active
  form.is_public = plan.is_public
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

.plan-card--inactive { opacity: 0.6; }

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
