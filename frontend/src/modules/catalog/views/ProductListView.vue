<template>
  <div>
    <CatalogTabNav />
    <div class="page-header">
      <div>
        <h2>Catalogue produits</h2>
        <p class="page-subtitle">{{ meta.total ?? '—' }} produit{{ (meta.total ?? 0) !== 1 ? 's' : '' }}</p>
      </div>
      <div class="header-actions">
        <RouterLink to="/catalog/products/create" class="btn btn-primary">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          Nouveau produit
        </RouterLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg class="search-icon" width="15" height="15" viewBox="0 0 16 16" fill="none">
          <circle cx="7" cy="7" r="4.5" stroke="var(--gray-400)" stroke-width="1.4"/>
          <path d="M10.5 10.5l2.5 2.5" stroke="var(--gray-400)" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input v-model="filters.search" type="text" class="form-input search-input"
               placeholder="     Nom, SKU, code-barres…" @input="debouncedLoad" />
      </div>
      <select v-model="filters.status" class="form-input filter-sel" @change="load">
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="draft">Brouillon</option>
        <option value="archived">Archivé</option>
      </select>
      <select v-model="filters.category_id" class="form-input filter-sel" @change="load">
        <option value="">Toutes catégories</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>

    <!-- Selection action bar -->
    <Transition name="slide-down">
      <div v-if="selected.size > 0" class="selection-bar">
        <span class="sel-count">{{ selected.size }} produit{{ selected.size > 1 ? 's' : '' }} sélectionné{{ selected.size > 1 ? 's' : '' }}</span>
        <div class="sel-actions">
          <div class="copies-wrap">
            <label class="copies-label">Copies :</label>
            <input v-model.number="batchCopies" type="number" min="1" max="500"
                   class="copies-input" />
          </div>
          <button class="btn btn-secondary btn-sm" @click="printBatch('thermal')" :disabled="printing">
            <span v-if="printing" class="spinner-sm"></span>
            <template v-else>🖨</template>
            Thermique
          </button>
          <button class="btn btn-secondary btn-sm" @click="printBatch('a4sheet')" :disabled="printing">
            📄 Planche A4
          </button>
          <button class="btn btn-ghost btn-sm" @click="clearSelection">✕ Désélectionner</button>
        </div>
      </div>
    </Transition>

    <StateBlock v-if="loading" variant="loading" />

    <StateBlock
      v-else-if="products.length === 0"
      variant="empty"
      title="Aucun produit"
      :message="filters.search ? 'Aucun résultat pour cette recherche.' : 'Commencez par ajouter votre premier produit.'"
    >
      <template v-if="!filters.search" #action>
        <RouterLink to="/catalog/products/create" class="btn btn-primary">Ajouter un produit</RouterLink>
      </template>
    </StateBlock>

    <div v-else class="card table-scroll" style="padding:0">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:40px">
              <input type="checkbox" :checked="allSelected" :indeterminate="someSelected && !allSelected"
                     @change="toggleAll" class="cb" />
            </th>
            <th>Produit</th>
            <th class="hide-mobile">Catégorie</th>
            <th>Prix</th>
            <th class="hide-mobile">Statut</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id" :class="{ 'row-selected': selected.has(p.id) }">
            <td>
              <input type="checkbox" :checked="selected.has(p.id)"
                     @change="toggleSelect(p.id)" class="cb" />
            </td>
            <td>
              <div class="product-cell">
                <div class="product-thumb">{{ p.name.charAt(0).toUpperCase() }}</div>
                <div>
                  <!-- Clickable name → show page -->
                  <RouterLink :to="`/catalog/products/${p.id}`" class="product-name product-name-link">{{ p.name }}</RouterLink>
                  <div class="product-meta">
                    <span class="sku-tag">{{ p.sku }}</span>
                    <span v-if="p.has_variants || (p.variants_count ?? 0) > 0" class="variant-tag">
                      {{ p.variants_count ?? '' }} variante{{ (p.variants_count ?? 0) > 1 ? 's' : '' }}
                    </span>
                    <span v-if="p.barcode" class="barcode-tag">{{ p.barcode }}</span>
                  </div>
                </div>
              </div>
            </td>
            <td class="hide-mobile">
              <span v-if="p.category" class="badge badge-gray">{{ p.category.name }}</span>
              <span v-else class="dim">—</span>
            </td>
            <td>
              <div class="price-cell">
                <span :class="p.is_on_sale ? 'price-sale' : 'price-normal'">{{ p.price.formatted }}</span>
                <span v-if="p.compare_at_price" class="price-compare">{{ p.compare_at_price.formatted }}</span>
              </div>
            </td>
            <td class="hide-mobile">
              <span :class="statusClass(p.status)">{{ statusLbl(p.status) }}</span>
            </td>
            <td>
              <div class="row-actions">
                <!-- Voir → show page / Éditer → edit form -->
                <RouterLink :to="`/catalog/products/${p.id}`" class="btn btn-ghost btn-sm" title="Voir la fiche">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M1 8C2.5 4.5 5 2.5 8 2.5S13.5 4.5 15 8c-1.5 3.5-4 5.5-7 5.5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.4"/>
                  </svg>
                </RouterLink>
                <RouterLink :to="`/catalog/products/${p.id}/edit`" class="btn btn-ghost btn-sm" title="Modifier">✏️</RouterLink>
                <button class="btn btn-ghost btn-sm icon-btn" title="Imprimer étiquette"
                        @click.stop="printOne(p)">🏷</button>
                <button class="btn btn-ghost btn-sm"
                        @click="toggleStatus(p)">{{ p.status === 'active' ? 'Archiver' : 'Activer' }}</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">← Précédent</button>
      <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Suivant →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import CatalogTabNav from '../components/CatalogTabNav.vue'
import { productService } from '../services/productService'
import { getAuthToken } from '@/api/authToken'
import StateBlock from '@/shared/ui/StateBlock.vue'
import type { Category, Product, ProductStatus } from '../types'

const products   = ref<Product[]>([])
const categories = ref<Category[]>([])
const loading    = ref(false)
const printing   = ref(false)
const meta       = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const filters    = reactive({ search: '', status: '', category_id: '', page: 1 })

// ── Selection ──────────────────────────────────────────────────────────────
const selected    = ref<Set<string>>(new Set())
const batchCopies = ref(1)

const allSelected  = computed(() => products.value.length > 0 && products.value.every(p => selected.value.has(p.id)))
const someSelected = computed(() => products.value.some(p => selected.value.has(p.id)))

function toggleSelect(id: string) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}
function toggleAll() {
  if (allSelected.value) {
    selected.value = new Set()
  } else {
    selected.value = new Set(products.value.map(p => p.id))
  }
}
function clearSelection() { selected.value = new Set() }

// ── Print ──────────────────────────────────────────────────────────────────
async function printBatch(format: 'thermal' | 'a4sheet') {
  if (!selected.value.size) return
  printing.value = true
  try {
    await productService.printBatch({
      items: [...selected.value].map(id => ({ product_id: id, copies: batchCopies.value })),
      format,
      show_price: true,
      show_qr: true,
    })
  } catch { /* ignore */ } finally {
    printing.value = false
  }
}

async function printOne(p: Product) {
  const url   = productService.getLabelUrl(p.id, { format: 'thermal', price: true, qr: true, copies: 1 })
  const token = getAuthToken() ?? ''
  const resp  = await fetch(url, { headers: { Authorization: `Bearer ${token}` } })
  const html  = await resp.text()
  const win   = window.open('', '_blank')
  if (win) { win.document.write(html); win.document.close(); setTimeout(() => win.print(), 400) }
}

// ── Load ───────────────────────────────────────────────────────────────────
let debTimer: ReturnType<typeof setTimeout>
function debouncedLoad() { clearTimeout(debTimer); debTimer = setTimeout(() => { filters.page = 1; load() }, 300) }

async function load() {
  loading.value = true
  try {
    const params: any = { page: filters.page, per_page: meta.per_page }
    if (filters.search)      params.search      = filters.search
    if (filters.status)      params.status      = filters.status
    if (filters.category_id) params.category_id = filters.category_id
    const res = await productService.list(params)
    products.value = res.data
    Object.assign(meta, res.meta)
  } catch { products.value = [] } finally { loading.value = false }
}

async function toggleStatus(p: Product) {
  try {
    p.status === 'active' ? await productService.archive(p.id) : await productService.activate(p.id)
    load()
  } catch { /* ignore */ }
}

function goPage(n: number) { filters.page = n; load() }
function statusClass(s: ProductStatus) {
  return { active: 'badge badge-success', draft: 'badge badge-gray', archived: 'badge badge-warning' }[s] ?? 'badge badge-gray'
}
function statusLbl(s: ProductStatus) {
  return { active: 'Actif', draft: 'Brouillon', archived: 'Archivé' }[s] ?? s
}

onMounted(() => {
  load()
  productService.categories.list().then(c => { categories.value = c }).catch(() => {})
})
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }
.header-actions { display: flex; gap: 0.5rem; }
.dim { color: var(--gray-400); font-size: var(--text-sm); }

.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.search-input { padding-left: 2.25rem !important; }
.filter-sel { width: 180px; }

/* Selection bar */
.selection-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background: var(--brand-primary-bg);
  border: 1px solid var(--brand-primary-light);
  border-radius: var(--radius-md);
  padding: 0.625rem 1rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}
.sel-count { font-size: var(--text-sm); font-weight: 600; color: var(--brand-primary-dark); }
.sel-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.copies-wrap { display: flex; align-items: center; gap: 0.375rem; }
.copies-label { font-size: var(--text-xs); color: var(--gray-600); white-space: nowrap; }
.copies-input {
  width: 60px;
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-sm);
  padding: 0.25rem 0.5rem;
  font-size: var(--text-sm);
  text-align: center;
}

/* Table */
.cb { width: 15px; height: 15px; cursor: pointer; accent-color: var(--brand-primary); }
.row-selected td { background: var(--brand-primary-bg) !important; }
.product-cell { display: flex; align-items: center; gap: 0.75rem; }
.product-thumb {
  width: 36px; height: 36px; border-radius: var(--radius-md);
  background: var(--brand-primary-bg); color: var(--brand-primary-dark);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: var(--text-sm); flex-shrink: 0;
}
.product-name { font-weight: 500; color: var(--gray-900); font-size: var(--text-sm); }
.product-name-link { text-decoration: none; transition: color 0.15s; }
.product-name-link:hover { color: var(--brand-primary); text-decoration: underline; }
.product-meta { display: flex; align-items: center; gap: 0.375rem; margin-top: 2px; flex-wrap: wrap; }
.sku-tag { font-family: monospace; font-size: 0.7rem; color: var(--gray-400); }
.variant-tag { font-size: 0.65rem; font-weight: 600; background: var(--brand-secondary-bg); color: var(--brand-secondary-dark); padding: 1px 5px; border-radius: 3px; }
.barcode-tag { font-family: monospace; font-size: 0.65rem; color: var(--gray-400); }

.price-cell { display: flex; flex-direction: column; gap: 1px; }
.price-normal { font-weight: 500; color: var(--gray-900); font-size: var(--text-sm); }
.price-sale { font-weight: 600; color: var(--color-error); font-size: var(--text-sm); }
.price-compare { font-size: var(--text-xs); color: var(--gray-400); text-decoration: line-through; }
.row-actions { display: flex; gap: 0.375rem; justify-content: flex-end; }
.icon-btn { font-size: 1rem; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; }
.page-info { font-size: var(--text-sm); color: var(--gray-500); }

@media (max-width: 768px) {
  .filter-sel { width: 100%; }
  .search-wrap { min-width: 100%; }
}
</style>
