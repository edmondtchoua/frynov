<template>
  <div>
    <div class="page-header">
      <div>
        <h2>Impression d'étiquettes</h2>
        <p class="page-subtitle">Générez et imprimez des étiquettes en masse pour vos produits.</p>
      </div>
      <RouterLink to="/catalog" class="btn btn-ghost">← Catalogue</RouterLink>
    </div>

    <div class="labels-layout">

      <!-- ── Left: product selection ──────────────────────────── -->
      <div class="labels-left">
        <div class="card">
          <div class="section-header-row">
            <h3>Sélectionner les produits</h3>
            <input v-model="search" type="text" class="form-input search-sm"
                   placeholder="Rechercher…" @input="debouncedSearch" />
          </div>

          <div v-if="loading" class="state-center">Chargement…</div>

          <div v-else class="product-pick-list">
            <label
              v-for="p in products"
              :key="p.id"
              class="pick-row"
              :class="{ 'pick-row--selected': isInBatch(p.id) }"
            >
              <input type="checkbox"
                     :checked="isInBatch(p.id)"
                     @change="toggleProduct(p)"
                     class="pick-cb" />
              <div class="pick-info">
                <div class="pick-name">{{ p.name }}</div>
                <div class="pick-sku">{{ p.sku }}</div>
              </div>
              <div v-if="isInBatch(p.id)" class="pick-copies">
                <button type="button" class="copies-btn" @click.stop="addCopies(p.id, -1)">−</button>
                <span class="copies-val">{{ getCopies(p.id) }}</span>
                <button type="button" class="copies-btn" @click.stop="addCopies(p.id, 1)">+</button>
              </div>
            </label>
          </div>

          <div v-if="products.length === 0 && !loading" class="state-center text-muted">
            Aucun produit trouvé.
          </div>
        </div>
      </div>

      <!-- ── Right: config + preview ──────────────────────────── -->
      <div class="labels-right">

        <!-- Config -->
        <div class="card">
          <h3>Configuration</h3>

          <div class="form-group">
            <label class="form-label">Format</label>
            <div class="format-choice">
              <label class="format-opt" :class="{ active: config.format === 'thermal' }">
                <input v-model="config.format" type="radio" value="thermal" class="sr-only" />
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                  <rect x="4" y="8" width="20" height="12" rx="3" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M8 14h12M8 17h8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                <span>Thermique</span>
                <small>50×30 mm</small>
              </label>
              <label class="format-opt" :class="{ active: config.format === 'a4sheet' }">
                <input v-model="config.format" type="radio" value="a4sheet" class="sr-only" />
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                  <rect x="6" y="3" width="16" height="22" rx="2" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M10 9h8M10 13h6M10 17h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                <span>Planche A4</span>
                <small>21×29.7 cm</small>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Options</label>
            <div class="options-list">
              <label class="opt-row">
                <input v-model="config.show_price" type="checkbox" class="pick-cb" />
                <span>Afficher le prix</span>
              </label>
              <label class="opt-row">
                <input v-model="config.show_qr" type="checkbox" class="pick-cb" />
                <span>Afficher le QR Code</span>
              </label>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Copies par défaut</label>
            <input v-model.number="config.default_copies" type="number" min="1" max="500"
                   class="form-input" style="width:100px" />
          </div>
        </div>

        <!-- Summary & print -->
        <div class="card">
          <h3>Résumé</h3>
          <div v-if="batch.length === 0" class="state-center text-muted" style="padding:2rem 0">
            Aucun produit sélectionné.
          </div>
          <div v-else>
            <div class="summary-list">
              <div v-for="item in batch" :key="item.product_id" class="summary-row">
                <span class="summary-name">{{ item.product_name }}</span>
                <span class="summary-copies">{{ item.copies }} étiq.</span>
                <button type="button" class="summary-remove" @click="removeFromBatch(item.product_id)">✕</button>
              </div>
            </div>
            <div class="summary-total">
              Total : <strong>{{ totalLabels }} étiquette{{ totalLabels > 1 ? 's' : '' }}</strong>
            </div>
            <button
              class="btn btn-primary"
              style="width:100%; margin-top:0.75rem; justify-content:center"
              :disabled="printing || batch.length === 0"
              @click="doPrint"
            >
              <span v-if="printing" class="spinner-sm spinner-white"></span>
              <template v-else>🖨</template>
              {{ printing ? 'Génération…' : 'Imprimer les étiquettes' }}
            </button>
          </div>
        </div>

      </div><!-- /labels-right -->
    </div><!-- /labels-layout -->
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { productService } from '../services/productService'
import type { LabelBatchItem, Product } from '../types'

const products = ref<Product[]>([])
const loading  = ref(false)
const printing = ref(false)
const search   = ref('')

const config = reactive({
  format:         'thermal' as 'thermal' | 'a4sheet',
  show_price:     true,
  show_qr:        true,
  default_copies: 1,
})

interface BatchEntry extends LabelBatchItem {
  product_name: string
}
const batch = ref<BatchEntry[]>([])

const totalLabels = computed(() => batch.value.reduce((s, i) => s + i.copies, 0))

function isInBatch(id: string)    { return batch.value.some(b => b.product_id === id) }
function getCopies(id: string)    { return batch.value.find(b => b.product_id === id)?.copies ?? 1 }

function toggleProduct(p: Product) {
  if (isInBatch(p.id)) {
    batch.value = batch.value.filter(b => b.product_id !== p.id)
  } else {
    batch.value.push({
      product_id:   p.id,
      product_name: p.name,
      sku:          p.sku,
      copies:       config.default_copies,
    })
  }
}

function addCopies(id: string, delta: number) {
  const item = batch.value.find(b => b.product_id === id)
  if (item) item.copies = Math.max(1, Math.min(500, item.copies + delta))
}

function removeFromBatch(id: string) {
  batch.value = batch.value.filter(b => b.product_id !== id)
}

async function doPrint() {
  if (!batch.value.length) return
  printing.value = true
  try {
    await productService.printBatch({
      items:      batch.value.map(b => ({ product_id: b.product_id, copies: b.copies })),
      format:     config.format,
      show_price: config.show_price,
      show_qr:    config.show_qr,
    })
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'Erreur lors de la génération.')
  } finally {
    printing.value = false
  }
}

let dTimer: ReturnType<typeof setTimeout>
function debouncedSearch() { clearTimeout(dTimer); dTimer = setTimeout(() => loadProducts(), 300) }

async function loadProducts() {
  loading.value = true
  try {
    const res = await productService.list({ search: search.value || undefined, per_page: 50, status: 'active' })
    products.value = res.data
  } catch { products.value = [] } finally { loading.value = false }
}

onMounted(loadProducts)
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
.text-muted { color: var(--gray-400); font-size: var(--text-sm); }

.labels-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
  align-items: flex-start;
}
@media (max-width: 900px) { .labels-layout { grid-template-columns: 1fr; } }

.labels-right { display: flex; flex-direction: column; gap: 1rem; }

.section-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.section-header-row h3 { font-size: var(--text-base); font-weight: 700; color: var(--gray-900); margin: 0; }
.search-sm { width: 180px; }

.state-center { text-align: center; padding: 1.5rem; color: var(--gray-400); font-size: var(--text-sm); }

/* Product pick list */
.product-pick-list { display: flex; flex-direction: column; gap: 2px; max-height: 480px; overflow-y: auto; }
.pick-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background 0.1s;
}
.pick-row:hover { background: var(--gray-50); }
.pick-row--selected { background: var(--brand-primary-bg); }
.pick-cb { width: 15px; height: 15px; accent-color: var(--brand-primary); cursor: pointer; flex-shrink: 0; }
.pick-info { flex: 1; min-width: 0; }
.pick-name { font-size: var(--text-sm); font-weight: 500; color: var(--gray-900); }
.pick-sku  { font-family: monospace; font-size: 0.7rem; color: var(--gray-400); }
.pick-copies { display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; }
.copies-btn {
  width: 22px; height: 22px;
  border: 1px solid var(--gray-200);
  border-radius: 50%;
  background: white;
  font-size: 0.875rem;
  line-height: 1;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.copies-btn:hover { background: var(--gray-100); }
.copies-val { font-size: var(--text-sm); font-weight: 600; color: var(--brand-primary-dark); min-width: 24px; text-align: center; }

/* Format choice */
.format-choice { display: flex; gap: 0.75rem; }
.format-opt {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 0.75rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.12s;
  text-align: center;
}
.format-opt.active { border-color: var(--brand-primary); background: var(--brand-primary-bg); }
.format-opt span { font-size: var(--text-sm); font-weight: 600; color: var(--gray-900); }
.format-opt small { font-size: var(--text-xs); color: var(--gray-400); }

/* Options */
.options-list { display: flex; flex-direction: column; gap: 0.5rem; }
.opt-row { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: var(--text-sm); color: var(--gray-700); }

/* Summary */
.summary-list { display: flex; flex-direction: column; gap: 0.375rem; margin-bottom: 0.75rem; }
.summary-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--gray-50);
  border-radius: var(--radius-sm);
  padding: 0.375rem 0.625rem;
}
.summary-name { flex: 1; font-size: var(--text-sm); color: var(--gray-700); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.summary-copies { font-size: var(--text-xs); font-weight: 600; color: var(--brand-primary-dark); background: var(--brand-primary-bg); padding: 2px 6px; border-radius: var(--radius-sm); white-space: nowrap; }
.summary-remove { background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 0 2px; font-size: 0.75rem; }
.summary-remove:hover { color: var(--color-error); }
.summary-total { font-size: var(--text-sm); color: var(--gray-600); text-align: right; }
</style>
