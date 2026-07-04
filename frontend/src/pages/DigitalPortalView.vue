<template>
  <div class="portal-wrap">
    <div class="portal-card">
      <h1 class="portal-title">{{ $t('portal.title') }}</h1>
      <p class="portal-sub">{{ $t('portal.subtitle') }}</p>

      <!-- ── Accès par jeton ─────────────────────────────────────────── -->
      <form class="portal-form" @submit.prevent="loadAccess">
        <label class="form-label">{{ $t('portal.tokenLabel') }}</label>
        <div class="portal-row">
          <input v-model.trim="token" class="form-input" :placeholder="$t('portal.tokenPlaceholder')" />
          <button type="submit" class="btn btn-primary" :disabled="loading || !token">
            <span v-if="loading" class="spinner-sm"></span>
            {{ $t('portal.open') }}
          </button>
        </div>
        <p v-if="error" class="portal-error">{{ error }}</p>
      </form>

      <!-- ── Résultat ────────────────────────────────────────────────── -->
      <div v-if="access" class="portal-result">
        <div class="portal-product">
          <div>
            <div class="portal-product-name">{{ access.product_name }}</div>
            <div class="portal-product-seller">{{ $t('portal.soldBy', { seller: access.seller_name ?? '' }) }}</div>
          </div>
          <span class="badge badge-success">{{ $t('portal.active') }}</span>
        </div>

        <div v-if="access.license_key" class="portal-license">
          <span class="portal-license-label">{{ $t('portal.licenseKey') }}</span>
          <code class="portal-license-key">{{ access.license_key }}</code>
          <button class="btn btn-ghost btn-sm" @click="copyKey">{{ copied ? $t('portal.copied') : $t('portal.copy') }}</button>
        </div>

        <div v-if="access.download_urls.length" class="portal-files">
          <div class="portal-files-title">{{ $t('portal.files') }}</div>
          <a v-for="f in access.download_urls" :key="f.asset_id" :href="f.url" class="portal-file">
            <span>{{ f.name }}</span>
            <span class="portal-file-size">{{ formatBytes(f.size_bytes) }} · {{ $t('portal.download') }} ↓</span>
          </a>
          <p class="portal-hint">{{ $t('portal.linkExpiry') }}</p>
        </div>
        <p v-else-if="!access.license_key" class="portal-hint">{{ $t('portal.noFiles') }}</p>
      </div>

      <!-- ── Retrouver mes achats ────────────────────────────────────── -->
      <div class="portal-divider">{{ $t('portal.or') }}</div>
      <form class="portal-form" @submit.prevent="requestLinks">
        <label class="form-label">{{ $t('portal.lostLabel') }}</label>
        <div class="portal-row">
          <input v-model.trim="email" type="email" class="form-input" placeholder="vous@exemple.com" />
          <button type="submit" class="btn btn-secondary" :disabled="sending || !email">
            <span v-if="sending" class="spinner-sm"></span>
            {{ $t('portal.sendLinks') }}
          </button>
        </div>
        <p v-if="requested" class="portal-ok">{{ $t('portal.requested') }}</p>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import client from '@/api/client'
import { t } from '@/i18n'

interface PortalAccess {
  product_name: string | null
  seller_name: string | null
  fulfillment_type: string
  license_key: string | null
  download_urls: { asset_id: string; name: string; size_bytes: number; url: string }[]
}

const route   = useRoute()
const token   = ref('')
const email   = ref('')
const access  = ref<PortalAccess | null>(null)
const loading = ref(false)
const sending = ref(false)
const error   = ref('')
const requested = ref(false)
const copied  = ref(false)

async function loadAccess() {
  loading.value = true
  error.value = ''
  access.value = null
  try {
    access.value = (await client.post('/api/portal/digital/access', { token: token.value })).data.data
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('portal.notFound')
  } finally {
    loading.value = false
  }
}

async function requestLinks() {
  sending.value = true
  requested.value = false
  try {
    await client.post('/api/portal/digital/request-links', { email: email.value })
  } catch { /* réponse générique quoi qu'il arrive */ }
  requested.value = true
  sending.value = false
}

function copyKey() {
  if (!access.value?.license_key) return
  navigator.clipboard?.writeText(access.value.license_key)
  copied.value = true
  setTimeout(() => (copied.value = false), 1500)
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
}

onMounted(() => {
  const q = route.query.token
  if (typeof q === 'string' && q) {
    token.value = q
    loadAccess() // lien magique : chargement direct
  }
})
</script>

<style scoped>
.portal-wrap {
  min-height: 100vh;
  display: flex; align-items: flex-start; justify-content: center;
  background: var(--gray-50, #f8fafc); padding: 48px 16px;
}
.portal-card {
  width: 100%; max-width: 560px; background: white;
  border: 1px solid var(--gray-200, #e2e8f0); border-radius: 12px; padding: 28px;
}
.portal-title { font-size: 1.3rem; margin: 0 0 4px; }
.portal-sub { color: var(--gray-500, #64748b); font-size: 0.9rem; margin: 0 0 20px; }
.portal-form { margin-bottom: 4px; }
.portal-row { display: flex; gap: 8px; }
.portal-row .form-input { flex: 1; }
.portal-error { color: #dc2626; font-size: 0.85rem; margin-top: 8px; }
.portal-ok { color: #059669; font-size: 0.85rem; margin-top: 8px; }
.portal-result { margin-top: 18px; border: 1px solid var(--gray-200, #e2e8f0); border-radius: 10px; padding: 16px; }
.portal-product { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.portal-product-name { font-weight: 700; }
.portal-product-seller { font-size: 0.8rem; color: var(--gray-500, #64748b); }
.portal-license { display: flex; align-items: center; gap: 10px; margin-top: 14px; background: var(--gray-50, #f8fafc); border-radius: 8px; padding: 10px 12px; }
.portal-license-label { font-size: 0.75rem; color: var(--gray-500, #64748b); text-transform: uppercase; }
.portal-license-key { font-weight: 700; letter-spacing: 0.05em; }
.portal-files { margin-top: 14px; }
.portal-files-title { font-size: 0.8rem; font-weight: 600; color: var(--gray-600, #475569); margin-bottom: 6px; }
.portal-file {
  display: flex; justify-content: space-between; gap: 8px; padding: 9px 12px;
  border: 1px solid var(--gray-200, #e2e8f0); border-radius: 8px; margin-bottom: 6px;
  text-decoration: none; color: inherit; font-size: 0.9rem;
}
.portal-file:hover { background: var(--gray-50, #f8fafc); }
.portal-file-size { color: var(--gray-500, #64748b); font-size: 0.8rem; white-space: nowrap; }
.portal-hint { font-size: 0.75rem; color: var(--gray-400, #94a3b8); margin-top: 6px; }
.portal-divider {
  text-align: center; color: var(--gray-400, #94a3b8); font-size: 0.78rem;
  margin: 18px 0 12px; text-transform: uppercase; letter-spacing: 0.08em;
}
</style>
