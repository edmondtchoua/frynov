<template>
  <div class="posm">
    <div v-if="s.loading.value" class="posm-loading">{{ $t('pos.loading') }}</div>

    <!-- Open session -->
    <div v-else-if="!s.session.value" class="posm-open">
      <h1 class="posm-open-title">{{ $t('pos.openTitle') }}</h1>
      <p class="posm-open-sub">{{ $t('pos.openSub') }}</p>
      <label class="posm-field">
        <span>{{ $t('pos.openingFloat') }}</span>
        <div class="posm-amount"><input v-model.number="openingFloat" type="number" min="0" step="any" inputmode="decimal" class="posm-input" data-test="opening-float" /><span class="posm-cur">{{ currency }}</span></div>
      </label>
      <p v-if="s.error.value" class="posm-error">{{ $t('pos.openError') }}</p>
      <button class="posm-btn posm-btn--primary posm-btn--block" :disabled="s.busy.value" data-test="open-session" @click="doOpen">
        {{ s.busy.value ? $t('pos.opening') : $t('pos.openButton') }}
      </button>
    </div>

    <!-- Terminal -->
    <div v-else class="posm-term">
      <header class="posm-head">
        <div class="posm-head-l">
          <span class="posm-dot" :class="online ? 'posm-dot--on' : 'posm-dot--off'" :title="online ? $t('posMobile.online') : $t('posMobile.offline')" data-test="net-dot"></span>
          <span class="posm-expected" data-test="expected-cash">{{ fmt(s.session.value.expected_cash_cents) }}</span>
        </div>
        <div class="posm-head-r">
          <button v-if="pending.length" class="posm-sync" :disabled="!online || syncing" data-test="sync" @click="syncQueue">
            {{ syncing ? $t('posMobile.syncing') : $t('posMobile.sync', { count: pending.length }) }}
          </button>
          <button class="posm-icon-btn" data-test="open-close" @click="openClose">{{ $t('pos.closeRegister') }}</button>
        </div>
      </header>

      <input v-model="query" class="posm-input posm-search" data-test="product-search" inputmode="search" :placeholder="$t('posMobile.searchHint')" @input="onSearch" @keyup.enter="onScan" />

      <div class="posm-grid">
        <button v-for="p in results" :key="p.id" class="posm-tile" :data-test="`product-${p.sku}`" @click="addProduct(p)">
          <span class="posm-tile-name">{{ p.name }}</span>
          <span class="posm-tile-price">{{ fmt(p.price?.amount) }}</span>
        </button>
      </div>
      <p v-if="searching" class="posm-hint">{{ $t('pos.searching') }}</p>
      <p v-else-if="results.length === 0 && query" class="posm-hint">{{ $t('pos.noProducts') }}</p>

      <!-- Cart sheet -->
      <div v-if="s.cart.value.length" class="posm-cart" data-test="cart">
        <ul class="posm-lines">
          <li v-for="(line, i) in s.cart.value" :key="line.product_id + (line.variant_id ?? '')" class="posm-line">
            <span class="posm-line-name">{{ line.name }}</span>
            <div class="posm-qty">
              <button class="posm-qty-btn" :data-test="`dec-${i}`" @click="s.decQty(i)">−</button>
              <span class="posm-qty-val" :data-test="`qty-${i}`">{{ line.quantity }}</span>
              <button class="posm-qty-btn" :data-test="`inc-${i}`" @click="s.incQty(i)">+</button>
            </div>
            <span class="posm-line-total">{{ fmt(line.unit_price_cents * line.quantity) }}</span>
          </li>
        </ul>
        <button class="posm-btn posm-btn--primary posm-btn--pay" :disabled="s.busy.value" data-test="open-pay" @click="pay.open = true">
          {{ $t('posMobile.pay', { amount: fmt(s.cartTotalCents.value) }) }}
        </button>
      </div>
    </div>

    <!-- Variant picker -->
    <BaseModal v-model="picker.open" :title="$t('pos.pickVariantTitle', { name: picker.productName })">
      <div v-if="picker.loading" class="posm-hint">{{ $t('common.loading') }}</div>
      <ul v-else class="posm-variant-list">
        <li v-for="v in picker.variants" :key="v.id" class="posm-variant" :data-test="`variant-${v.sku}`" @click="pickVariant(v)">
          <span>{{ v.label }}</span><strong>{{ fmt(v.price_cents) }}</strong>
        </li>
      </ul>
    </BaseModal>

    <!-- Payment sheet -->
    <BaseModal v-model="pay.open" :title="$t('posMobile.payTitle', { amount: fmt(s.cartTotalCents.value) })">
      <div class="posm-pay-methods">
        <button :class="['posm-method', { 'posm-method--on': pay.method === 'cash' }]" data-test="pay-cash" @click="pay.method = 'cash'">{{ $t('payments.method.cash') }}</button>
        <button :class="['posm-method', { 'posm-method--on': pay.method === 'mobile_money' }]" data-test="pay-mm" @click="pay.method = 'mobile_money'">{{ $t('payments.method.mobile_money') }}</button>
      </div>
      <label v-if="pay.method === 'mobile_money'" class="posm-field">
        <span>{{ $t('posMobile.reference') }}</span>
        <input v-model="pay.reference" class="posm-input" data-test="pay-reference" :placeholder="$t('posMobile.referenceHint')" />
      </label>
      <p v-if="pay.error" class="posm-error" data-test="pay-error">{{ pay.error }}</p>
      <template #footer>
        <button class="posm-btn posm-btn--ghost" @click="pay.open = false">{{ $t('common.cancel') }}</button>
        <button class="posm-btn posm-btn--primary" :disabled="s.busy.value" data-test="pay-confirm" @click="doPay">{{ $t('posMobile.confirmPay') }}</button>
      </template>
    </BaseModal>

    <!-- Close -->
    <BaseModal v-model="closeModal.open" :title="$t('pos.closeTitle')">
      <div class="posm-recon">
        <div class="posm-recon-row"><span>{{ $t('pos.expectedCash') }}</span><strong>{{ fmt(s.session.value?.expected_cash_cents) }}</strong></div>
      </div>
      <label class="posm-field">
        <span>{{ $t('pos.countedCash') }}</span>
        <div class="posm-amount"><input v-model.number="closeModal.counted" type="number" min="0" step="any" inputmode="decimal" class="posm-input" data-test="counted-cash" /><span class="posm-cur">{{ currency }}</span></div>
      </label>
      <p v-if="pending.length" class="posm-warn" data-test="close-warn">{{ $t('posMobile.closeWarn', { count: pending.length }) }}</p>
      <template #footer>
        <button class="posm-btn posm-btn--ghost" @click="closeModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="posm-btn posm-btn--primary" :disabled="s.busy.value" data-test="confirm-close" @click="doClose">{{ $t('pos.close') }}</button>
      </template>
    </BaseModal>

    <transition name="posm-toast">
      <div v-if="toast" class="posm-toast" data-test="toast">{{ toast }}</div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { formatMoney, toCents, fromCents } from '@/shared/utils/money'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/modules/catalog/services/productService'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { t } from '@/i18n'
import { usePosSession } from '../composables/usePosSession'
import { usePosOfflineQueue, type QueuedSale } from '../composables/posOfflineQueue'
import { posService } from '../services/posService'
import type { PosPaymentMethod } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (cents?: number | null) => formatMoney(cents ?? 0, currency.value)

const s = usePosSession()
const { queue: pending, enqueue, flush } = usePosOfflineQueue()

const openingFloat = ref(0)
async function doOpen() { await s.open(openingFloat.value) }

// ── Connectivity ─────────────────────────────────────────────────────────────
const online = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
function setOnline() { online.value = true; if (pending.value.length) syncQueue() }
function setOffline() { online.value = false }

// ── Search / scan ────────────────────────────────────────────────────────────
const query = ref('')
const results = ref<any[]>([])
const searching = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

function onSearch() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(runSearch, 250)
}
async function runSearch() {
  const q = query.value.trim()
  if (!q) { results.value = []; return }
  searching.value = true
  try {
    const res = await productService.list({ search: q, status: 'active', per_page: 12 })
    results.value = res.data ?? []
  } catch { results.value = [] }
  finally { searching.value = false }
}
async function onScan() {
  const code = query.value.trim()
  if (!code) return
  try {
    const p = await productService.getBySku(code)
    if (p) { await addProduct(p); query.value = ''; results.value = [] }
  } catch { runSearch() }
}

// ── Cart & variants ──────────────────────────────────────────────────────────
const picker = reactive<{ open: boolean; loading: boolean; productId: string; productName: string; variants: any[] }>({
  open: false, loading: false, productId: '', productName: '', variants: [],
})
async function addProduct(p: any) {
  const productId = p.product_id ?? p.id
  if (p.has_variants) {
    Object.assign(picker, { open: true, loading: true, productId, productName: p.name, variants: [] })
    try {
      const detail = await productService.getDetail(p.id)
      picker.variants = (detail.variants ?? [])
        .filter((v: any) => v.is_active !== false)
        .map((v: any) => ({ id: v.id, sku: v.sku, label: v.label ?? v.name ?? v.sku, price_cents: v.price?.amount ?? p.price?.amount ?? 0 }))
    } catch { picker.variants = [] }
    finally { picker.loading = false }
    return
  }
  s.addLine(productId, null, p.name, p.sku, p.price?.amount ?? 0)
}
function pickVariant(v: any) {
  s.addLine(picker.productId, v.id, `${picker.productName} — ${v.label}`, v.sku, v.price_cents)
  picker.open = false
}

// ── Payment (single tender; offline-aware) ───────────────────────────────────
const pay = reactive<{ open: boolean; method: PosPaymentMethod; reference: string; error: string }>({
  open: false, method: 'mobile_money', reference: '', error: '',
})

async function doPay() {
  if (!s.session.value || s.cart.value.length === 0) return
  pay.error = ''
  const total = s.cartTotalCents.value
  const payload = {
    items: s.cart.value.map(l => ({ product_id: l.product_id, variant_id: l.variant_id ?? undefined, quantity: l.quantity })),
    method: pay.method,
    reference: pay.method === 'mobile_money' ? (pay.reference || undefined) : undefined,
  }
  try {
    const res = await s.checkout(pay.method, pay.reference || undefined)
    if (res) { pay.open = false; pay.reference = ''; flash(t('pos.saleRecorded', { amount: fmt(total) })) }
  } catch (e: any) {
    // No server response → offline / network error → queue the sale locally.
    if (!e?.response) {
      enqueue({ session_id: s.session.value.id, payload, total_cents: total }, cryptoId(), nowIso())
      s.clearCart()
      pay.open = false
      pay.reference = ''
      flash(t('posMobile.queued'))
    } else {
      pay.error = e?.response?.data?.message ?? e?.response?.data?.errors?.payments?.[0] ?? t('pos.checkoutError')
    }
  }
}

function cryptoId(): string {
  try { return crypto.randomUUID() } catch { return `q-${Date.now()}-${Math.floor(Math.random() * 1e6)}` }
}
function nowIso(): string { return new Date().toISOString() }

// ── Offline sync ─────────────────────────────────────────────────────────────
const syncing = ref(false)
async function syncQueue() {
  if (syncing.value || !online.value) return
  syncing.value = true
  try {
    const sent = await flush(async (sale: QueuedSale) => {
      const res = await posService.checkout(sale.session_id, sale.payload)
      // Keep the header figures fresh from the last flushed sale of the current session.
      if (s.session.value && res.session?.id === s.session.value.id) s.session.value = res.session
    })
    if (sent > 0) flash(t('posMobile.synced', { count: sent }))
  } finally {
    syncing.value = false
  }
}

// ── Close ────────────────────────────────────────────────────────────────────
const closeModal = reactive<{ open: boolean; counted: number }>({ open: false, counted: 0 })
function openClose() {
  closeModal.counted = fromCents(s.session.value?.expected_cash_cents ?? 0)
  closeModal.open = true
}
async function doClose() {
  const closed = await s.close(closeModal.counted)
  closeModal.open = false
  openingFloat.value = 0
  if (closed) flash(t('pos.registerClosed', { diff: formatMoney(closed.difference_cents ?? 0, currency.value) }))
}

// ── Toast ────────────────────────────────────────────────────────────────────
const toast = ref('')
function flash(msg: string) { toast.value = msg; setTimeout(() => { toast.value = '' }, 3000) }

onMounted(async () => {
  await s.load()
  window.addEventListener('online', setOnline)
  window.addEventListener('offline', setOffline)
})
onBeforeUnmount(() => {
  window.removeEventListener('online', setOnline)
  window.removeEventListener('offline', setOffline)
})
</script>

<style scoped>
.posm { min-height: 100%; display: flex; flex-direction: column; padding-bottom: 1rem; }
.posm-loading, .posm-hint { padding: 1rem; color: var(--gray-500); text-align: center; }

.posm-open { padding: 1.5rem 1.25rem; max-width: 480px; margin: 0 auto; width: 100%; }
.posm-open-title { font-size: 1.35rem; font-weight: 700; margin: .5rem 0 .25rem; }
.posm-open-sub { color: var(--gray-500); margin: 0 0 1.25rem; font-size: .9rem; }

.posm-field { display: block; margin-bottom: 1rem; }
.posm-field > span { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-600); margin-bottom: .35rem; }
.posm-input { width: 100%; padding: .85rem .8rem; border: 1px solid var(--gray-300, #d1d5db); border-radius: 12px; font-size: 1rem; }
.posm-amount { position: relative; display: flex; align-items: center; }
.posm-cur { position: absolute; right: .9rem; color: var(--gray-500); font-weight: 600; }

.posm-btn { border: none; border-radius: 12px; padding: .85rem 1rem; font-weight: 700; cursor: pointer; font-size: 1rem; }
.posm-btn--primary { background: var(--brand-primary, #4f46e5); color: #fff; }
.posm-btn--primary:disabled { opacity: .55; }
.posm-btn--ghost { background: transparent; border: 1px solid var(--gray-300, #d1d5db); color: var(--gray-700); }
.posm-btn--block { width: 100%; }

.posm-term { display: flex; flex-direction: column; }
.posm-head { display: flex; align-items: center; justify-content: space-between; padding: .6rem .9rem; border-bottom: 1px solid var(--gray-200, #e5e7eb); position: sticky; top: 0; background: #fff; z-index: 5; }
.posm-head-l { display: flex; align-items: center; gap: .5rem; }
.posm-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.posm-dot--on { background: #22c55e; }
.posm-dot--off { background: #ef4444; }
.posm-expected { font-weight: 700; font-size: 1.05rem; }
.posm-head-r { display: flex; align-items: center; gap: .5rem; }
.posm-sync { background: #fef3c7; color: #92400e; border: none; border-radius: 999px; padding: .35rem .7rem; font-size: .78rem; font-weight: 700; cursor: pointer; }
.posm-sync:disabled { opacity: .6; }
.posm-icon-btn { background: transparent; border: 1px solid var(--gray-300, #d1d5db); border-radius: 10px; padding: .4rem .7rem; font-size: .8rem; cursor: pointer; }

.posm-search { margin: .75rem .9rem; width: auto; }
.posm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: .6rem; padding: 0 .9rem; }
.posm-tile { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 14px; padding: .8rem; min-height: 92px; display: flex; flex-direction: column; justify-content: space-between; background: #fff; cursor: pointer; text-align: left; }
.posm-tile:active { background: #eef2ff; }
.posm-tile-name { font-weight: 600; font-size: .88rem; }
.posm-tile-price { font-weight: 700; font-size: 1rem; }

.posm-cart { position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--gray-200, #e5e7eb); padding: .6rem .9rem 0; box-shadow: 0 -4px 16px rgba(0,0,0,.06); margin-top: 1rem; }
.posm-lines { list-style: none; margin: 0; padding: 0; max-height: 34vh; overflow-y: auto; }
.posm-line { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: .6rem; padding: .5rem 0; border-bottom: 1px solid var(--gray-100, #f3f4f6); }
.posm-line-name { font-weight: 600; font-size: .88rem; }
.posm-qty { display: flex; align-items: center; gap: .5rem; }
.posm-qty-btn { width: 34px; height: 34px; border: 1px solid var(--gray-300, #d1d5db); background: #fff; border-radius: 9px; font-size: 1.2rem; cursor: pointer; line-height: 1; }
.posm-qty-val { min-width: 1.6rem; text-align: center; font-weight: 700; }
.posm-line-total { font-weight: 700; font-size: .9rem; min-width: 66px; text-align: right; }
.posm-btn--pay { width: 100%; margin: .6rem 0; padding: 1rem; font-size: 1.05rem; }

.posm-variant-list { list-style: none; margin: 0; padding: 0; }
.posm-variant { display: flex; justify-content: space-between; padding: .85rem .6rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px; margin-bottom: .5rem; cursor: pointer; }

.posm-pay-methods { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-bottom: 1rem; }
.posm-method { padding: 1rem; border: 1px solid var(--gray-300, #d1d5db); background: #fff; border-radius: 12px; font-weight: 700; cursor: pointer; }
.posm-method--on { border-color: var(--brand-primary, #4f46e5); background: #eef2ff; color: var(--brand-primary, #4f46e5); }

.posm-recon { background: var(--gray-50, #f9fafb); border-radius: 10px; padding: .75rem; margin-bottom: 1rem; }
.posm-recon-row { display: flex; justify-content: space-between; font-weight: 700; }
.posm-warn { background: #fef3c7; color: #92400e; border-radius: 8px; padding: .5rem .7rem; font-size: .82rem; }

.posm-error { color: #dc2626; font-size: .85rem; margin: .5rem 0; }

.posm-toast { position: fixed; bottom: 1.25rem; left: 50%; transform: translateX(-50%); background: #111827; color: #fff; padding: .75rem 1.25rem; border-radius: 12px; font-weight: 600; z-index: 60; box-shadow: 0 4px 16px rgba(0,0,0,.2); max-width: 90vw; text-align: center; }
.posm-toast-enter-active, .posm-toast-leave-active { transition: opacity .2s, transform .2s; }
.posm-toast-enter-from, .posm-toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(10px); }
</style>
