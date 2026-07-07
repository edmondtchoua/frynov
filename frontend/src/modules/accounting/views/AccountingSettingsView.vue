<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.settingsTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.settingsSubtitle') }}</p>
      </div>
    </div>

    <StateBlock v-if="loading" variant="loading" />
    <StateBlock v-else-if="!settings" variant="empty" :title="$t('accounting.notProvisioned')" />

    <template v-else>
      <div class="card acc-block">
        <h3 class="section-title">{{ $t('accounting.generalSection') }}</h3>
        <div class="acc-grid">
          <div><span class="acc-label">{{ $t('accounting.country') }}</span><strong>{{ settings.country ?? '—' }}</strong></div>
          <div><span class="acc-label">{{ $t('accounting.currency') }}</span><strong>{{ settings.currency }}</strong></div>
          <div>
            <span class="acc-label">{{ $t('accounting.autoPost') }}</span>
            <label class="acc-switch">
              <input v-model="autoPost" type="checkbox" data-test="auto-post" @change="saveAutoPost" />
              <span>{{ autoPost ? $t('accounting.autoPostOn') : $t('accounting.autoPostOff') }}</span>
            </label>
          </div>
        </div>
        <p v-if="saveError" class="form-error" data-test="settings-error">{{ saveError }}</p>
        <p v-if="saved" class="acc-saved" data-test="settings-saved">{{ $t('accounting.saved') }}</p>
      </div>

      <div class="card acc-block">
        <h3 class="section-title">{{ $t('accounting.defaultAccountsSection') }}</h3>
        <p class="acc-hint">{{ $t('accounting.defaultAccountsHint') }}</p>
        <table class="data-table">
          <tbody>
            <tr v-for="(code, key) in settings.default_accounts" :key="key">
              <td><code class="acc-code">{{ key }}</code></td>
              <td>{{ $t('accounting.ref.' + String(key).replace('@', '')) }}</td>
              <td style="text-align:right"><strong>{{ code }}</strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { AccountingSettings } from '../types'

const settings  = ref<AccountingSettings | null>(null)
const loading   = ref(false)
const autoPost  = ref(false)
const saveError = ref('')
const saved     = ref(false)

async function load() {
  loading.value = true
  try {
    settings.value = await accountingService.settings()
    autoPost.value = settings.value?.auto_post ?? false
  } finally {
    loading.value = false
  }
}

async function saveAutoPost() {
  saveError.value = ''
  saved.value = false
  try {
    settings.value = await accountingService.updateSettings({ auto_post: autoPost.value })
    saved.value = true
    setTimeout(() => { saved.value = false }, 2500)
  } catch (e: any) {
    autoPost.value = !autoPost.value // rollback visuel
    saveError.value = e?.response?.data?.message ?? t('accounting.saveError')
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 900px; margin: 0 auto; }
.page-header { margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.acc-block { margin-bottom: 20px; }
.section-title { font-size: 1rem; font-weight: 600; margin: 0 0 12px; }
.acc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.acc-label { display: block; font-size: .78rem; color: var(--gray-500); margin-bottom: 4px; }
.acc-switch { display: flex; align-items: center; gap: 8px; font-size: .9rem; cursor: pointer; }
.acc-hint { font-size: .82rem; color: var(--gray-500); margin: 0 0 12px; }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .82rem; }
.acc-saved { color: #059669; font-size: .85rem; margin-top: 8px; }
.form-error { color: #dc2626; font-size: .85rem; margin-top: 8px; }
</style>
