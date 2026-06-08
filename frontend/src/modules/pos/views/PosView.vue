<template>
  <div class="pos">
    <!-- Loading -->
    <div v-if="loading" class="pos-loading">Chargement de la caisse…</div>

    <!-- ── No open session → open-session screen ──────────────────────────── -->
    <div v-else-if="!session" class="pos-open">
      <div class="pos-open-card">
        <h1 class="pos-open-title">Ouvrir une session de caisse</h1>
        <p class="pos-open-sub">Saisissez le fond de caisse présent dans le tiroir avant de commencer.</p>

        <label class="pos-field">
          <span>Fond de caisse</span>
          <div class="pos-amount-input">
            <input
              v-model.number="openingFloat" type="number" min="0" step="any"
              class="pos-input" placeholder="0" data-test="opening-float"
            />
            <span class="pos-currency">{{ currency }}</span>
          </div>
        </label>

        <p v-if="openError" class="pos-error">{{ openError }}</p>

        <button class="pos-btn pos-btn--primary pos-btn--lg" :disabled="opening" data-test="open-session" @click="openSession">
          {{ opening ? 'Ouverture…' : 'Ouvrir la caisse' }}
        </button>
      </div>
    </div>

    <!-- ── Open session → terminal ────────────────────────────────────────── -->
    <div v-else class="pos-terminal">
      <!-- Header -->
      <header class="pos-header">
        <div>
          <span class="pos-badge">Caisse ouverte</span>
          <span class="pos-header-meta">{{ session.label || 'Session' }} · {{ session.sales_count }} vente(s)</span>
        </div>
        <div class="pos-header-right">
          <span class="pos-expected" data-test="expected-cash">Espèces attendues : <strong>{{ fmt(session.expected_cash_cents) }}</strong></span>
          <button class="pos-btn pos-btn--ghost" data-test="open-close-modal" @click="openCloseModal">Clôturer la caisse</button>
        </div>
      </header>

      <div class="pos-body">
        <!-- Left: product search -->
        <section class="pos-catalog">
          <input
            v-model="query" class="pos-input pos-search" data-test="product-search"
            placeholder="Rechercher un produit ou scanner un code…" @input="onSearch" @keyup.enter="onScan"
          />
          <div v-if="searching" class="pos-hint">Recherche…</div>
          <div v-else-if="results.length === 0 && query" class="pos-hint">Aucun produit trouvé.</div>
          <ul class="pos-results">
            <li v-for="p in results" :key="p.id" class="pos-result" :data-test="`product-${p.sku}`" @click="addProduct(p)">
              <div class="pos-result-main">
                <span class="pos-result-name">{{ p.name }}</span>
                <span class="pos-result-sku">{{ p.sku }}<span v-if="p.has_variants"> · {{ p.variants_count }} décl.</span></span>
              </div>
              <span class="pos-result-price">{{ fmt(p.price?.amount) }}</span>
            </li>
          </ul>
        </section>

        <!-- Right: cart -->
        <section class="pos-cart">
          <h2 class="pos-cart-title">Panier</h2>
          <div v-if="cart.length === 0" class="pos-cart-empty">Le panier est vide. Ajoutez des produits depuis la liste.</div>
          <ul v-else class="pos-cart-lines">
            <li v-for="(line, i) in cart" :key="line.product_id + (line.variant_id ?? '')" class="pos-line">
              <div class="pos-line-info">
                <span class="pos-line-name">{{ line.name }}</span>
                <span class="pos-line-sku">{{ line.sku }} · {{ fmt(line.unit_price_cents) }}</span>
              </div>
              <div class="pos-qty">
                <button class="pos-qty-btn" :data-test="`dec-${i}`" @click="decQty(i)">−</button>
                <span class="pos-qty-val" :data-test="`qty-${i}`">{{ line.quantity }}</span>
                <button class="pos-qty-btn" :data-test="`inc-${i}`" @click="incQty(i)">+</button>
              </div>
              <span class="pos-line-total">{{ fmt(line.unit_price_cents * line.quantity) }}</span>
            </li>
          </ul>

          <div class="pos-cart-foot">
            <div class="pos-total-row">
              <span>Total</span>
              <strong data-test="cart-total">{{ fmt(cartTotalCents) }}</strong>
            </div>

            <label class="pos-field">
              <span>Moyen de paiement</span>
              <select v-model="method" class="pos-input" data-test="method">
                <option value="cash">Espèces</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="card">Carte</option>
              </select>
            </label>

            <p v-if="checkoutError" class="pos-error" data-test="checkout-error">{{ checkoutError }}</p>

            <button
              class="pos-btn pos-btn--primary pos-btn--lg"
              :disabled="cart.length === 0 || checkingOut" data-test="checkout" @click="checkout"
            >
              {{ checkingOut ? 'Encaissement…' : `Encaisser ${fmt(cartTotalCents)}` }}
            </button>
          </div>
        </section>
      </div>
    </div>

    <!-- Variant picker (shared BaseModal — UX-03) -->
    <BaseModal v-model="picker.open" :title="`Choisir une déclinaison — ${picker.productName}`">
      <div v-if="picker.loading" class="pos-hint">Chargement…</div>
      <ul v-else class="pos-variant-list">
        <li v-for="v in picker.variants" :key="v.id" class="pos-variant" :data-test="`variant-${v.sku}`" @click="pickVariant(v)">
          <span>{{ v.label }} · {{ v.sku }}</span>
          <strong>{{ fmt(v.price_cents) }}</strong>
        </li>
      </ul>
      <template #footer>
        <button class="pos-btn pos-btn--ghost" @click="picker.open = false">Annuler</button>
      </template>
    </BaseModal>

    <!-- Close session modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="closeModal.open" title="Clôture de caisse">
      <div class="pos-recon">
        <div class="pos-recon-row"><span>Fond de caisse</span><strong>{{ fmt(session?.opening_float_cents) }}</strong></div>
        <div class="pos-recon-row"><span>Ventes espèces</span><strong>{{ fmt(session?.cash_sales_cents) }}</strong></div>
        <div class="pos-recon-row pos-recon-row--accent"><span>Espèces attendues</span><strong>{{ fmt(session?.expected_cash_cents) }}</strong></div>
      </div>

      <label class="pos-field">
        <span>Espèces comptées dans le tiroir</span>
        <div class="pos-amount-input">
          <input v-model.number="closeModal.counted" type="number" min="0" step="any" class="pos-input" data-test="counted-cash" />
          <span class="pos-currency">{{ currency }}</span>
        </div>
      </label>

      <div class="pos-diff" :class="diffClass" data-test="difference">
        Écart : {{ fmt(differenceCents) }}
        <span v-if="differenceCents === 0"> ✓ caisse juste</span>
        <span v-else-if="differenceCents > 0"> (surplus)</span>
        <span v-else> (manquant)</span>
      </div>

      <template #footer>
        <button class="pos-btn pos-btn--ghost" @click="closeModal.open = false">Annuler</button>
        <button class="pos-btn pos-btn--primary" :disabled="closeModal.submitting" data-test="confirm-close" @click="confirmClose">
          {{ closeModal.submitting ? 'Clôture…' : 'Clôturer' }}
        </button>
      </template>
    </BaseModal>

    <!-- Toast -->
    <transition name="pos-toast">
      <div v-if="toast" class="pos-toast" data-test="toast">{{ toast }}</div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { formatMoney, toCents, fromCents } from '@/shared/utils/money'
import { useAuthStore } from '@/stores/auth'
import { productService } from '@/modules/catalog/services/productService'
import { posService } from '../services/posService'
import BaseModal from '@/shared/ui/BaseModal.vue'
import type { CashRegisterSession, PosCartItem, PosPaymentMethod } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')

const fmt = (cents?: number | null) => formatMoney(cents ?? 0, currency.value)

const loading = ref(true)
const session = ref<CashRegisterSession | null>(null)

// ── Open session ─────────────────────────────────────────────────────────────
const openingFloat = ref<number>(0)
const opening = ref(false)
const openError = ref('')

async function loadSession() {
  loading.value = true
  try { session.value = await posService.current() }
  catch { session.value = null }
  finally { loading.value = false }
}

async function openSession() {
  openError.value = ''
  opening.value = true
  try {
    session.value = await posService.open({ opening_float_cents: toCents(openingFloat.value) })
  } catch (e: any) {
    openError.value = e?.response?.data?.message ?? 'Impossible d’ouvrir la caisse.'
  } finally {
    opening.value = false
  }
}

// ── Product search ───────────────────────────────────────────────────────────
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

/** Enter key: try an exact SKU/barcode match (scanner), else fall back to search. */
async function onScan() {
  const code = query.value.trim()
  if (!code) return
  try {
    const p = await productService.getBySku(code)
    if (p) { await addProduct(p); query.value = ''; results.value = [] }
  } catch {
    runSearch()
  }
}

// ── Cart ─────────────────────────────────────────────────────────────────────
const cart = ref<PosCartItem[]>([])
const method = ref<PosPaymentMethod>('cash')

const cartTotalCents = computed(() => cart.value.reduce((s, l) => s + l.unit_price_cents * l.quantity, 0))

const picker = reactive<{ open: boolean; loading: boolean; productId: string; productName: string; variants: any[] }>({
  open: false, loading: false, productId: '', productName: '', variants: [],
})

async function addProduct(p: any) {
  const productId = p.product_id ?? p.id
  if (p.has_variants) {
    picker.open = true
    picker.loading = true
    picker.productId = productId
    picker.productName = p.name
    picker.variants = []
    try {
      const detail = await productService.getDetail(p.id)
      picker.variants = (detail.variants ?? [])
        .filter((v: any) => v.is_active !== false)
        .map((v: any) => ({
          id: v.id, sku: v.sku, label: v.label ?? v.name ?? v.sku,
          price_cents: v.price?.amount ?? p.price?.amount ?? 0,
        }))
    } catch { picker.variants = [] }
    finally { picker.loading = false }
    return
  }
  addLine(productId, null, p.name, p.sku, p.price?.amount ?? 0)
}

function pickVariant(v: any) {
  // product_id is the PARENT product's id; v.id is the variant id.
  addLine(picker.productId, v.id, `${picker.productName} — ${v.label}`, v.sku, v.price_cents)
  picker.open = false
}

function addLine(productId: string, variantId: string | null, name: string, sku: string, priceCents: number) {
  const existing = cart.value.find(l => l.product_id === productId && (l.variant_id ?? null) === variantId)
  if (existing) { existing.quantity += 1; return }
  cart.value.push({ product_id: productId, variant_id: variantId, name, sku, unit_price_cents: priceCents, quantity: 1 })
}

function incQty(i: number) { cart.value[i].quantity += 1 }
function decQty(i: number) {
  cart.value[i].quantity -= 1
  if (cart.value[i].quantity <= 0) cart.value.splice(i, 1)
}

// ── Checkout ─────────────────────────────────────────────────────────────────
const checkingOut = ref(false)
const checkoutError = ref('')
const toast = ref('')

function flash(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function checkout() {
  if (!session.value || cart.value.length === 0) return
  checkoutError.value = ''
  checkingOut.value = true
  const total = cartTotalCents.value
  try {
    const res = await posService.checkout(session.value.id, {
      items: cart.value.map(l => ({ product_id: l.product_id, variant_id: l.variant_id ?? undefined, quantity: l.quantity })),
      method: method.value,
    })
    session.value = res.session
    cart.value = []
    query.value = ''
    results.value = []
    flash(`Vente enregistrée • ${fmt(total)}`)
  } catch (e: any) {
    checkoutError.value = e?.response?.data?.message ?? 'Échec de l’encaissement.'
  } finally {
    checkingOut.value = false
  }
}

// ── Close session ────────────────────────────────────────────────────────────
const closeModal = reactive<{ open: boolean; counted: number; submitting: boolean }>({
  open: false, counted: 0, submitting: false,
})

const differenceCents = computed(() => toCents(closeModal.counted) - (session.value?.expected_cash_cents ?? 0))
const diffClass = computed(() => ({
  'pos-diff--ok': differenceCents.value === 0,
  'pos-diff--over': differenceCents.value > 0,
  'pos-diff--short': differenceCents.value < 0,
}))

function openCloseModal() {
  if (!session.value) return
  closeModal.counted = fromCents(session.value.expected_cash_cents)
  closeModal.open = true
}

async function confirmClose() {
  if (!session.value) return
  closeModal.submitting = true
  try {
    const closed = await posService.close(session.value.id, { counted_cash_cents: toCents(closeModal.counted) })
    closeModal.open = false
    session.value = null
    cart.value = []
    openingFloat.value = 0
    flash(`Caisse clôturée • écart ${formatMoney(closed.difference_cents ?? 0, currency.value)}`)
  } catch (e: any) {
    flash(e?.response?.data?.message ?? 'Échec de la clôture.')
  } finally {
    closeModal.submitting = false
  }
}

onMounted(loadSession)
</script>

<style scoped>
.pos { height: 100%; display: flex; flex-direction: column; }
.pos-loading, .pos-hint { padding: 1rem; color: var(--gray-500); }

/* Open-session screen */
.pos-open { display: flex; align-items: center; justify-content: center; min-height: 70vh; }
.pos-open-card { width: 100%; max-width: 420px; background: var(--white, #fff); border: 1px solid var(--gray-200, #e5e7eb); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
.pos-open-title { font-size: 1.35rem; font-weight: 700; margin: 0 0 .25rem; }
.pos-open-sub { color: var(--gray-500); margin: 0 0 1.25rem; font-size: .9rem; }

.pos-field { display: block; margin-bottom: 1rem; }
.pos-field > span { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-600); margin-bottom: .35rem; }
.pos-input { width: 100%; padding: .7rem .8rem; border: 1px solid var(--gray-300, #d1d5db); border-radius: 10px; font-size: 1rem; }
.pos-amount-input { position: relative; display: flex; align-items: center; }
.pos-currency { position: absolute; right: .8rem; color: var(--gray-500); font-weight: 600; font-size: .85rem; }

.pos-btn { border: none; border-radius: 10px; padding: .6rem 1rem; font-weight: 600; cursor: pointer; font-size: .9rem; }
.pos-btn--primary { background: var(--brand-primary, #4f46e5); color: #fff; }
.pos-btn--primary:disabled { opacity: .6; cursor: not-allowed; }
.pos-btn--ghost { background: transparent; border: 1px solid var(--gray-300, #d1d5db); color: var(--gray-700); }
.pos-btn--lg { width: 100%; padding: .85rem; font-size: 1rem; }

/* Terminal */
.pos-terminal { display: flex; flex-direction: column; height: 100%; }
.pos-header { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1rem; border-bottom: 1px solid var(--gray-200, #e5e7eb); gap: 1rem; flex-wrap: wrap; }
.pos-badge { background: #dcfce7; color: #166534; font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
.pos-header-meta { color: var(--gray-500); font-size: .85rem; margin-left: .5rem; }
.pos-header-right { display: flex; align-items: center; gap: 1rem; }
.pos-expected { font-size: .85rem; color: var(--gray-600); }

.pos-body { flex: 1; display: grid; grid-template-columns: 1fr 380px; gap: 1rem; padding: 1rem; min-height: 0; }
@media (max-width: 820px) { .pos-body { grid-template-columns: 1fr; } }

.pos-catalog { display: flex; flex-direction: column; min-height: 0; }
.pos-search { margin-bottom: .75rem; }
.pos-results { list-style: none; margin: 0; padding: 0; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .5rem; align-content: start; }
.pos-result { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 10px; padding: .6rem; cursor: pointer; display: flex; flex-direction: column; gap: .35rem; transition: border-color .12s; }
.pos-result:hover { border-color: var(--brand-primary, #4f46e5); }
.pos-result-name { font-weight: 600; font-size: .85rem; }
.pos-result-sku { font-size: .72rem; color: var(--gray-500); }
.pos-result-price { font-weight: 700; font-size: .9rem; }

.pos-cart { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px; display: flex; flex-direction: column; min-height: 0; background: var(--gray-50, #f9fafb); }
.pos-cart-title { font-size: .95rem; font-weight: 700; padding: .75rem 1rem; margin: 0; border-bottom: 1px solid var(--gray-200, #e5e7eb); }
.pos-cart-empty { padding: 1.5rem 1rem; color: var(--gray-500); font-size: .85rem; text-align: center; }
.pos-cart-lines { list-style: none; margin: 0; padding: .5rem; overflow-y: auto; flex: 1; }
.pos-line { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: .5rem; padding: .5rem; border-radius: 8px; }
.pos-line:hover { background: var(--white, #fff); }
.pos-line-name { font-weight: 600; font-size: .85rem; display: block; }
.pos-line-sku { font-size: .72rem; color: var(--gray-500); }
.pos-qty { display: flex; align-items: center; gap: .4rem; }
.pos-qty-btn { width: 26px; height: 26px; border: 1px solid var(--gray-300, #d1d5db); background: #fff; border-radius: 6px; font-size: 1rem; cursor: pointer; line-height: 1; }
.pos-qty-val { min-width: 1.5rem; text-align: center; font-weight: 600; }
.pos-line-total { font-weight: 700; font-size: .88rem; min-width: 70px; text-align: right; }

.pos-cart-foot { border-top: 1px solid var(--gray-200, #e5e7eb); padding: .9rem 1rem; }
.pos-total-row { display: flex; justify-content: space-between; align-items: baseline; font-size: 1.1rem; margin-bottom: .75rem; }
.pos-total-row strong { font-size: 1.4rem; }

.pos-error { color: #dc2626; font-size: .82rem; margin: .5rem 0; }

/* Modals — chrome via shared <BaseModal> (UX-03). */
.pos-variant-list { list-style: none; margin: 0 0 1rem; padding: 0; max-height: 50vh; overflow-y: auto; }
.pos-variant { display: flex; justify-content: space-between; padding: .6rem; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; margin-bottom: .4rem; cursor: pointer; }
.pos-variant:hover { border-color: var(--brand-primary, #4f46e5); }
.pos-recon { background: var(--gray-50, #f9fafb); border-radius: 10px; padding: .75rem; margin-bottom: 1rem; }
.pos-recon-row { display: flex; justify-content: space-between; font-size: .88rem; padding: .2rem 0; }
.pos-recon-row--accent { border-top: 1px solid var(--gray-200, #e5e7eb); margin-top: .25rem; padding-top: .45rem; font-weight: 700; }
.pos-diff { padding: .6rem; border-radius: 8px; font-weight: 600; font-size: .9rem; text-align: center; margin-bottom: 1rem; }
.pos-diff--ok { background: #dcfce7; color: #166534; }
.pos-diff--over { background: #dbeafe; color: #1e40af; }
.pos-diff--short { background: #fee2e2; color: #991b1b; }

/* Toast */
.pos-toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); background: #111827; color: #fff; padding: .75rem 1.25rem; border-radius: 10px; font-weight: 600; z-index: 60; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
.pos-toast-enter-active, .pos-toast-leave-active { transition: opacity .2s, transform .2s; }
.pos-toast-enter-from, .pos-toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(10px); }
</style>
