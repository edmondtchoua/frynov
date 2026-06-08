<template>
  <div class="supplier-list-view">

    <!-- ── Header ────────────────────────────────────────────────────────── -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ $t('suppliers.title') }}</h1>
        <span class="count-badge">{{ meta.total }} {{ meta.total !== 1 ? $t('suppliers.itemPlural') : $t('suppliers.itemSingular') }}</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="openCreate">
          <span class="btn-icon">+</span> {{ $t('suppliers.new') }}
        </button>
      </div>
    </div>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <div class="filters-bar">
      <div class="search-wrapper">
        <svg class="search-icon" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
          <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
        </svg>
        <input v-model="search" class="search-input" :placeholder="$t('suppliers.searchPlaceholder')" @input="onSearch" />
      </div>
      <select v-model="statusFilter" class="filter-select" @change="load(1)">
        <option value="">{{ $t('suppliers.allStatuses') }}</option>
        <option value="active">{{ $t('suppliers.activePlural') }}</option>
        <option value="inactive">{{ $t('suppliers.inactivePlural') }}</option>
      </select>
    </div>

    <!-- ── Table ─────────────────────────────────────────────────────────── -->
    <div class="table-card table-scroll">
      <StateBlock v-if="loading" variant="loading" />

      <StateBlock
        v-else-if="suppliers.length === 0"
        variant="empty"
        :title="$t('suppliers.empty')"
      >
        <template #action>
          <button class="btn btn-primary btn-sm" @click="openCreate">{{ $t('suppliers.addFirst') }}</button>
        </template>
      </StateBlock>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('suppliers.colCode') }}</th>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('suppliers.colContact') }}</th>
            <th>{{ $t('common.email') }}</th>
            <th>{{ $t('common.phone') }}</th>
            <th>{{ $t('suppliers.colTerms') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th class="col-actions">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in suppliers" :key="s.id" class="table-row">
            <td>
              <span class="code-badge">{{ s.code ?? '—' }}</span>
            </td>
            <td class="col-name">
              <div class="name-cell">
                <span class="avatar-circle">{{ s.name.charAt(0).toUpperCase() }}</span>
                <span class="supplier-name">{{ s.name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ s.contact_name ?? '—' }}</td>
            <td>
              <a v-if="s.email" :href="`mailto:${s.email}`" class="link">{{ s.email }}</a>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="text-muted">{{ s.phone ?? '—' }}</td>
            <td class="text-muted">{{ s.payment_terms ?? '—' }}</td>
            <td>
              <span :class="['status-badge', `status-${s.status}`]">
                {{ s.status === 'active' ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td class="col-actions">
              <div class="action-group">
                <RouterLink :to="`/suppliers/${s.id}`" class="btn-action btn-view" :title="$t('suppliers.viewDetail')">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 8C2.5 4.5 5 2.5 8 2.5S13.5 4.5 15 8c-1.5 3.5-4 5.5-7 5.5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.4"/></svg>
                </RouterLink>
                <button class="btn-action btn-edit" :title="$t('common.edit')" @click="openEdit(s)">✏️</button>
                <button class="btn-action btn-delete" :title="$t('common.delete')" @click="confirmDelete(s)">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="pagination">
        <button class="page-btn" :disabled="meta.current_page === 1" @click="load(meta.current_page - 1)">←</button>
        <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="page-btn" :disabled="meta.current_page === meta.last_page" @click="load(meta.current_page + 1)">→</button>
      </div>
    </div>

    <!-- ── Create / Edit Modal (shared BaseModal — UX-03) ──────────────────── -->
    <BaseModal
      v-model="showModal"
      size="lg"
      :title="editingId ? $t('suppliers.modalEditTitle') : $t('suppliers.modalCreateTitle')"
    >
      <form id="supplier-form" @submit.prevent="submitModal">
        <div class="form-grid">
          <div class="form-group">
            <label>{{ $t('common.name') }} <span class="required">*</span></label>
            <input v-model="form.name" required class="form-input" :placeholder="$t('suppliers.namePlaceholder')" />
          </div>
          <div class="form-group">
            <label>{{ $t('common.email') }}</label>
            <input v-model="form.email" type="email" class="form-input" :placeholder="$t('suppliers.emailPlaceholder')" />
          </div>
          <div class="form-group">
            <label>{{ $t('common.phone') }}</label>
            <input v-model="form.phone" class="form-input" placeholder="+225 07 00 00 00" />
          </div>
          <div class="form-group">
            <label>{{ $t('suppliers.contactPerson') }}</label>
            <input v-model="form.contact_name" class="form-input" :placeholder="$t('suppliers.contactPlaceholder')" />
          </div>
          <div class="form-group full-width">
            <label>{{ $t('suppliers.terms') }}</label>
            <input v-model="form.payment_terms" class="form-input" :placeholder="$t('suppliers.termsPlaceholder')" />
          </div>
          <div class="form-group">
            <label>{{ $t('common.status') }}</label>
            <select v-model="form.status" class="form-input">
              <option value="active">{{ $t('common.active') }}</option>
              <option value="inactive">{{ $t('common.inactive') }}</option>
            </select>
          </div>
          <div class="form-group full-width">
            <label>{{ $t('common.notes') }}</label>
            <textarea v-model="form.notes" class="form-input form-textarea" rows="3" :placeholder="$t('suppliers.notesPlaceholder')"></textarea>
          </div>
        </div>

        <div v-if="formError" class="form-error">{{ formError }}</div>
      </form>

      <template #footer>
        <button type="button" class="btn btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
        <button type="submit" form="supplier-form" class="btn btn-primary" :disabled="submitting">
          {{ submitting ? $t('common.saving') : (editingId ? $t('common.save') : $t('common.create')) }}
        </button>
      </template>
    </BaseModal>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { supplierService } from '../services/supplierService'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { t } from '@/i18n'
import type { Supplier } from '../types'

// ── State ───────────────────────────────────────────────────────────────────
const suppliers  = ref<Supplier[]>([])
const loading    = ref(false)
const meta       = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const search     = ref('')
const statusFilter = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

// Modal state
const showModal  = ref(false)
const editingId  = ref<string | null>(null)
const submitting = ref(false)
const formError  = ref('')
const form = reactive({
  name: '', email: '', phone: '', contact_name: '', payment_terms: '', notes: '', status: 'active' as 'active' | 'inactive',
})

// ── Load ────────────────────────────────────────────────────────────────────
async function load(page = 1) {
  loading.value = true
  try {
    const res = await supplierService.list({ page, search: search.value || undefined, status: statusFilter.value || undefined })
    suppliers.value = res.data
    Object.assign(meta, res.meta)
  } finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 350)
}

onMounted(() => load())

// ── Modal ───────────────────────────────────────────────────────────────────
function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', email: '', phone: '', contact_name: '', payment_terms: '', notes: '', status: 'active' })
  formError.value = ''
  showModal.value = true
}

function openEdit(s: Supplier) {
  editingId.value = s.id
  Object.assign(form, { name: s.name, email: s.email ?? '', phone: s.phone ?? '', contact_name: s.contact_name ?? '', payment_terms: s.payment_terms ?? '', notes: s.notes ?? '', status: s.status })
  formError.value = ''
  showModal.value = true
}

function closeModal() { showModal.value = false }

async function submitModal() {
  submitting.value = true
  formError.value = ''
  try {
    if (editingId.value) {
      await supplierService.update(editingId.value, form)
    } else {
      await supplierService.create(form)
    }
    closeModal()
    await load(meta.current_page)
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    submitting.value = false
  }
}

const { confirm } = useConfirm()

async function confirmDelete(s: Supplier) {
  if (!(await confirm({
    title: t('common.delete'),
    message: t('suppliers.confirmDelete', { name: s.name }),
    confirmLabel: t('common.delete'),
    danger: true,
  }))) return
  try {
    await supplierService.delete(s.id)
    await load(meta.current_page)
  } catch (e: any) {
    alert(e?.response?.data?.message ?? t('suppliers.deleteFailed'))
  }
}
</script>

<style scoped>
.supplier-list-view { padding: 24px; }

.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-title  { display: flex; align-items: center; gap: 12px; }
.page-title h1 { font-size: 24px; font-weight: 700; color: var(--gray-900); }
.count-badge { background: var(--brand-primary-light, #e0f2f1); color: var(--brand-primary, #0d9488); padding: 2px 10px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }

.filters-bar  { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.search-wrapper { position: relative; flex: 1; min-width: 220px; }
.search-icon    { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--gray-400); }
.search-input   { width: 100%; padding: 8px 12px 8px 34px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; outline: none; }
.search-input:focus { border-color: var(--brand-primary, #0d9488); }
.filter-select  { padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; outline: none; background: white; cursor: pointer; }

.table-card     { background: white; border-radius: 12px; border: 1px solid var(--gray-200); overflow-x: auto; }
.table-loading  { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 48px; color: var(--gray-500); }

.data-table         { width: 100%; border-collapse: collapse; }
.data-table th      { padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--gray-200); background: var(--gray-50); }
.data-table td      { padding: 14px 16px; font-size: 14px; color: var(--gray-700); border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
.table-row:hover td { background: var(--gray-50); }
.col-actions        { width: 100px; }

.empty-state  { text-align: center; padding: 64px 24px !important; }
.empty-inner  { display: flex; flex-direction: column; align-items: center; gap: 12px; color: var(--gray-400); }
.empty-inner p { font-size: 15px; color: var(--gray-500); }

.code-badge   { background: var(--gray-100); color: var(--gray-600); font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 6px; font-family: monospace; }
.name-cell    { display: flex; align-items: center; gap: 10px; }
.avatar-circle { width: 32px; height: 32px; border-radius: 50%; background: var(--brand-primary, #0d9488); color: white; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.supplier-name { font-weight: 600; color: var(--gray-900); }
.text-muted   { color: var(--gray-500); }
.link         { color: var(--brand-primary, #0d9488); text-decoration: none; }
.link:hover   { text-decoration: underline; }

.status-badge         { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-active        { background: #d1fae5; color: #065f46; }
.status-inactive      { background: #f3f4f6; color: #6b7280; }

.action-group { display: flex; gap: 4px; }
.btn-action   { background: none; border: none; padding: 6px; border-radius: 6px; cursor: pointer; font-size: 14px; transition: background 0.15s; }
.btn-action:hover { background: var(--gray-100); }

.pagination  { display: flex; align-items: center; justify-content: center; gap: 16px; padding: 16px; border-top: 1px solid var(--gray-100); }
.page-btn    { padding: 6px 14px; border: 1px solid var(--gray-200); background: white; border-radius: 8px; cursor: pointer; font-size: 14px; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info   { font-size: 14px; color: var(--gray-500); }

.spinner { width: 20px; height: 20px; border: 2px solid var(--gray-200); border-top-color: var(--brand-primary, #0d9488); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal chrome now provided by the shared <BaseModal> (UX-03). */
.form-grid    { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group   { display: flex; flex-direction: column; gap: 6px; }
.full-width   { grid-column: 1 / -1; }
.form-group label { font-size: 13px; font-weight: 600; color: var(--gray-700); }
.required     { color: #ef4444; }
.form-input   { padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.15s; width: 100%; }
.form-input:focus { border-color: var(--brand-primary, #0d9488); }
.form-textarea { resize: vertical; min-height: 80px; }
.form-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 12px; }

/* Buttons */
.btn         { padding: 9px 18px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
.btn-primary { background: var(--brand-primary, #0d9488); color: white; }
.btn-primary:hover:not(:disabled) { background: #0b8070; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost   { background: transparent; border: 1px solid var(--gray-200); color: var(--gray-700); }
.btn-ghost:hover { background: var(--gray-50); }
.btn-sm      { padding: 6px 14px; font-size: 13px; }
.btn-icon    { font-size: 18px; line-height: 1; }

@media (max-width: 768px) {
  .form-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .data-table th:nth-child(4), .data-table td:nth-child(4),
  .data-table th:nth-child(5), .data-table td:nth-child(5),
  .data-table th:nth-child(6), .data-table td:nth-child(6) { display: none; }
}
</style>
