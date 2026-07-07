<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.taxesTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.taxesSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" data-test="add-tax" @click="openCreate">{{ $t('accounting.addTax') }}</button>
    </div>

    <div class="card table-scroll">
      <StateBlock v-if="loading" variant="loading" />
      <StateBlock v-else-if="taxes.length === 0" variant="empty" :title="$t('accounting.noTaxes')" />
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('accounting.colCode') }}</th>
            <th>{{ $t('accounting.colName') }}</th>
            <th>{{ $t('accounting.colRate') }}</th>
            <th>{{ $t('accounting.colMode') }}</th>
            <th>{{ $t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tx in taxes" :key="tx.id">
            <td><code class="acc-code">{{ tx.code }}</code></td>
            <td>{{ tx.name }}</td>
            <td>{{ (tx.rate_bp / 100).toFixed(2) }} %</td>
            <td>{{ tx.is_inclusive ? $t('accounting.inclusive') : $t('accounting.exclusive') }}</td>
            <td>
              <span :class="tx.is_active ? 'badge badge-success' : 'badge badge-gray'">
                {{ tx.is_active ? $t('accounting.active') : $t('accounting.inactive') }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <BaseModal v-model="modal.open" :title="$t('accounting.addTax')">
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.colCode') }} *</label>
        <input v-model="modal.code" class="form-input" placeholder="TVA18" data-test="tax-code" />
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.colName') }} *</label>
        <input v-model="modal.name" class="form-input" data-test="tax-name" />
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.ratePct') }} *</label>
        <input v-model.number="modal.ratePct" type="number" min="0" max="100" step="0.01" class="form-input" data-test="tax-rate" />
      </div>
      <p v-if="modal.error" class="form-error" data-test="tax-error">{{ modal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="modal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="modal.saving || !modal.code || !modal.name" data-test="tax-submit" @click="submit">
          {{ $t('common.save') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { Tax } from '../types'

const taxes   = ref<Tax[]>([])
const loading = ref(false)

async function load() {
  loading.value = true
  try { taxes.value = await accountingService.taxes() }
  finally { loading.value = false }
}

const modal = reactive({ open: false, saving: false, error: '', code: '', name: '', ratePct: 18 })

function openCreate() {
  Object.assign(modal, { open: true, saving: false, error: '', code: '', name: '', ratePct: 18 })
}

async function submit() {
  modal.saving = true
  modal.error = ''
  try {
    // Taux saisi en % → points de base entiers (convention projet : arithmétique entière).
    await accountingService.createTax({
      code: modal.code.trim(), name: modal.name.trim(),
      rate_bp: Math.round(modal.ratePct * 100),
    })
    modal.open = false
    await load()
  } catch (e: any) {
    modal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {}).flat()[0] as string)
      ?? t('accounting.createError')
  } finally {
    modal.saving = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .82rem; }
.form-error { color: #dc2626; font-size: .85rem; }
.table-scroll { overflow-x: auto; }
</style>
