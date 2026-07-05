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

      <!-- ── Mon compte (RC-7C) ──────────────────────────────────────── -->
      <div class="portal-divider">{{ $t('portal.account.heading') }}</div>

      <!-- Connecté : liste des achats -->
      <div v-if="acct.token" class="portal-account">
        <div class="portal-account-bar">
          <span>{{ $t('portal.account.signedInAs', { email: acct.email }) }}</span>
          <button class="btn btn-ghost btn-sm" @click="logout">{{ $t('portal.account.logout') }}</button>
        </div>
        <div v-if="purchases.length" class="portal-files">
          <a v-for="(p, i) in purchases" :key="i" :href="p.portal_link" class="portal-file">
            <span>{{ p.product_name }} <em class="portal-product-seller">· {{ p.seller_name }}</em></span>
            <span class="portal-file-size">{{ $t('portal.account.open') }} →</span>
          </a>
        </div>
        <p v-else class="portal-hint">{{ $t('portal.account.empty') }}</p>
      </div>

      <!-- Non connecté : login / inscription -->
      <div v-else>
        <div class="portal-tabs">
          <button class="portal-tab" :class="{ active: acct.mode === 'login' }" @click="setMode('login')">{{ $t('portal.account.login') }}</button>
          <button class="portal-tab" :class="{ active: acct.mode === 'register' }" @click="setMode('register')">{{ $t('portal.account.register') }}</button>
        </div>

        <form v-if="acct.mode === 'login'" class="portal-form" @submit.prevent="login">
          <input v-model.trim="acct.email" type="email" class="form-input" :placeholder="$t('portal.account.email')" style="margin-bottom:8px" />
          <input v-model="acct.password" type="password" class="form-input" :placeholder="$t('portal.account.password')" style="margin-bottom:8px" />
          <button type="submit" class="btn btn-primary" :disabled="acct.busy || !acct.email || !acct.password">
            <span v-if="acct.busy" class="spinner-sm"></span>{{ $t('portal.account.login') }}
          </button>
        </form>

        <form v-else-if="acct.mode === 'register' && !acct.awaitingCode" class="portal-form" @submit.prevent="register">
          <input v-model.trim="acct.email" type="email" class="form-input" :placeholder="$t('portal.account.email')" style="margin-bottom:8px" />
          <input v-model="acct.password" type="password" class="form-input" :placeholder="$t('portal.account.passwordNew')" style="margin-bottom:8px" />
          <button type="submit" class="btn btn-primary" :disabled="acct.busy || !acct.email || acct.password.length < 8">
            <span v-if="acct.busy" class="spinner-sm"></span>{{ $t('portal.account.getCode') }}
          </button>
          <p class="portal-hint">{{ $t('portal.account.registerHint') }}</p>
        </form>

        <form v-else class="portal-form" @submit.prevent="verify">
          <p class="portal-hint" style="margin-bottom:8px">{{ $t('portal.account.codeSent', { email: acct.email }) }}</p>
          <div class="portal-row">
            <input v-model.trim="acct.code" class="form-input" :placeholder="$t('portal.account.code')" />
            <button type="submit" class="btn btn-primary" :disabled="acct.busy || !acct.code">
              <span v-if="acct.busy" class="spinner-sm"></span>{{ $t('portal.account.verify') }}
            </button>
          </div>
          <!-- Si le compte existait déjà (vérifié), aucun code n'est envoyé : proposer la connexion. -->
          <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px" @click="setMode('login')">
            {{ $t('portal.account.alreadyRegistered') }}
          </button>
        </form>

        <p v-if="acct.error" class="portal-error">{{ acct.error }}</p>
        <p v-if="acct.notice" class="portal-ok">{{ acct.notice }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import client from '@/api/client'
import { t } from '@/i18n'

// RC-7C — instance dédiée au portail : PAS d'interceptor tenant (un 401 ici ne doit pas rediriger
// vers le login opérateur). Le token de compte portail est distinct et géré localement.
const portalApi = axios.create({ baseURL: import.meta.env.VITE_API_BASE_URL ?? '', headers: { Accept: 'application/json' } })

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

// ── Mon compte (RC-7C) ────────────────────────────────────────────────────────
const PORTAL_TOKEN_KEY = 'portal_token'
interface Purchase { product_name: string | null; seller_name: string | null; portal_link: string }
const purchases = ref<Purchase[]>([])
const acct = reactive({
  mode: 'login' as 'login' | 'register',
  email: '', password: '', code: '',
  awaitingCode: false, busy: false, error: '', notice: '',
  token: localStorage.getItem(PORTAL_TOKEN_KEY) || '',
})

function setMode(m: 'login' | 'register') {
  acct.mode = m
  acct.awaitingCode = false
  acct.error = ''
  acct.notice = ''
}

async function register() {
  acct.busy = true; acct.error = ''; acct.notice = ''
  try {
    await portalApi.post('/api/portal/register', { email: acct.email, password: acct.password })
    acct.awaitingCode = true
    acct.notice = t('portal.account.codeSent', { email: acct.email })
  } catch (e: any) {
    acct.error = e?.response?.data?.message ?? t('portal.account.genericError')
  } finally { acct.busy = false }
}

async function verify() {
  acct.busy = true; acct.error = ''
  try {
    // Le backend exige le mot de passe à la vérification (liaison anti-usurpation) : encore en
    // mémoire depuis l'inscription.
    await portalApi.post('/api/portal/verify', { email: acct.email, code: acct.code, password: acct.password })
    acct.awaitingCode = false
    acct.mode = 'login'
    acct.code = ''
    acct.notice = t('portal.account.verified')
  } catch (e: any) {
    acct.error = e?.response?.data?.message ?? t('portal.account.badCode')
  } finally { acct.busy = false }
}

async function login() {
  acct.busy = true; acct.error = ''
  try {
    const res = await portalApi.post('/api/portal/login', { email: acct.email, password: acct.password })
    acct.token = res.data.token
    localStorage.setItem(PORTAL_TOKEN_KEY, acct.token)
    acct.password = ''
    await loadMyPurchases()
  } catch (e: any) {
    acct.error = e?.response?.data?.message ?? t('portal.account.badCredentials')
  } finally { acct.busy = false }
}

async function loadMyPurchases() {
  try {
    purchases.value = (await portalApi.get('/api/portal/my-purchases', {
      headers: { Authorization: `Bearer ${acct.token}` },
    })).data.data
  } catch {
    // token expiré/invalide → déconnexion silencieuse
    logout()
  }
}

function logout() {
  acct.token = ''
  purchases.value = []
  localStorage.removeItem(PORTAL_TOKEN_KEY)
}

onMounted(() => {
  const q = route.query.token
  if (typeof q === 'string' && q) {
    token.value = q
    loadAccess() // lien magique : chargement direct
  }
  if (acct.token) loadMyPurchases() // session compte persistée
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
.portal-tabs { display: flex; gap: 4px; margin-bottom: 10px; }
.portal-tab {
  flex: 1; border: 1px solid var(--gray-200, #e2e8f0); background: white; padding: 8px;
  border-radius: 8px; cursor: pointer; font-size: 0.85rem; color: var(--gray-500, #64748b);
}
.portal-tab.active { border-color: var(--brand-primary, #16a34a); color: var(--brand-primary, #16a34a); font-weight: 600; }
.portal-account-bar {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 0.85rem; color: var(--gray-600, #475569); margin-bottom: 10px;
}
</style>
