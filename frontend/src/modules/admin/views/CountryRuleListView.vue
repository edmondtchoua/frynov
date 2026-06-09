<template>
  <div>
    <div class="page-header">
      <div>
        <h2>{{ $t('admin.countryRulesTitle') }}</h2>
        <p class="page-subtitle">{{ $t('admin.countryRulesSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ {{ $t('admin.newRule') }}</button>
    </div>

    <StateBlock v-if="loading" variant="loading" />

    <StateBlock v-else-if="error" variant="error" :message="error">
      <template #action>
        <button class="btn btn-secondary" @click="load">{{ $t('common.retry') }}</button>
      </template>
    </StateBlock>

    <StateBlock
      v-else-if="!rules.length"
      variant="empty"
      :title="$t('admin.noRules')"
      :message="$t('admin.noRulesHint')"
    />

    <div v-else class="card" style="padding:0; overflow:hidden;">
      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('admin.country') }}</th>
              <th>{{ $t('admin.currency') }}</th>
              <th>{{ $t('admin.timezone') }}</th>
              <th>{{ $t('common.status') }}</th>
              <th>{{ $t('admin.approval') }}</th>
              <th>{{ $t('admin.allowedPlans') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rules" :key="r.id">
              <td><strong>{{ r.country_code }}</strong></td>
              <td>{{ r.default_currency || '—' }}</td>
              <td>{{ r.default_timezone || '—' }}</td>
              <td>
                <span v-if="r.is_blocked" class="tag tag--blocked">{{ $t('admin.blocked') }}</span>
                <span v-else-if="r.is_active" class="tag tag--ok">{{ $t('common.active') }}</span>
                <span v-else class="tag tag--off">{{ $t('common.inactive') }}</span>
              </td>
              <td>{{ r.requires_approval ? $t('admin.required') : $t('admin.auto') }}</td>
              <td>{{ r.allowed_plans?.length ? r.allowed_plans.join(', ') : $t('admin.all') }}</td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-secondary" @click="openEdit(r)">{{ $t('common.edit') }}</button>
                  <button class="btn btn-sm btn-danger" @click="remove(r)">{{ $t('admin.deleteShort') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create / edit modal (shared BaseModal — UX-03) -->
    <BaseModal
      v-model="modal.open"
      :title="modal.editing ? $t('admin.editRule', { code: form.country_code }) : $t('admin.newRuleTitle')"
    >
      <div v-if="modal.error" class="form-error">{{ modal.error }}</div>

      <label class="form-label">{{ $t('admin.countryCode') }} *</label>
      <input v-model="form.country_code" :disabled="modal.editing" maxlength="2" class="form-input" placeholder="SN" style="text-transform:uppercase" />

      <label class="form-label">{{ $t('admin.defaultCurrency') }}</label>
      <input v-model="form.default_currency" maxlength="3" class="form-input" placeholder="XOF" style="text-transform:uppercase" />

      <label class="form-label">{{ $t('admin.timezoneLabel') }}</label>
      <input v-model="form.default_timezone" class="form-input" placeholder="Africa/Dakar" />

      <label class="form-label">{{ $t('admin.allowedPlansLabel') }}</label>
      <input v-model="form.allowed_plans" class="form-input" placeholder="starter, pro" />

      <div class="form-checks">
        <label class="check"><input v-model="form.is_active" type="checkbox" /> {{ $t('common.active') }}</label>
        <label class="check"><input v-model="form.requires_approval" type="checkbox" /> {{ $t('admin.requiresApproval') }}</label>
        <label class="check"><input v-model="form.is_blocked" type="checkbox" /> {{ $t('admin.blockedRegistration') }}</label>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="closeModal">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="modal.saving" @click="save">
          {{ modal.saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { adminService, type AdminCountryRule } from '../services/adminService'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { t } from '@/i18n'

const rules   = ref<AdminCountryRule[]>([])
const loading = ref(true)
const error   = ref<string | null>(null)

const modal = reactive({ open: false, editing: false, saving: false, error: '', id: '' })
const form  = reactive({
  country_code: '',
  default_currency: '',
  default_timezone: '',
  allowed_plans: '',
  is_active: true,
  requires_approval: false,
  is_blocked: false,
})

async function load() {
  loading.value = true
  error.value = null
  try {
    rules.value = (await adminService.getCountryRules()).data
  } catch {
    error.value = t('admin.loadRulesError')
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.country_code = ''
  form.default_currency = ''
  form.default_timezone = ''
  form.allowed_plans = ''
  form.is_active = true
  form.requires_approval = false
  form.is_blocked = false
}

function openCreate() {
  resetForm()
  modal.editing = false
  modal.id = ''
  modal.error = ''
  modal.open = true
}

function openEdit(r: AdminCountryRule) {
  modal.editing = true
  modal.id = r.id
  modal.error = ''
  form.country_code = r.country_code
  form.default_currency = r.default_currency ?? ''
  form.default_timezone = r.default_timezone ?? ''
  form.allowed_plans = (r.allowed_plans ?? []).join(', ')
  form.is_active = r.is_active
  form.requires_approval = r.requires_approval
  form.is_blocked = r.is_blocked
  modal.open = true
}

function closeModal() { modal.open = false }

function buildPayload(): Partial<AdminCountryRule> {
  const plans = form.allowed_plans.split(',').map(s => s.trim()).filter(Boolean)
  return {
    country_code: form.country_code.trim().toUpperCase(),
    default_currency: form.default_currency.trim().toUpperCase() || null,
    default_timezone: form.default_timezone.trim() || null,
    allowed_plans: plans.length ? plans : null,
    is_active: form.is_active,
    requires_approval: form.requires_approval,
    is_blocked: form.is_blocked,
  }
}

async function save() {
  modal.saving = true
  modal.error = ''
  try {
    const payload = buildPayload()
    if (modal.editing) {
      await adminService.updateCountryRule(modal.id, payload)
    } else {
      await adminService.createCountryRule(payload)
    }
    modal.open = false
    await load()
  } catch (e: any) {
    modal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {})?.[0] as string[] | undefined)?.[0]
      ?? t('admin.saveRuleError')
  } finally {
    modal.saving = false
  }
}

const { confirm } = useConfirm()

async function remove(r: AdminCountryRule) {
  if (!(await confirm({
    title: t('common.delete'),
    message: t('admin.deleteRuleConfirm', { code: r.country_code }),
    confirmLabel: t('common.delete'),
    danger: true,
  }))) return
  try {
    await adminService.deleteCountryRule(r.id)
    await load()
  } catch {
    error.value = 'Suppression impossible.'
  }
}

onMounted(load)
</script>

<style scoped>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.25rem; }
.page-subtitle { color: var(--gray-500,#64748b); font-size:0.875rem; margin:0.25rem 0 0; max-width:60ch; }
.table-scroll { overflow-x:auto; }
.actions { display:flex; gap:0.4rem; justify-content:flex-end; }

.tag { display:inline-block; padding:0.15rem 0.55rem; border-radius:999px; font-size:0.75rem; font-weight:600; }
.tag--ok { background:#ecfdf5; color:#059669; }
.tag--blocked { background:#fef2f2; color:#dc2626; }
.tag--off { background:#f1f5f9; color:#64748b; }

.form-label { display:block; font-size:0.8125rem; font-weight:600; color: var(--gray-600,#475569); margin:0.75rem 0 0.25rem; }
.form-checks { display:flex; flex-direction:column; gap:0.5rem; margin-top:1rem; }
.check { display:flex; align-items:center; gap:0.5rem; font-size:0.875rem; }
.form-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:8px; padding:0.5rem 0.75rem; font-size:0.85rem; margin-bottom:0.5rem; }
.btn-danger { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.btn-danger:hover { background:#fee2e2; }
.btn-sm { padding:0.3rem 0.6rem; font-size:0.8125rem; }
</style>
