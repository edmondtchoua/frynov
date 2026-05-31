<template>
  <div>
    <div class="page-header">
      <div>
        <h2>Catalogue produits</h2>
        <p class="page-subtitle">{{ meta.total ?? '—' }} produits</p>
      </div>
      <RouterLink to="/catalog/products/create" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        Nouveau produit
      </RouterLink>
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
          placeholder="Nom, SKU, code-barres…"
          @input="debouncedLoad"
        />
      </div>

      <select v-model="filters.status" class="form-input filter-select" @change="load">
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="draft">Brouillon</option>
        <option value="archived">Archivé</option>
      </select>

      <select v-model="filters.category_id" class="form-input filter-select" @change="load">
        <option value="">Toutes les catégories</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <!-- Empty -->
    <div v-else-if="products.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <path d="M10 20h20M20 10v20" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h3>Aucun produit</h3>
      <p>{{ filters.search ? 'Aucun résultat pour cette recherche.' : 'Commencez par ajouter votre premier produit.' }}</p>
      <RouterLink v-if="!filters.search" to="/catalog/products/create" class="btn btn-primary">
        Ajouter un produit
      </RouterLink>
    </div>

    <!-- Table -->
    <div v-else class="card" style="padding: 0; overflow: hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Produit</th>
            <th class="hide-mobile">Catégorie</th>
            <th>Prix</th>
            <th class="hide-mobile">Statut</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id">
            <td>
              <div class="product-cell">
                <div class="product-thumb">
                  {{ product.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <div class="product-name">{{ product.name }}</div>
                  <div class="product-sku">{{ product.sku }}</div>
                </div>
              </div>
            </td>
            <td class="hide-mobile">
              <span v-if="product.category" class="badge badge-gray">{{ product.category.name }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <div class="price-cell">
                <span :class="product.is_on_sale ? 'price-sale' : ''">{{ product.price.formatted }}</span>
                <span v-if="product.compare_at_price" class="price-compare">
                  {{ product.compare_at_price.formatted }}
                </span>
              </div>
            </td>
            <td class="hide-mobile">
              <span :class="statusBadge(product.status)">{{ statusLabel(product.status) }}</span>
            </td>
            <td style="text-align: right;">
              <div class="row-actions">
                <RouterLink :to="`/catalog/products/${product.id}`" class="btn btn-ghost btn-sm">
                  Éditer
                </RouterLink>
                <button
                  class="btn btn-ghost btn-sm"
                  :title="product.status === 'active' ? 'Archiver' : 'Activer'"
                  @click="toggleStatus(product)"
                >
                  {{ product.status === 'active' ? 'Archiver' : 'Activer' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button
        class="btn btn-ghost btn-sm"
        :disabled="meta.current_page <= 1"
        @click="goToPage(meta.current_page - 1)"
      >← Précédent</button>
      <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button
        class="btn btn-ghost btn-sm"
        :disabled="meta.current_page >= meta.last_page"
        @click="goToPage(meta.current_page + 1)"
      >Suivant →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { productService } from '../services/productService'
import type { Category, Product, ProductStatus } from '../types'

const products   = ref<Product[]>([])
const categories = ref<Category[]>([])
const loading    = ref(false)
const meta       = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })

const filters = reactive({
  search:      '',
  status:      '',
  category_id: '',
  page:        1,
})

// Debounce helper
let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { filters.page = 1; load() }, 300)
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: filters.page, per_page: meta.per_page }
    if (filters.search)      params.search      = filters.search
    if (filters.status)      params.status      = filters.status
    if (filters.category_id) params.category_id = filters.category_id

    const res = await productService.list(params)
    products.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    products.value = []
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  try {
    categories.value = await productService.categories.list()
  } catch {
    categories.value = []
  }
}

async function toggleStatus(product: Product) {
  try {
    if (product.status === 'active') {
      await productService.archive(product.id)
    } else {
      await productService.activate(product.id)
    }
    load()
  } catch { /* ignore */ }
}

function goToPage(page: number) {
  filters.page = page
  load()
}

function statusBadge(status: ProductStatus): string {
  return { active: 'badge badge-success', draft: 'badge badge-gray', archived: 'badge badge-warning' }[status] ?? 'badge badge-gray'
}

function statusLabel(status: ProductStatus): string {
  return { active: 'Actif', draft: 'Brouillon', archived: 'Archivé' }[status] ?? status
}

onMounted(() => {
  load()
  loadCategories()
})
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }

.filter-bar {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}
.search-input { padding-left: 2.25rem; }
.filter-select { width: 180px; }

.product-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.product-thumb {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: var(--text-sm);
  flex-shrink: 0;
}
.product-name { font-weight: 500; color: var(--gray-900); font-size: var(--text-sm); }
.product-sku  { font-size: var(--text-xs); color: var(--gray-400); margin-top: 1px; font-family: monospace; }

.price-cell   { display: flex; flex-direction: column; gap: 2px; }
.price-sale   { color: var(--color-error); font-weight: 600; }
.price-compare{ font-size: var(--text-xs); color: var(--gray-400); text-decoration: line-through; }
.text-muted   { color: var(--gray-400); font-size: var(--text-sm); }

.row-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.5rem;
}
.page-info { font-size: var(--text-sm); color: var(--gray-500); }

@media (max-width: 768px) {
  .filter-select { width: 100%; }
  .search-wrap { min-width: 100%; }
}
</style>
