<template>
  <div>
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h2>Stock</h2>
        <p class="page-subtitle">{{ meta.total ?? '—' }} produits en stock</p>
      </div>
      <div class="header-actions"><RouterLink to="/inventory/batch-delivery" class="btn btn-secondary btn-sm">↓ Réception livraison</RouterLink><RouterLink to="/inventory/alerts" class="btn btn-ghost" style="gap: 6px;">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 2a5 5 0 0 1 5 5v2.5l1 1.5H2l1-1.5V7a5 5 0 0 1 5-5Z" stroke="var(--warning-color, #f59e0b)" stroke-width="1.4"/>
          <path d="M6.5 13.5a1.5 1.5 0 0 0 3 0" stroke="var(--warning-color, #f59e0b)" stroke-width="1.4"/>
        </svg>
        Alertes{{ alertCount > 0 ? ` (${alertCount})` : '' }}
      </RouterLink>
    </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
          <circle cx="7" cy="7" r="4.5" stroke="var(--gray-400)" stroke-width="1.4"/>
          <path d="M10.5 10.5l2.5 2.5" stroke="var(--gray-400)" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input
          v-model="filters.search"
          type="text"
          class="form-input search-input"
          placeholder="Nom, SKU…"
          @input="debouncedLoad"
        />
      </div>

      <!-- Warehouse filter (P0 multi-entrepôts) -->
      <select v-model="filters.warehouse_id" class="form-input filter-sel" @change="onWarehouseChange">
        <option value="">Tous les entrepôts</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">
          {{ w.is_default ? '⭐ ' : '' }}{{ w.name }}
        </option>
      </select>

      <select v-model="filters.category_id" class="form-input filter-sel" @change="load">
        <option value="">Toutes les catégories</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">
          {{ c.parent_id ? '└ ' : '' }}{{ c.name }}
        </option>
      </select>

      <label class="toggle-label">
        <input v-model="filters.lowStockOnly" type="checkbox" class="toggle-checkbox" @change="load" />
        <span class="toggle-text">Stock bas uniquement</span>
      </label>
    </div>

    <!-- Warehouse KPI summary bar (visible when a specific warehouse is selected) -->
    <Transition name="slide-up">
      <div v-if="whSummary" class="wh-summary-bar">
        <div class="wh-kpi"><div class="wh-kpi-val">{{ whSummary.sku_count }}</div><div class="wh-kpi-lbl">SKUs</div></div>
        <div class="wh-kpi"><div class="wh-kpi-val">{{ whSummary.total_qty.toLocaleString('fr-FR') }}</div><div class="wh-kpi-lbl">Total</div></div>
        <div class="wh-kpi"><div class="wh-kpi-val">{{ whSummary.available_qty.toLocaleString('fr-FR') }}</div><div class="wh-kpi-lbl">Disponible</div></div>
        <div class="wh-kpi" :class="{ 'wh-kpi--warn': whSummary.low_stock_count > 0 }">
          <div class="wh-kpi-val">{{ whSummary.low_stock_count }}</div><div class="wh-kpi-lbl">Stock bas</div>
        </div>
        <div class="wh-kpi"><div class="wh-kpi-val">{{ fmtValue(whSummary.total_value_cents) }}</div><div class="wh-kpi-lbl">Valeur</div></div>
      </div>
    </Transition>

    <!-- Loading -->
    <StateBlock v-if="loading" variant="loading" />

    <!-- Empty -->
    <StateBlock
      v-else-if="stocks.length === 0"
      variant="empty"
      title="Aucun stock trouvé"
      :message="filters.search ? 'Aucun résultat pour cette recherche.' : 'Les articles apparaîtront ici une fois synchronisés.'"
    />

    <!-- Table -->
    <div v-else class="card table-scroll" style="padding: 0;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Produit</th>
            <th class="hide-mobile" style="text-align: right;">Qté</th>
            <th class="hide-mobile" style="text-align: right;">Réservé</th>
            <th style="text-align: right;">Disponible</th>
            <th class="hide-mobile">Statut</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="stock in stocks" :key="stock.id" :class="{ 'row-warning': stock.is_low_stock }">
            <td>
              <div class="product-cell">
                <div class="product-thumb" :style="stock.is_low_stock ? 'background: var(--warning-bg, #fef3c7); color: #92400e;' : ''">
                  {{ stock.product?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                </div>
                <div>
                  <div class="product-name">{{ stock.product?.name ?? '—' }}</div>
                  <div class="product-sku">{{ stock.product?.sku ?? '—' }}</div>
                </div>
              </div>
            </td>
            <td class="hide-mobile" style="text-align: right; font-variant-numeric: tabular-nums;">
              {{ stock.quantity }}
            </td>
            <td class="hide-mobile" style="text-align: right; color: var(--gray-500); font-variant-numeric: tabular-nums;">
              {{ stock.reserved_quantity }}
            </td>
            <td style="text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;"
                :style="stock.is_low_stock ? 'color: #b45309;' : 'color: var(--gray-900)'">
              {{ stock.available }}
            </td>
            <td class="hide-mobile">
              <span v-if="stock.is_low_stock" class="badge badge-warning">Stock bas</span>
              <span v-else class="badge badge-success">OK</span>
            </td>
            <td>
              <div class="row-actions" style="justify-content: flex-end;">
                <button class="btn btn-ghost btn-sm" title="Entrée stock" @click="openModal(stock, 'in')">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                  <span class="hide-mobile">Entrée</span>
                </button>
                <button class="btn btn-ghost btn-sm" title="Sortie stock" @click="openModal(stock, 'out')">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                  <span class="hide-mobile">Sortie</span>
                </button>
                <button class="btn btn-ghost btn-sm" title="Ajuster" @click="openModal(stock, 'adjust')">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <circle cx="7" cy="7" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M7 1v2M7 11v2M1 7h2M11 7h2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                  </svg>
                  <span class="hide-mobile">Ajuster</span>
                </button>
                <RouterLink :to="`/inventory/movements/${stock.product_id}`" class="btn btn-ghost btn-sm" title="Historique">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M7 4v3.5l2 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                  </svg>
                  <span class="hide-mobile">Historique</span>
                </RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">← Précédent</button>
      <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Suivant →</button>
    </div>

    <!-- Move / Adjust Modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="modal.open"
      :title="modalTitle"
      @update:model-value="(v: boolean) => { if (!v) closeModal() }"
    >
      <div style="display: flex; flex-direction: column; gap: 16px;">
            <p class="modal-subtitle" style="margin:0;">{{ modal.stock?.product?.name }} · {{ modal.stock?.product?.sku }}</p>
            <!-- Current stock info -->
            <div class="stock-info-row">
              <div class="stock-info-item">
                <span class="stock-info-label">Quantité actuelle</span>
                <span class="stock-info-value">{{ modal.stock?.quantity }}</span>
              </div>
              <div class="stock-info-item">
                <span class="stock-info-label">Disponible</span>
                <span class="stock-info-value" :style="modal.stock?.is_low_stock ? 'color:#b45309;' : ''">{{ modal.stock?.available }}</span>
              </div>
              <div class="stock-info-item">
                <span class="stock-info-label">Seuil bas</span>
                <span class="stock-info-value">{{ modal.stock?.low_stock_threshold }}</span>
              </div>
            </div>

            <!-- Quantity -->
            <div class="form-group">
              <label class="form-label">
                {{ modal.mode === 'adjust' ? 'Nouvelle quantité absolue' : 'Quantité' }}
                <span class="required-star">*</span>
              </label>
              <input
                v-model.number="form.quantity"
                type="number"
                min="0"
                class="form-input"
                :placeholder="modal.mode === 'adjust' ? 'Ex : 42' : 'Ex : 10'"
              />
              <p v-if="modal.mode === 'in'" class="form-hint">
                Après : {{ (modal.stock?.quantity ?? 0) + (form.quantity || 0) }} unités
              </p>
              <p v-else-if="modal.mode === 'out'" class="form-hint">
                Après : {{ Math.max(0, (modal.stock?.quantity ?? 0) - (form.quantity || 0)) }} unités
              </p>
              <p v-else class="form-hint">
                Ajustement : {{ form.quantity !== undefined ? (form.quantity - (modal.stock?.quantity ?? 0) >= 0 ? '+' : '') + (form.quantity - (modal.stock?.quantity ?? 0)) : '—' }} unités
              </p>
            </div>

            <!-- Reason (move-in/out only) -->
            <div v-if="modal.mode !== 'adjust'" class="form-group">
              <label class="form-label">Raison <span class="required-star">*</span></label>
              <select v-model="form.reason" class="form-input">
                <option v-if="modal.mode === 'in'" value="delivery">Livraison</option>
                <option v-if="modal.mode === 'in'" value="return">Retour client</option>
                <option v-if="modal.mode === 'in'" value="manual">Manuel</option>
                <option v-if="modal.mode === 'out'" value="sale">Vente</option>
                <option v-if="modal.mode === 'out'" value="loss">Perte / casse</option>
                <option v-if="modal.mode === 'out'" value="manual">Manuel</option>
              </select>
            </div>

            <!-- Reference -->
            <div class="form-group">
              <label class="form-label">Référence</label>
              <input v-model="form.reference" type="text" class="form-input" placeholder="N° bon de livraison, commande…" />
            </div>

            <!-- Note -->
            <div class="form-group">
              <label class="form-label">Note</label>
              <textarea v-model="form.note" class="form-input form-textarea" rows="2" placeholder="Commentaire optionnel…"></textarea>
            </div>

            <p v-if="modal.error" class="form-error">{{ modal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="closeModal">Annuler</button>
        <button
          class="btn btn-primary"
          :disabled="modal.saving || !formValid"
          @click="submitModal"
        >
          <span v-if="modal.saving" class="spinner-sm"></span>
          {{ modal.mode === 'in' ? 'Enregistrer l\'entrée' : modal.mode === 'out' ? 'Enregistrer la sortie' : 'Appliquer l\'ajustement' }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import { inventoryService } from '../services/inventoryService'
import { productService } from '@/modules/catalog/services/productService'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import client from '@/api/client'
import type { Stock, MovementReason } from '../types'
import type { Category } from '@/modules/catalog/types'

interface Warehouse { id: string; name: string; code: string; type: string; is_default: boolean }
interface WhSummary  { sku_count: number; total_qty: number; available_qty: number; low_stock_count: number; total_value_cents: number }

const stocks     = ref<Stock[]>([])
const categories = ref<Category[]>([])
const warehouses = ref<Warehouse[]>([])
const whSummary  = ref<WhSummary | null>(null)
const loading    = ref(false)
const alertCount = ref(0)
const meta       = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })

const filters = reactive({ search: '', lowStockOnly: false, category_id: '', warehouse_id: '', page: 1 })

async function loadWarehouses() {
  try { warehouses.value = (await client.get('/api/inventory/warehouses')).data.data ?? [] }
  catch { warehouses.value = [] }
}

async function loadWhSummary(id: string) {
  if (!id) { whSummary.value = null; return }
  try { whSummary.value = (await client.get(`/api/inventory/warehouses/${id}/summary`)).data.data ?? null }
  catch { whSummary.value = null }
}

function onWarehouseChange() {
  load()
  loadWhSummary(filters.warehouse_id)
}

const fmtValue = (cents: number) => formatMoney(cents)

// ── Modal state ────────────────────────────────────────────────────────────────
const modal = reactive({
  open:  false,
  mode:  'in' as 'in' | 'out' | 'adjust',
  stock: null as Stock | null,
  saving: false,
  error:  '',
})
const form = reactive({ quantity: undefined as number | undefined, reason: 'delivery' as MovementReason, reference: '', note: '' })

const modalTitle = computed(() => ({
  in:     'Entrée de stock',
  out:    'Sortie de stock',
  adjust: 'Ajustement de stock',
})[modal.mode])

const formValid = computed(() => {
  if (form.quantity === undefined || form.quantity < 0) return false
  if (modal.mode !== 'adjust' && !form.reason) return false
  return true
})

// ── Debounce ───────────────────────────────────────────────────────────────────
let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { filters.page = 1; load() }, 300)
}

// ── Data ───────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number | boolean> = { page: filters.page, per_page: meta.per_page }
    if (filters.search)       params.search      = filters.search
    if (filters.lowStockOnly) params.low_stock    = true
    if (filters.category_id)  params.category_id  = filters.category_id
    if (filters.warehouse_id) params.warehouse_id  = filters.warehouse_id
    const res = await inventoryService.list(params as any)
    stocks.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    stocks.value = []
  } finally {
    loading.value = false
  }
}

async function loadAlertCount() {
  try {
    const alerts = await inventoryService.alerts()
    alertCount.value = alerts.length
  } catch {
    alertCount.value = 0
  }
}

async function loadCategories() {
  try {
    categories.value = await productService.categories.list()
  } catch {
    categories.value = []
  }
}

function goToPage(page: number) { filters.page = page; load() }

// ── Modal ──────────────────────────────────────────────────────────────────────
function openModal(stock: Stock, mode: 'in' | 'out' | 'adjust') {
  modal.stock  = stock
  modal.mode   = mode
  modal.error  = ''
  modal.saving = false
  form.quantity  = mode === 'adjust' ? stock.quantity : undefined
  form.reason    = mode === 'in' ? 'delivery' : 'sale'
  form.reference = ''
  form.note      = ''
  modal.open = true
}

function closeModal() { modal.open = false }

async function submitModal() {
  if (!modal.stock || !formValid.value) return
  modal.saving = true
  modal.error  = ''
  try {
    const productId = modal.stock.product_id
    if (modal.mode === 'in') {
      await inventoryService.moveIn(productId, {
        quantity: form.quantity!,
        reason:   form.reason,
        reference: form.reference || undefined,
        note:      form.note || undefined,
      })
    } else if (modal.mode === 'out') {
      await inventoryService.moveOut(productId, {
        quantity: form.quantity!,
        reason:   form.reason,
        reference: form.reference || undefined,
        note:      form.note || undefined,
      })
    } else {
      await inventoryService.adjust(productId, {
        quantity: form.quantity!,
        note:     form.note || undefined,
      })
    }
    closeModal()
    load()
    loadAlertCount()
  } catch (e: any) {
    modal.error = e?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    modal.saving = false
  }
}

onMounted(() => { load(); loadAlertCount(); loadCategories(); loadWarehouses() })
</script>

<style scoped>
/* ── Filter bar — force inline row ──────────────────────────────────────── */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

/* Search wrapper — relative so the icon can be positioned inside the input */
.search-wrap {
  position: relative;  /* ← required for absolute icon */
  flex: 1;
  min-width: 160px;
  max-width: 300px;
}

/* Magnifier icon — centred vertically inside the input */
.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  z-index: 1;
}

/* Extra left padding so text doesn't overlap the icon */
.search-input {
  padding-left: 2.25rem !important;
}

.filter-sel {
  width: 190px;
  flex-shrink: 0;
}

@media (max-width: 640px) {
  .filter-sel { width: 100%; }
  .search-wrap { max-width: 100%; }
}

/* ── Product cell ────────────────────────────────────────────────────────── */
.product-cell  { display: flex; align-items: center; gap: 0.75rem; }
.product-thumb {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-md, 8px);
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.8125rem;
  flex-shrink: 0;
}
.product-name { font-weight: 500; color: var(--gray-900); font-size: 0.875rem; }
.product-sku  { font-size: 0.75rem; color: var(--gray-400); font-family: ui-monospace, monospace; margin-top: 1px; }

.page-subtitle { color: var(--gray-500); font-size: 0.875rem; margin-top: 0.2rem; }

/* ── Warehouse KPI summary bar ───────────────────────────────────────────── */
.wh-summary-bar {
  display: flex;
  gap: 0;
  background: white;
  border: 1px solid var(--brand-primary-light);
  border-radius: var(--radius-lg);
  margin-bottom: 1rem;
  overflow: hidden;
}
.wh-kpi {
  flex: 1;
  padding: 0.875rem 1rem;
  text-align: center;
  border-right: 1px solid var(--gray-100);
  transition: background 0.12s;
}
.wh-kpi:last-child { border-right: none; }
.wh-kpi:hover { background: var(--gray-50); }
.wh-kpi--warn .wh-kpi-val { color: #b45309 !important; }
.wh-kpi--warn { background: #fffbeb; }

.wh-kpi-val {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--gray-900);
  line-height: 1.2;
  margin-bottom: 2px;
}
.wh-kpi-lbl {
  font-size: 0.6875rem;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 600;
}

.row-warning td { background: #fffbeb; }

.toggle-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}
.toggle-checkbox { width: 16px; height: 16px; accent-color: var(--brand-primary); cursor: pointer; }
.toggle-text { font-size: 0.875rem; color: var(--gray-700); }

.stock-info-row {
  display: flex;
  gap: 16px;
  padding: 12px 16px;
  background: var(--gray-50);
  border-radius: 8px;
  border: 1px solid var(--gray-200);
}
.stock-info-item { display: flex; flex-direction: column; gap: 2px; }
.stock-info-label { font-size: 0.75rem; color: var(--gray-500); }
.stock-info-value { font-size: 1rem; font-weight: 600; color: var(--gray-900); }

.form-hint { font-size: 0.8rem; color: var(--gray-500); margin-top: 4px; }
.form-error { color: #dc2626; font-size: 0.875rem; }
.required-star { color: #dc2626; margin-left: 2px; }
.form-textarea { resize: vertical; min-height: 60px; }

.modal-subtitle { font-size: 0.875rem; color: var(--gray-500); margin-top: 2px; }
</style>
