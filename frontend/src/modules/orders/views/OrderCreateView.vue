<template>
  <div class="order-create">
    <div class="page-header">
      <h2>Nouvelle commande</h2>
      <RouterLink to="/orders" class="btn btn-secondary">← Retour</RouterLink>
    </div>

    <form @submit.prevent="submit">
      <!-- ── Customer (optional) ──────────────────────────────────── -->
      <div class="card" style="margin-bottom:1rem">
        <label class="form-label">Client <span class="hint">(optionnel)</span></label>
        <div class="picker">
          <input
            v-model="customerSearch"
            class="form-input"
            :placeholder="selectedCustomer ? selectedCustomer.name : 'Rechercher un client…'"
            @input="onCustomerSearch"
            @focus="customerOpen = true"
          />
          <button v-if="selectedCustomer" type="button" class="picker-clear" @click="clearCustomer" title="Retirer">✕</button>
          <div v-if="customerOpen && customerResults.length" class="picker-dropdown">
            <button
              v-for="c in customerResults" :key="c.id" type="button"
              class="picker-option" @click="selectCustomer(c)"
            >
              <span class="po-name">{{ c.name }}</span>
              <span v-if="c.phone" class="po-meta">{{ c.phone }}</span>
            </button>
          </div>
        </div>
        <div v-if="selectedCustomer" class="selected-chip">
          <strong>{{ selectedCustomer.name }}</strong>
          <span v-if="selectedCustomer.phone" class="po-meta">· {{ selectedCustomer.phone }}</span>
        </div>
      </div>

      <!-- ── Line items ───────────────────────────────────────────── -->
      <div class="card" style="margin-bottom:1rem">
        <h3 class="card-h3">Articles</h3>

        <div class="lines-head">
          <span style="flex:1">Produit</span>
          <span style="width:130px;text-align:right">Prix unitaire</span>
          <span style="width:90px;text-align:center">Qté</span>
          <span style="width:120px;text-align:right">Total ligne</span>
          <span style="width:32px"></span>
        </div>

        <div v-for="(item, i) in items" :key="item._key" class="line-row">
          <!-- Product picker -->
          <div class="picker" style="flex:1">
            <input
              v-model="item._search"
              class="form-input"
              :class="{ error: item._error }"
              :placeholder="item.label || 'Rechercher un produit (nom, SKU)…'"
              @input="onProductSearch(i)"
              @focus="openProductDropdown(i)"
            />
            <div v-if="item._open && productResults.length" class="picker-dropdown">
              <button
                v-for="p in productResults" :key="p.id" type="button"
                class="picker-option" @click="selectProduct(i, p)"
              >
                <span class="po-name">{{ p.name }}<span v-if="p.has_variants" class="var-tag">variantes</span></span>
                <span class="po-meta">{{ p.sku }} · {{ p.price.formatted }}</span>
              </button>
              <div v-if="productSearching" class="picker-loading">Recherche…</div>
            </div>

            <!-- Variant selector — required for variable products -->
            <div v-if="item.product_id && (item._needsVariant || item._variants.length)" class="variant-pick">
              <span v-if="item._loadingVariants" class="dim" style="font-size:0.78rem">Chargement des variantes…</span>
              <select
                v-else
                :value="item.variant_id ?? ''"
                class="form-input variant-select"
                :class="{ error: item._needsVariant && item._error }"
                @change="selectVariant(i, item._variants.find(v => v.id === ($event.target as HTMLSelectElement).value)!)"
              >
                <option value="" disabled>Choisir une déclinaison…</option>
                <option v-for="v in item._variants" :key="v.id" :value="v.id">
                  {{ v.label }} · {{ v.sku }} · {{ fmtCents(v.price_cents) }}
                </option>
              </select>
            </div>
          </div>

          <!-- Unit price (read-only, from catalog) -->
          <div style="width:130px;text-align:right" class="unit-price">
            <span v-if="item.product_id">{{ fmtCents(item.unit_price_cents) }}</span>
            <span v-else class="dim">—</span>
          </div>

          <!-- Quantity -->
          <div style="width:90px">
            <input
              v-model.number="item.quantity"
              type="number" min="1"
              class="form-input" style="text-align:center"
            />
          </div>

          <!-- Line total -->
          <div style="width:120px;text-align:right;font-weight:600" class="line-total">
            <span v-if="item.product_id">{{ fmtCents(item.unit_price_cents * (item.quantity || 0)) }}</span>
            <span v-else class="dim">—</span>
          </div>

          <button
            type="button" class="line-remove"
            :disabled="items.length === 1"
            @click="removeItem(i)"
            title="Supprimer la ligne"
          >✕</button>
        </div>

        <button type="button" class="btn btn-secondary" style="margin-top:0.75rem" @click="addItem">
          + Ajouter un article
        </button>
      </div>

      <!-- ── Note ─────────────────────────────────────────────────── -->
      <div class="card" style="margin-bottom:1rem">
        <label class="form-label">Note <span class="hint">(optionnel)</span></label>
        <textarea v-model="note" class="form-input" rows="2" placeholder="Commentaire sur la commande…"></textarea>
      </div>

      <!-- ── Total + actions ──────────────────────────────────────── -->
      <div class="card order-footer">
        <div class="order-total">
          <span class="ot-label">Total commande</span>
          <span class="ot-value">{{ fmtCents(orderTotalCents) }}</span>
        </div>
        <div v-if="error" class="form-error">{{ error }}</div>
        <div class="footer-actions">
          <RouterLink to="/orders" class="btn btn-secondary">Annuler</RouterLink>
          <button type="submit" class="btn btn-primary" :disabled="loading || !canSubmit">
            <span v-if="loading" class="spinner-sm spinner-white"></span>
            {{ loading ? 'Création…' : 'Créer la commande' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import { orderService } from '../services/orderService'
import { productService } from '@/modules/catalog/services/productService'
import { customerService } from '@/modules/customers/services/customerService'
import type { Product } from '@/modules/catalog/types'

const router = useRouter()

interface VariantOption { id: string; label: string; sku: string; price_cents: number }

interface LineItem {
  _key: number
  _search: string
  _open: boolean
  _error: boolean
  _variants: VariantOption[]   // populated when product is variable
  _needsVariant: boolean       // true while a variant must still be chosen
  _loadingVariants: boolean
  label: string                // selected product/variant display
  product_id: string
  variant_id: string | null
  quantity: number
  unit_price_cents: number     // resolved from catalog (display only)
}

let _k = 0
function blankItem(): LineItem {
  return {
    _key: ++_k, _search: '', _open: false, _error: false,
    _variants: [], _needsVariant: false, _loadingVariants: false,
    label: '', product_id: '', variant_id: null, quantity: 1, unit_price_cents: 0,
  }
}

const items   = ref<LineItem[]>([blankItem()])
const note    = ref('')
const loading = ref(false)
const error   = ref<string | null>(null)

// ── Product search ──────────────────────────────────────────────────────────
const productResults   = ref<Product[]>([])
const productSearching  = ref(false)
let   activeLine        = -1
let   productTimer: ReturnType<typeof setTimeout> | null = null

function openProductDropdown(i: number) {
  activeLine = i
  items.value.forEach((it, idx) => it._open = idx === i)
  if (productResults.value.length === 0) loadProducts('')
}

function onProductSearch(i: number) {
  activeLine = i
  items.value[i]._open = true
  items.value[i].product_id = '' // typing invalidates selection
  if (productTimer) clearTimeout(productTimer)
  productTimer = setTimeout(() => loadProducts(items.value[i]._search), 250)
}

async function loadProducts(search: string) {
  productSearching.value = true
  try {
    const res = await productService.list({ search: search || undefined, status: 'active', per_page: 15 })
    productResults.value = res.data ?? []
  } catch {
    productResults.value = []
  } finally {
    productSearching.value = false
  }
}

async function selectProduct(i: number, p: Product) {
  const it = items.value[i]
  it.product_id   = p.id
  it.label        = p.name
  it._search      = ''
  it._open        = false
  it._error       = false
  it.variant_id   = null
  it._variants    = []
  it._needsVariant = false

  if (p.has_variants) {
    // Variable product: the user MUST pick a variant (stock & price live on variants)
    it._needsVariant     = true
    it._loadingVariants  = true
    it.unit_price_cents  = p.price.amount  // provisional, overwritten on variant pick
    try {
      const detail = await productService.getDetail(p.id)
      it._variants = (detail.variants ?? [])
        .filter((v: any) => v.is_active !== false)
        .map((v: any) => ({
          id:         v.id,
          label:      v.label ?? v.name ?? v.sku,
          sku:        v.sku,
          price_cents: v.price?.amount ?? p.price.amount,
        }))
    } catch {
      it._variants = []
    } finally {
      it._loadingVariants = false
    }
  } else {
    it.unit_price_cents = p.price.amount  // simple product
  }
}

function selectVariant(i: number, v: VariantOption) {
  const it = items.value[i]
  it.variant_id       = v.id
  it.unit_price_cents = v.price_cents
  it._needsVariant    = false
  it._error           = false
  it.label            = `${it.label.split(' — ')[0]} — ${v.label}`
}

// ── Customer search ─────────────────────────────────────────────────────────
const customerSearch  = ref('')
const customerResults = ref<any[]>([])
const customerOpen    = ref(false)
const selectedCustomer = ref<any | null>(null)
let   custTimer: ReturnType<typeof setTimeout> | null = null

function onCustomerSearch() {
  customerOpen.value = true
  if (custTimer) clearTimeout(custTimer)
  custTimer = setTimeout(async () => {
    const q = customerSearch.value.trim()
    if (q.length < 2) { customerResults.value = []; return }
    try {
      customerResults.value = await customerService.search(q)
    } catch {
      customerResults.value = []
    }
  }, 250)
}

function selectCustomer(c: any) {
  selectedCustomer.value = c
  customerSearch.value   = ''
  customerResults.value  = []
  customerOpen.value     = false
}

function clearCustomer() {
  selectedCustomer.value = null
  customerSearch.value   = ''
}

// ── Lines ───────────────────────────────────────────────────────────────────
function addItem() { items.value.push(blankItem()) }
function removeItem(i: number) { if (items.value.length > 1) items.value.splice(i, 1) }

// ── Totals ──────────────────────────────────────────────────────────────────
const orderTotalCents = computed(() =>
  items.value.reduce((sum, it) => sum + (it.product_id ? it.unit_price_cents * (it.quantity || 0) : 0), 0)
)

// A line is complete when it has a product, a valid qty, and (if variable) a variant.
function lineComplete(it: LineItem): boolean {
  return !!it.product_id && it.quantity > 0 && !it._needsVariant
}

const canSubmit = computed(() => items.value.some(lineComplete))

// ── Submit ──────────────────────────────────────────────────────────────────
async function submit() {
  error.value = null
  // Validate: every line must have a product, and variable products need a variant
  let valid = true
  for (const it of items.value) {
    if (!it.product_id) { it._error = true; valid = false }
    else if (it._needsVariant) { it._error = true; valid = false }
  }
  if (!valid) {
    error.value = items.value.some(it => it.product_id && it._needsVariant)
      ? 'Choisissez une déclinaison pour les produits à variantes.'
      : 'Sélectionnez un produit pour chaque ligne.'
    return
  }

  loading.value = true
  try {
    const order = await orderService.create({
      customer_id: selectedCustomer.value?.id ?? undefined,
      note:        note.value || undefined,
      items: items.value
        .filter(it => it.product_id)
        .map(it => ({
          product_id: it.product_id,
          variant_id: it.variant_id ?? undefined,
          quantity:   it.quantity,
        })),
    })
    router.push(`/orders/${order.id}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Erreur lors de la création de la commande.'
  } finally {
    loading.value = false
  }
}

const fmtCents = (cents: number) => formatMoney(cents)
</script>

<style scoped>
.order-create { max-width: 820px; }
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
.card { background:white; border:1px solid var(--gray-200); border-radius:var(--radius-lg); padding:1.25rem; }
.card-h3 { font-size:0.95rem; font-weight:600; margin:0 0 1rem; }
.form-label { font-size:0.8125rem; font-weight:600; color:var(--gray-700); display:block; margin-bottom:0.375rem; }
.hint { color:var(--gray-400); font-weight:400; }
.form-input { width:100%; padding:0.5rem 0.7rem; border:1px solid var(--gray-300); border-radius:var(--radius-sm); font-size:0.875rem; box-sizing:border-box; }
.form-input:focus { outline:none; border-color:var(--brand-primary); box-shadow:0 0 0 3px var(--brand-primary-bg); }
.form-input.error { border-color:#ef4444; }
textarea.form-input { resize:vertical; }
.dim { color:var(--gray-300); }

/* Picker (product + customer autocomplete) */
.picker { position:relative; }
.picker-clear { position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--gray-400); cursor:pointer; }
.picker-clear:hover { color:#ef4444; }
.picker-dropdown {
  position:absolute; top:calc(100% + 2px); left:0; right:0; z-index:30;
  background:white; border:1px solid var(--gray-200); border-radius:var(--radius-md);
  box-shadow:var(--shadow-lg); max-height:240px; overflow-y:auto;
}
.picker-option {
  display:flex; flex-direction:column; gap:1px; width:100%; text-align:left;
  padding:0.5rem 0.75rem; background:none; border:none; cursor:pointer; border-bottom:1px solid var(--gray-50);
}
.picker-option:hover { background:var(--brand-primary-bg); }
.po-name { font-size:0.875rem; font-weight:500; color:var(--gray-900); }
.po-meta { font-size:0.75rem; color:var(--gray-400); }
.picker-loading { padding:0.5rem 0.75rem; font-size:0.8rem; color:var(--gray-400); }
.selected-chip { margin-top:0.5rem; font-size:0.875rem; color:var(--gray-700); }

/* Line items */
.lines-head { display:flex; gap:0.5rem; padding:0 0 0.5rem; font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:var(--gray-400); border-bottom:1px solid var(--gray-100); }
.line-row { display:flex; gap:0.5rem; align-items:flex-start; padding:0.625rem 0; border-bottom:1px solid var(--gray-50); }
.unit-price { font-size:0.875rem; color:var(--gray-600); padding-top:0.5rem; }
.line-total { font-size:0.875rem; color:var(--gray-900); padding-top:0.5rem; }

/* Variant selector (variable products) */
.variant-pick { margin-top:0.4rem; }
.variant-select { font-size:0.8125rem; padding:0.4rem 0.5rem; }
.variant-select.error { border-color:#ef4444; }
.var-tag { margin-left:0.4rem; font-size:0.62rem; font-weight:600; text-transform:uppercase; letter-spacing:0.03em; background:#f3e8ff; color:#7e22ce; padding:1px 5px; border-radius:6px; }
.line-remove { width:32px; height:32px; flex-shrink:0; background:none; border:1px solid var(--gray-200); border-radius:var(--radius-sm); color:var(--gray-400); cursor:pointer; }
.line-remove:hover:not(:disabled) { color:#ef4444; border-color:#fca5a5; }
.line-remove:disabled { opacity:0.3; cursor:not-allowed; }

/* Footer */
.order-footer { display:flex; flex-direction:column; gap:1rem; }
.order-total { display:flex; justify-content:space-between; align-items:baseline; padding-bottom:0.75rem; border-bottom:1px solid var(--gray-100); }
.ot-label { font-size:0.875rem; color:var(--gray-500); }
.ot-value { font-size:1.5rem; font-weight:700; color:var(--brand-primary); }
.footer-actions { display:flex; justify-content:flex-end; gap:0.75rem; }
.form-error { color:#ef4444; font-size:0.875rem; }
.spinner-sm { width:16px; height:16px; border:2px solid rgba(255,255,255,0.3); border-top-color:currentColor; border-radius:50%; animation:spin 0.6s linear infinite; display:inline-block; }
.spinner-white { border-color:rgba(255,255,255,0.3); border-top-color:white; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
