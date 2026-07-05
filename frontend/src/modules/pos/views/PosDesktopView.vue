<template>
  <div class="posd">
    <div v-if="s.loading.value" class="posd-loading">{{ $t('pos.loading') }}</div>

    <!-- ── Open-session screen ─────────────────────────────────────────────── -->
    <div v-else-if="!s.session.value" class="posd-open">
      <div class="posd-open-card">
        <h1 class="posd-open-title">{{ $t('pos.openTitle') }}</h1>
        <p class="posd-open-sub">{{ $t('pos.openSub') }}</p>
        <label class="posd-field">
          <span>{{ $t('pos.openingFloat') }}</span>
          <div class="posd-amount">
            <input v-model.number="openingFloat" type="number" min="0" step="any" class="posd-input" data-test="opening-float" />
            <span class="posd-cur">{{ currency }}</span>
          </div>
        </label>
        <p v-if="s.error.value" class="posd-error">{{ $t('pos.openError') }}</p>
        <button class="posd-btn posd-btn--primary posd-btn--lg" :disabled="s.busy.value" data-test="open-session" @click="doOpen">
          {{ s.busy.value ? $t('pos.opening') : $t('pos.openButton') }}
        </button>
      </div>
    </div>

    <!-- ── Terminal ────────────────────────────────────────────────────────── -->
    <div v-else class="posd-term">
      <header class="posd-head">
        <div class="posd-head-l">
          <span class="posd-badge">{{ $t('pos.sessionOpen') }}</span>
          <span class="posd-meta">{{ s.session.value.label || $t('pos.sessionFallback') }} · {{ $t('pos.salesCount', { count: s.session.value.sales_count }) }}</span>
        </div>
        <div class="posd-head-r">
          <span class="posd-expected" data-test="expected-cash">{{ $t('pos.expectedCash') }} : <strong>{{ fmt(s.session.value.expected_cash_cents) }}</strong></span>
          <button class="posd-btn posd-btn--ghost" :disabled="!lastSale" data-test="open-receipt" @click="openReceipt()">{{ $t('posReceipt.print') }}</button>
          <button class="posd-btn posd-btn--ghost" data-test="open-movement" @click="movement.open = true">{{ $t('posDesktop.cashMovement') }}</button>
          <button class="posd-btn posd-btn--ghost" :disabled="!lastSale" data-test="open-refund" @click="refund.open = true">{{ $t('posDesktop.refund') }}</button>
          <button class="posd-btn posd-btn--ghost" data-test="open-close" @click="openClose">{{ $t('pos.closeRegister') }}</button>
        </div>
      </header>

      <div class="posd-body">
        <!-- Catalog grid -->
        <section class="posd-catalog">
          <input
            ref="searchInput" v-model="query" class="posd-input posd-search" data-test="product-search"
            :placeholder="$t('posDesktop.searchHint')" @input="onSearch" @keyup.enter="onScan"
          />
          <div v-if="searching" class="posd-hint">{{ $t('pos.searching') }}</div>
          <div v-else-if="results.length === 0 && query" class="posd-hint">{{ $t('pos.noProducts') }}</div>
          <div class="posd-grid">
            <button v-for="p in results" :key="p.id" class="posd-tile" :data-test="`product-${p.sku}`" @click="addProduct(p)">
              <span class="posd-tile-name">{{ p.name }}</span>
              <span class="posd-tile-sku">{{ p.sku }}</span>
              <span class="posd-tile-price">{{ fmt(p.price?.amount) }}</span>
            </button>
          </div>
        </section>

        <!-- Cart + payment pad -->
        <section class="posd-cart">
          <h2 class="posd-cart-title">{{ $t('pos.cart') }} <span v-if="s.cartCount.value" class="posd-cart-count">{{ s.cartCount.value }}</span></h2>
          <div v-if="s.cart.value.length === 0" class="posd-cart-empty">{{ $t('pos.cartEmpty') }}</div>
          <ul v-else class="posd-lines">
            <li v-for="(line, i) in s.cart.value" :key="line.product_id + (line.variant_id ?? '')" class="posd-line">
              <div class="posd-line-info">
                <span class="posd-line-name">{{ line.name }}</span>
                <span class="posd-line-sku">{{ line.sku }} · {{ fmt(line.unit_price_cents) }}</span>
              </div>
              <div class="posd-qty">
                <button class="posd-qty-btn" :data-test="`dec-${i}`" @click="s.decQty(i)">−</button>
                <span class="posd-qty-val" :data-test="`qty-${i}`">{{ line.quantity }}</span>
                <button class="posd-qty-btn" :data-test="`inc-${i}`" @click="s.incQty(i)">+</button>
              </div>
              <span class="posd-line-total">{{ fmt(line.unit_price_cents * line.quantity) }}</span>
            </li>
          </ul>

          <div class="posd-foot">
            <div class="posd-total-row">
              <span>{{ $t('common.total') }}</span>
              <strong data-test="cart-total">{{ fmt(s.cartTotalCents.value) }}</strong>
            </div>

            <!-- Split payment pad -->
            <div v-if="legs.length" class="posd-legs" data-test="legs">
              <div v-for="(leg, i) in legs" :key="i" class="posd-leg">
                <span>{{ $t(`payments.method.${leg.method}`) }}</span>
                <span>{{ fmt(leg.amount_cents) }}</span>
                <button class="posd-leg-rm" :data-test="`rm-leg-${i}`" @click="legs.splice(i, 1)">×</button>
              </div>
              <div class="posd-remaining" :class="{ 'posd-remaining--ok': remainingCents === 0 }" data-test="remaining">
                {{ $t('posDesktop.remaining') }} : <strong>{{ fmt(remainingCents) }}</strong>
              </div>
            </div>

            <div class="posd-pay-controls">
              <select v-model="payMethod" class="posd-input posd-input--sm" data-test="pay-method">
                <option value="cash">{{ $t('payments.method.cash') }}</option>
                <option value="mobile_money">{{ $t('payments.method.mobile_money') }}</option>
                <option value="card">{{ $t('payments.method.card') }}</option>
                <option value="transfer">{{ $t('payments.method.transfer') }}</option>
              </select>
              <input v-model.number="legAmount" type="number" min="0" step="any" class="posd-input posd-input--sm" :placeholder="$t('posDesktop.amountHint')" data-test="leg-amount" />
              <button class="posd-btn posd-btn--ghost" :disabled="!canAddLeg" data-test="add-leg" @click="addLeg">{{ $t('posDesktop.addLeg') }}</button>
            </div>

            <p v-if="checkoutError" class="posd-error" data-test="checkout-error">{{ checkoutError }}</p>

            <div class="posd-pay-actions">
              <button
                class="posd-btn posd-btn--ghost posd-btn--lg" :disabled="s.cart.value.length === 0 || s.busy.value"
                data-test="quick-cash" @click="quickCash"
              >{{ $t('posDesktop.quickCash') }}</button>
              <button
                class="posd-btn posd-btn--primary posd-btn--lg" :disabled="!canSplitCheckout || s.busy.value"
                data-test="checkout-split" @click="doSplitCheckout"
              >{{ s.busy.value ? $t('pos.checkingOut') : $t('posDesktop.settle') }}</button>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Variant picker -->
    <BaseModal v-model="picker.open" :title="$t('pos.pickVariantTitle', { name: picker.productName })">
      <div v-if="picker.loading" class="posd-hint">{{ $t('common.loading') }}</div>
      <ul v-else class="posd-variant-list">
        <li v-for="v in picker.variants" :key="v.id" class="posd-variant" :data-test="`variant-${v.sku}`" @click="pickVariant(v)">
          <span>{{ v.label }} · {{ v.sku }}</span><strong>{{ fmt(v.price_cents) }}</strong>
        </li>
      </ul>
    </BaseModal>

    <!-- Cash movement modal -->
    <BaseModal v-model="movement.open" :title="$t('posDesktop.cashMovementTitle')">
      <div class="posd-seg">
        <button :class="['posd-seg-btn', { 'posd-seg-btn--on': movement.direction === 'in' }]" data-test="mv-in" @click="movement.direction = 'in'">{{ $t('posDesktop.payIn') }}</button>
        <button :class="['posd-seg-btn', { 'posd-seg-btn--on': movement.direction === 'out' }]" data-test="mv-out" @click="movement.direction = 'out'">{{ $t('posDesktop.payOut') }}</button>
      </div>
      <label class="posd-field">
        <span>{{ $t('posDesktop.amount') }}</span>
        <div class="posd-amount"><input v-model.number="movement.amount" type="number" min="0" step="any" class="posd-input" data-test="mv-amount" /><span class="posd-cur">{{ currency }}</span></div>
      </label>
      <label class="posd-field">
        <span>{{ $t('posDesktop.reason') }}</span>
        <select v-model="movement.reason" class="posd-input" data-test="mv-reason">
          <option value="float_add">{{ $t('posDesktop.reasonFloat') }}</option>
          <option value="withdrawal">{{ $t('posDesktop.reasonWithdrawal') }}</option>
          <option value="expense">{{ $t('posDesktop.reasonExpense') }}</option>
        </select>
      </label>
      <p v-if="movement.error" class="posd-error" data-test="mv-error">{{ movement.error }}</p>
      <template #footer>
        <button class="posd-btn posd-btn--ghost" @click="movement.open = false">{{ $t('common.cancel') }}</button>
        <button class="posd-btn posd-btn--primary" :disabled="s.busy.value || !movement.amount" data-test="mv-confirm" @click="doMovement">{{ $t('common.confirm') }}</button>
      </template>
    </BaseModal>

    <!-- Refund last sale modal -->
    <BaseModal v-model="refund.open" :title="$t('posDesktop.refundTitle')">
      <div v-if="!lastSale" class="posd-hint">{{ $t('posDesktop.noSale') }}</div>
      <template v-else>
        <p class="posd-refund-sub">{{ $t('posDesktop.refundSub', { number: lastSale.number }) }}</p>
        <ul class="posd-refund-lines">
          <li v-for="rl in refundLines" :key="rl.order_line_id" class="posd-refund-line">
            <span class="posd-refund-name">{{ rl.name }}</span>
            <div class="posd-qty">
              <button class="posd-qty-btn" :data-test="`rdec-${rl.order_line_id}`" @click="rl.quantity = Math.max(0, rl.quantity - 1)">−</button>
              <span class="posd-qty-val">{{ rl.quantity }}</span>
              <button class="posd-qty-btn" :data-test="`rinc-${rl.order_line_id}`" @click="rl.quantity = Math.min(rl.max, rl.quantity + 1)">+</button>
            </div>
            <span class="posd-refund-max">/ {{ rl.max }}</span>
          </li>
        </ul>
        <label class="posd-field">
          <span>{{ $t('posDesktop.refundReason') }}</span>
          <input v-model="refund.reason" class="posd-input" data-test="refund-reason" />
        </label>
        <label class="posd-field">
          <span>{{ $t('posDesktop.refundMethod') }}</span>
          <select v-model="refund.method" class="posd-input" data-test="refund-method">
            <option value="cash">{{ $t('payments.method.cash') }}</option>
            <option value="mobile_money">{{ $t('payments.method.mobile_money') }}</option>
          </select>
        </label>
        <p v-if="refund.error" class="posd-error" data-test="refund-error">{{ refund.error }}</p>
      </template>
      <template #footer>
        <button class="posd-btn posd-btn--ghost" @click="refund.open = false">{{ $t('common.cancel') }}</button>
        <button v-if="lastSale" class="posd-btn posd-btn--primary" :disabled="s.busy.value || !canRefund" data-test="refund-confirm" @click="doRefund">{{ $t('posDesktop.refundConfirm') }}</button>
      </template>
    </BaseModal>

    <!-- Receipt modal (RC-19) -->
    <BaseModal v-model="receiptModal.open" :title="$t('posReceipt.title')">
      <div v-if="receiptModal.loading" class="posd-hint">{{ $t('common.loading') }}</div>
      <p v-else-if="receiptModal.error" class="posd-error" data-test="receipt-error">{{ $t('posReceipt.loadError') }}</p>
      <PosReceipt v-else-if="receiptModal.data" ref="receiptComp" :receipt="receiptModal.data" />
      <template #footer>
        <button class="posd-btn posd-btn--ghost" @click="receiptModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="posd-btn posd-btn--primary" :disabled="!receiptModal.data" data-test="print-receipt" @click="receiptComp?.print()">
          {{ $t('posReceipt.print') }}
        </button>
      </template>
    </BaseModal>

    <!-- Close modal -->
    <BaseModal v-model="closeModal.open" :title="$t('pos.closeTitle')">
      <div class="posd-recon">
        <div class="posd-recon-row"><span>{{ $t('pos.openingFloat') }}</span><strong>{{ fmt(s.session.value?.opening_float_cents) }}</strong></div>
        <div class="posd-recon-row"><span>{{ $t('pos.cashSales') }}</span><strong>{{ fmt(s.session.value?.cash_sales_cents) }}</strong></div>
        <div class="posd-recon-row"><span>{{ $t('posDesktop.movements') }}</span><strong>{{ fmt(s.session.value?.net_cash_movements_cents) }}</strong></div>
        <div class="posd-recon-row posd-recon-row--accent"><span>{{ $t('pos.expectedCash') }}</span><strong>{{ fmt(s.session.value?.expected_cash_cents) }}</strong></div>
      </div>
      <label class="posd-field">
        <span>{{ $t('pos.countedCash') }}</span>
        <div class="posd-amount"><input v-model.number="closeModal.counted" type="number" min="0" step="any" class="posd-input" data-test="counted-cash" /><span class="posd-cur">{{ currency }}</span></div>
      </label>
      <div class="posd-diff" :class="diffClass" data-test="difference">
        {{ $t('pos.difference') }} {{ fmt(differenceCents) }}
        <span v-if="differenceCents === 0"> ✓ {{ $t('pos.exact') }}</span>
      </div>
      <template #footer>
        <button class="posd-btn posd-btn--ghost" @click="closeModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="posd-btn posd-btn--primary" :disabled="s.busy.value" data-test="confirm-close" @click="doClose">{{ $t('pos.close') }}</button>
      </template>
    </BaseModal>

    <transition name="posd-toast">
      <div v-if="toast" class="posd-toast" data-test="toast">{{ toast }}</div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { formatMoney, toCents, fromCents } from '@/shared/utils/money'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/modules/catalog/services/productService'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { t } from '@/i18n'
import { usePosSession } from '../composables/usePosSession'
import { posService } from '../services/posService'
import PosReceipt from '../components/PosReceipt.vue'
import type { PosPaymentLeg, PosPaymentMethod, PosReceipt as PosReceiptData } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (cents?: number | null) => formatMoney(cents ?? 0, currency.value)

const s = usePosSession()

const openingFloat = ref(0)
async function doOpen() { await s.open(openingFloat.value) }

// ── Search / scan ────────────────────────────────────────────────────────────
const query = ref('')
const results = ref<any[]>([])
const searching = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
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
    const res = await productService.list({ search: q, status: 'active', per_page: 18 })
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

// ── Cart & variant picker ────────────────────────────────────────────────────
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

// ── Split payment pad ────────────────────────────────────────────────────────
const legs = ref<PosPaymentLeg[]>([])
const payMethod = ref<PosPaymentMethod>('cash')
const legAmount = ref<number | null>(null)
const checkoutError = ref('')

const paidCents = computed(() => legs.value.reduce((sum, l) => sum + l.amount_cents, 0))
const remainingCents = computed(() => Math.max(0, s.cartTotalCents.value - paidCents.value))
const canAddLeg = computed(() => !!legAmount.value && legAmount.value > 0 && remainingCents.value > 0)
const canSplitCheckout = computed(() => s.cart.value.length > 0 && legs.value.length > 0 && remainingCents.value === 0)

function addLeg() {
  if (!canAddLeg.value) return
  const amount = Math.min(toCents(legAmount.value as number), remainingCents.value)
  legs.value.push({ method: payMethod.value, amount_cents: amount })
  legAmount.value = null
}

/** One-tap: pay the whole total in cash (no split). */
async function quickCash() {
  checkoutError.value = ''
  try {
    const res = await s.checkout('cash')
    if (res) onSold(res)
  } catch { checkoutError.value = s.error.value === 'error' ? t('pos.checkoutError') : s.error.value }
}

async function doSplitCheckout() {
  if (!canSplitCheckout.value) return
  checkoutError.value = ''
  try {
    const res = await s.checkout(legs.value.slice())
    if (res) { legs.value = []; onSold(res) }
  } catch { checkoutError.value = s.error.value === 'error' ? t('pos.checkoutError') : s.error.value }
}

// ── Last sale (for one-click refund) ─────────────────────────────────────────
const lastSale = ref<{ id: string; number: string; lines: { order_line_id: string; name: string; max: number }[] } | null>(null)

function onSold(res: any) {
  const order = res.order
  // Enrich order lines with cart names captured before the cart was cleared.
  const names = new Map<string, string>()
  ;(order.lines ?? []).forEach((ol: any) => {
    names.set(ol.id, ol.product_name ?? ol.name ?? ol.sku ?? t('posDesktop.item'))
  })
  lastSale.value = {
    id: order.id,
    number: order.number ?? '—',
    lines: (order.lines ?? []).map((ol: any) => ({
      order_line_id: ol.id,
      name: ol.product_name ?? ol.name ?? ol.sku ?? t('posDesktop.item'),
      max: ol.quantity,
    })),
  }
  flash(t('pos.saleRecorded', { amount: fmt(res.order?.total_amount) }))
}

// ── Receipt (RC-19) ──────────────────────────────────────────────────────────
const receiptModal = reactive<{ open: boolean; loading: boolean; error: boolean; data: PosReceiptData | null }>({
  open: false, loading: false, error: false, data: null,
})
const receiptComp = ref<InstanceType<typeof PosReceipt> | null>(null)

async function openReceipt(orderId?: string) {
  const id = orderId ?? lastSale.value?.id
  if (!id) return
  receiptModal.open = true
  receiptModal.loading = true
  receiptModal.error = false
  receiptModal.data = null
  try {
    receiptModal.data = await posService.receipt(id)
  } catch {
    receiptModal.error = true
  } finally {
    receiptModal.loading = false
  }
}

// ── Cash movement ────────────────────────────────────────────────────────────
const movement = reactive<{ open: boolean; direction: 'in' | 'out'; amount: number | null; reason: string; error: string }>({
  open: false, direction: 'in', amount: null, reason: 'float_add', error: '',
})
async function doMovement() {
  movement.error = ''
  try {
    await s.cashMovement(movement.direction, movement.amount as number, movement.reason)
    movement.open = false
    movement.amount = null
    flash(t('posDesktop.movementDone'))
  } catch (e: any) {
    movement.error = e?.response?.data?.message ?? e?.response?.data?.errors?.amount_cents?.[0] ?? t('posDesktop.movementError')
  }
}

// ── Refund ───────────────────────────────────────────────────────────────────
const refund = reactive<{ open: boolean; reason: string; method: PosPaymentMethod; error: string }>({
  open: false, reason: '', method: 'cash', error: '',
})
const refundLines = ref<{ order_line_id: string; name: string; quantity: number; max: number }[]>([])

function seedRefundLines() {
  refundLines.value = (lastSale.value?.lines ?? []).map(l => ({ ...l, quantity: l.max }))
}
const canRefund = computed(() => refund.reason.trim().length > 0 && refundLines.value.some(l => l.quantity > 0))

async function doRefund() {
  if (!lastSale.value || !canRefund.value) return
  refund.error = ''
  try {
    const lines = refundLines.value.filter(l => l.quantity > 0).map(l => ({ order_line_id: l.order_line_id, quantity: l.quantity }))
    const res = await s.refund(lastSale.value.id, lines, refund.reason.trim(), refund.method)
    refund.open = false
    lastSale.value = null
    flash(t('posDesktop.refundDone', { amount: fmt(res.return.refund_amount_cents) }))
  } catch (e: any) {
    refund.error = e?.response?.data?.message ?? t('posDesktop.refundError')
  }
}

// ── Close ────────────────────────────────────────────────────────────────────
const closeModal = reactive<{ open: boolean; counted: number }>({ open: false, counted: 0 })
const differenceCents = computed(() => toCents(closeModal.counted) - (s.session.value?.expected_cash_cents ?? 0))
const diffClass = computed(() => ({
  'posd-diff--ok': differenceCents.value === 0,
  'posd-diff--over': differenceCents.value > 0,
  'posd-diff--short': differenceCents.value < 0,
}))
function openClose() {
  closeModal.counted = fromCents(s.session.value?.expected_cash_cents ?? 0)
  closeModal.open = true
}
async function doClose() {
  const closed = await s.close(closeModal.counted)
  closeModal.open = false
  lastSale.value = null
  openingFloat.value = 0
  if (closed) flash(t('pos.registerClosed', { diff: formatMoney(closed.difference_cents ?? 0, currency.value) }))
}

// ── Toast + hotkeys ──────────────────────────────────────────────────────────
const toast = ref('')
function flash(msg: string) { toast.value = msg; setTimeout(() => { toast.value = '' }, 3000) }

function onKey(e: KeyboardEvent) {
  if (!s.session.value) return
  if (e.key === 'F2') { e.preventDefault(); searchInput.value?.focus() }
  else if (e.key === 'F9') { e.preventDefault(); movement.open = true }
  else if (e.key === 'F8' && lastSale.value) { e.preventDefault(); refund.open = true }
  else if (e.key === 'F7' && lastSale.value) { e.preventDefault(); openReceipt() }
}

// Seed the refund editor whenever the refund modal opens.
function watchRefundOpen() {
  if (refund.open) { refund.reason = ''; refund.method = 'cash'; refund.error = ''; seedRefundLines() }
}

// Seed the refund editor whenever the refund modal opens.
watch(() => refund.open, watchRefundOpen)

onMounted(async () => {
  await s.load()
  window.addEventListener('keydown', onKey)
  await nextTick()
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.posd { height: 100%; display: flex; flex-direction: column; }
.posd-loading, .posd-hint { padding: 1rem; color: var(--gray-500); }

.posd-open { display: flex; align-items: center; justify-content: center; min-height: 70vh; }
.posd-open-card { width: 100%; max-width: 420px; background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
.posd-open-title { font-size: 1.35rem; font-weight: 700; margin: 0 0 .25rem; }
.posd-open-sub { color: var(--gray-500); margin: 0 0 1.25rem; font-size: .9rem; }

.posd-field { display: block; margin-bottom: 1rem; }
.posd-field > span { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-600); margin-bottom: .35rem; }
.posd-input { width: 100%; padding: .7rem .8rem; border: 1px solid var(--gray-300, #d1d5db); border-radius: 10px; font-size: 1rem; }
.posd-input--sm { padding: .5rem .6rem; font-size: .9rem; }
.posd-amount { position: relative; display: flex; align-items: center; }
.posd-cur { position: absolute; right: .8rem; color: var(--gray-500); font-weight: 600; font-size: .85rem; }

.posd-btn { border: none; border-radius: 10px; padding: .6rem 1rem; font-weight: 600; cursor: pointer; font-size: .9rem; }
.posd-btn--primary { background: var(--brand-primary, #4f46e5); color: #fff; }
.posd-btn--primary:disabled, .posd-btn--ghost:disabled { opacity: .5; cursor: not-allowed; }
.posd-btn--ghost { background: transparent; border: 1px solid var(--gray-300, #d1d5db); color: var(--gray-700); }
.posd-btn--lg { padding: .85rem; font-size: 1rem; }

.posd-term { display: flex; flex-direction: column; height: 100%; }
.posd-head { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1rem; border-bottom: 1px solid var(--gray-200, #e5e7eb); gap: 1rem; flex-wrap: wrap; }
.posd-badge { background: #dcfce7; color: #166534; font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.posd-meta { color: var(--gray-500); font-size: .85rem; margin-left: .5rem; }
.posd-head-r { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.posd-expected { font-size: .85rem; color: var(--gray-600); margin-right: .3rem; }

.posd-body { flex: 1; display: grid; grid-template-columns: 1fr 400px; gap: 1rem; padding: 1rem; min-height: 0; }
@media (max-width: 900px) { .posd-body { grid-template-columns: 1fr; } }

.posd-catalog { display: flex; flex-direction: column; min-height: 0; }
.posd-search { margin-bottom: .75rem; }
.posd-grid { overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .6rem; align-content: start; }
.posd-tile { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px; padding: .7rem; cursor: pointer; display: flex; flex-direction: column; gap: .3rem; text-align: left; background: #fff; transition: border-color .12s, box-shadow .12s; min-height: 84px; }
.posd-tile:hover { border-color: var(--brand-primary, #4f46e5); box-shadow: 0 2px 10px rgba(79,70,229,.1); }
.posd-tile-name { font-weight: 600; font-size: .85rem; }
.posd-tile-sku { font-size: .72rem; color: var(--gray-500); }
.posd-tile-price { font-weight: 700; font-size: .95rem; margin-top: auto; }

.posd-cart { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px; display: flex; flex-direction: column; min-height: 0; background: var(--gray-50, #f9fafb); }
.posd-cart-title { font-size: .95rem; font-weight: 700; padding: .75rem 1rem; margin: 0; border-bottom: 1px solid var(--gray-200, #e5e7eb); display: flex; align-items: center; gap: .5rem; }
.posd-cart-count { background: var(--brand-primary, #4f46e5); color: #fff; border-radius: 999px; font-size: .72rem; padding: .05rem .5rem; }
.posd-cart-empty { padding: 1.5rem 1rem; color: var(--gray-500); font-size: .85rem; text-align: center; }
.posd-lines { list-style: none; margin: 0; padding: .5rem; overflow-y: auto; flex: 1; }
.posd-line { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: .5rem; padding: .5rem; border-radius: 8px; }
.posd-line:hover { background: #fff; }
.posd-line-name { font-weight: 600; font-size: .85rem; display: block; }
.posd-line-sku { font-size: .72rem; color: var(--gray-500); }
.posd-qty { display: flex; align-items: center; gap: .4rem; }
.posd-qty-btn { width: 28px; height: 28px; border: 1px solid var(--gray-300, #d1d5db); background: #fff; border-radius: 6px; font-size: 1.05rem; cursor: pointer; line-height: 1; }
.posd-qty-val { min-width: 1.5rem; text-align: center; font-weight: 600; }
.posd-line-total { font-weight: 700; font-size: .88rem; min-width: 70px; text-align: right; }

.posd-foot { border-top: 1px solid var(--gray-200, #e5e7eb); padding: .9rem 1rem; }
.posd-total-row { display: flex; justify-content: space-between; align-items: baseline; font-size: 1.1rem; margin-bottom: .6rem; }
.posd-total-row strong { font-size: 1.5rem; }

.posd-legs { background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; padding: .5rem; margin-bottom: .6rem; }
.posd-leg { display: grid; grid-template-columns: 1fr auto auto; gap: .5rem; align-items: center; font-size: .85rem; padding: .2rem 0; }
.posd-leg-rm { border: none; background: transparent; color: #dc2626; cursor: pointer; font-size: 1.1rem; }
.posd-remaining { margin-top: .35rem; padding-top: .35rem; border-top: 1px dashed var(--gray-200, #e5e7eb); font-size: .85rem; color: var(--gray-600); }
.posd-remaining--ok { color: #166534; }

.posd-pay-controls { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem; margin-bottom: .6rem; }
.posd-pay-actions { display: grid; grid-template-columns: 1fr 1.4fr; gap: .5rem; }

.posd-error { color: #dc2626; font-size: .82rem; margin: .5rem 0; }

.posd-variant-list { list-style: none; margin: 0; padding: 0; max-height: 50vh; overflow-y: auto; }
.posd-variant { display: flex; justify-content: space-between; padding: .6rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; margin-bottom: .4rem; cursor: pointer; }
.posd-variant:hover { border-color: var(--brand-primary, #4f46e5); }

.posd-seg { display: flex; gap: .5rem; margin-bottom: 1rem; }
.posd-seg-btn { flex: 1; padding: .6rem; border: 1px solid var(--gray-300, #d1d5db); background: #fff; border-radius: 10px; cursor: pointer; font-weight: 600; }
.posd-seg-btn--on { border-color: var(--brand-primary, #4f46e5); background: #eef2ff; color: var(--brand-primary, #4f46e5); }

.posd-refund-sub { color: var(--gray-600); font-size: .88rem; margin: 0 0 .75rem; }
.posd-refund-lines { list-style: none; margin: 0 0 1rem; padding: 0; }
.posd-refund-line { display: grid; grid-template-columns: 1fr auto auto; gap: .6rem; align-items: center; padding: .4rem 0; }
.posd-refund-name { font-weight: 600; font-size: .85rem; }
.posd-refund-max { font-size: .78rem; color: var(--gray-500); }

.posd-recon { background: #fff; border-radius: 10px; padding: .75rem; margin-bottom: 1rem; border: 1px solid var(--gray-200, #e5e7eb); }
.posd-recon-row { display: flex; justify-content: space-between; font-size: .88rem; padding: .2rem 0; }
.posd-recon-row--accent { border-top: 1px solid var(--gray-200, #e5e7eb); margin-top: .25rem; padding-top: .45rem; font-weight: 700; }
.posd-diff { padding: .6rem; border-radius: 8px; font-weight: 600; font-size: .9rem; text-align: center; margin-bottom: .5rem; }
.posd-diff--ok { background: #dcfce7; color: #166534; }
.posd-diff--over { background: #dbeafe; color: #1e40af; }
.posd-diff--short { background: #fee2e2; color: #991b1b; }

.posd-toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); background: #111827; color: #fff; padding: .75rem 1.25rem; border-radius: 10px; font-weight: 600; z-index: 60; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
.posd-toast-enter-active, .posd-toast-leave-active { transition: opacity .2s, transform .2s; }
.posd-toast-enter-from, .posd-toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(10px); }
</style>
