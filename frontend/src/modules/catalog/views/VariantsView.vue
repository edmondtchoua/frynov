<template>
  <div>
    <CatalogTabNav />

    <div class="page-header">
      <div>
        <h2>{{ $t('catalog.variants.title') }}</h2>
        <p class="page-subtitle">{{ $t('catalog.variants.subtitle', { count: meta.total ?? '—', products: statsData.productsCount }) }}</p>
      </div>
      <div class="page-header-cta">
        <span class="cta-note">{{ $t('catalog.variants.ctaNote') }}</span>
        <RouterLink to="/catalog" class="btn btn-primary">← {{ $t('catalog.viewProductsList') }}</RouterLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg class="search-icon" width="15" height="15" viewBox="0 0 16 16" fill="none">
          <circle cx="7" cy="7" r="4.5" stroke="var(--gray-400)" stroke-width="1.4"/>
          <path d="M10.5 10.5l2.5 2.5" stroke="var(--gray-400)" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input
          v-model="search"
          type="text"
          class="form-input search-input"
          :placeholder="$t('catalog.variants.searchPlaceholder')"
          @input="debouncedLoad"
        />
      </div>
      <select v-model="filterStatus" class="form-input filter-sel" @change="load">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option value="active">{{ $t('catalog.variants.statusActiveProduct') }}</option>
        <option value="draft">{{ $t('catalog.status.draft') }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 220px;">
      <span class="spinner-sm" style="width:28px;height:28px;border-width:3px"></span>
    </div>

    <!-- Empty -->
    <div v-else-if="variants.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <circle cx="14" cy="14" r="5" stroke="var(--brand-primary)" stroke-width="2"/>
        <circle cx="26" cy="14" r="5" stroke="var(--brand-primary)" stroke-width="2"/>
        <circle cx="14" cy="26" r="5" stroke="var(--brand-primary)" stroke-width="2"/>
        <circle cx="26" cy="26" r="5" stroke="var(--brand-primary)" stroke-width="2"/>
      </svg>
      <h3>{{ $t('catalog.variants.empty') }}</h3>
      <p>
        {{ search ? $t('catalog.noResults') : $t('catalog.variants.emptyHint') }}
      </p>
      <RouterLink v-if="!search" to="/catalog" class="btn btn-primary">{{ $t('catalog.variants.goToCatalog') }}</RouterLink>
    </div>

    <!-- Table -->
    <div v-else class="card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('catalog.variants.colVariant') }}</th>
            <th>{{ $t('catalog.variants.colParent') }}</th>
            <th class="hide-mobile">{{ $t('catalog.variants.colAttributes') }}</th>
            <th>{{ $t('catalog.colPrice') }}</th>
            <th>{{ $t('catalog.variants.colStock') }}</th>
            <th class="hide-mobile">{{ $t('catalog.colCategory') }}</th>
            <th style="text-align:right">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in variants" :key="v.id">
            <td>
              <div class="variant-cell">
                <div class="variant-thumb">{{ v.label?.charAt(0)?.toUpperCase() ?? 'V' }}</div>
                <div>
                  <div class="variant-label">{{ v.label || '—' }}</div>
                  <code class="variant-sku">{{ v.sku }}</code>
                </div>
              </div>
            </td>
            <td>
              <RouterLink :to="`/catalog/products/${v.product_id}`" class="product-link">
                {{ v.product?.name ?? '—' }}
              </RouterLink>
              <div class="product-sku-hint hide-mobile">{{ v.product?.sku }}</div>
            </td>
            <td class="hide-mobile">
              <div class="attr-chips">
                <!-- Use attribute_chips (from JSON blob) — always populated -->
                <span
                  v-for="chip in (v.attribute_chips ?? [])"
                  :key="chip.name"
                  class="attr-chip"
                  :title="chip.name"
                >{{ chip.label }}</span>
                <!-- Fallback: show label if no chips -->
                <span v-if="!v.attribute_chips?.length && v.label" class="attr-chip">{{ v.label }}</span>
                <span v-if="!v.attribute_chips?.length && !v.label" class="dim">—</span>
              </div>
            </td>
            <td>
              <span class="price-tag">{{ formatPrice(v.price_amount, v.price_currency) }}</span>
            </td>
            <td>
              <div class="stock-cell" :class="{ 'stock-zero': !v.stock_available }">
                <span class="stock-qty">{{ v.stock_available ?? 0 }}</span>
                <span v-if="v.stock_qty !== v.stock_available" class="stock-total">/ {{ v.stock_qty ?? 0 }}</span>
              </div>
            </td>
            <td class="hide-mobile">
              <span v-if="v.product?.category" class="badge badge-gray">{{ v.product.category.name }}</span>
              <span v-else class="dim">—</span>
            </td>
            <td style="text-align:right">
              <RouterLink :to="`/catalog/products/${v.product_id}`" class="btn btn-ghost btn-sm" :title="$t('catalog.variants.viewProductSheet')">
                {{ $t('common.view') }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="pagination-bar">
        <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">← {{ $t('common.previous') }}</button>
        <span class="page-info">{{ $t('common.pageOf', { current: meta.current_page, total: meta.last_page }) }}</span>
        <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">{{ $t('common.next') }} →</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import CatalogTabNav from '../components/CatalogTabNav.vue'
import client from '@/api/client'

interface AttributeChip { name: string; label: string }

interface ProductVariantRow {
  id: string
  product_id: string
  sku: string
  label?: string
  price_amount?: number
  price_currency?: string
  stock_qty?: number
  stock_available?: number
  attribute_chips?: AttributeChip[]
  attribute_values?: { id: string; label: string; color_hex?: string; attribute?: { id: string; name: string } }[]
  product?: { id: string; name: string; sku: string; category?: { id: string; name: string } | null }
}

interface PaginatorMeta { current_page: number; last_page: number; total: number; per_page: number }

const variants = ref<ProductVariantRow[]>([])
const meta     = ref<PaginatorMeta>({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
const loading  = ref(false)
const search   = ref('')
const filterStatus = ref('')
const statsData = reactive({ productsCount: 0 })

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(load, 280)
}

async function load(page = 1) {
  loading.value = true
  try {
    const r = await client.get('/api/catalog/variants', {
      params: {
        search:   search.value || undefined,
        status:   filterStatus.value || undefined,
        per_page: 50,
        page,
      },
    })
    // Paginator direct response: { data: [...], total, current_page, last_page, ... }
    const paginator = r.data
    variants.value  = paginator.data ?? []
    // Build meta from paginator root fields (no wrapper `meta` key in Laravel paginate())
    meta.value = {
      current_page: paginator.current_page ?? page,
      last_page:    paginator.last_page    ?? 1,
      total:        paginator.total        ?? variants.value.length,
      per_page:     paginator.per_page     ?? 50,
    }
    statsData.productsCount = new Set(variants.value.map((v: ProductVariantRow) => v.product_id)).size
  } catch (e: any) {
    console.error('[VariantsView] load error:', e?.response?.status, e?.message)
    variants.value = []
    meta.value = { current_page: 1, last_page: 1, total: 0, per_page: 50 }
  } finally {
    loading.value = false
  }
}

function goPage(page: number) { load(page) }

function formatPrice(amount?: number, currency?: string): string {
  if (amount === undefined || amount === null || amount === 0) return '—'
  return formatMoney(amount, currency ?? 'XAF')
}

onMounted(load)
</script>

<style scoped>
.page-header     { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-subtitle   { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }
.page-header-cta { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.cta-note        { font-size: var(--text-sm); color: var(--gray-400); font-style: italic; }

@media (max-width: 600px) {
  .page-header     { flex-direction: column; gap: 12px; }
  .cta-note        { display: none; }
}
.filter-bar     { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.search-wrap    { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
.search-icon    { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; }
.search-input   { padding-left: 32px !important; }
.filter-sel     { min-width: 160px; }

.variant-cell   { display: flex; align-items: center; gap: 10px; }
.variant-thumb  { width: 32px; height: 32px; background: var(--gray-100); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; color: var(--gray-600); flex-shrink: 0; }
.variant-label  { font-weight: 500; font-size: 0.875rem; color: var(--gray-900); }
.variant-sku    { font-size: 0.75rem; color: var(--gray-400); font-family: monospace; }

.product-link   { font-size: 0.875rem; font-weight: 500; color: var(--brand-secondary, #3b82f6); text-decoration: none; }
.product-link:hover { text-decoration: underline; }
.product-sku-hint { font-size: 0.75rem; color: var(--gray-400); font-family: monospace; }

.attr-chips     { display: flex; gap: 4px; flex-wrap: wrap; }
.attr-chip      { display: inline-flex; align-items: center; gap: 4px; background: var(--gray-100); color: var(--gray-700); padding: 2px 7px; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
.color-dot      { width: 10px; height: 10px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.15); flex-shrink: 0; }

.price-tag      { font-weight: 600; font-size: 0.875rem; color: var(--gray-900); }

/* Stock column */
.stock-cell     { display: flex; align-items: baseline; gap: 3px; }
.stock-qty      { font-weight: 700; font-size: 0.9375rem; color: var(--gray-900); }
.stock-total    { font-size: 0.75rem; color: var(--gray-400); }
.stock-zero .stock-qty { color: var(--gray-300); }
.dim            { color: var(--gray-300); font-size: 0.875rem; }

.pagination-bar {
  display: flex; align-items: center; justify-content: center; gap: 16px;
  padding: 12px 16px; border-top: 1px solid var(--gray-100);
}
.page-info { font-size: 0.875rem; color: var(--gray-500); }

@media (max-width: 768px) {
  .filter-bar { flex-direction: column; }
  .search-wrap { max-width: 100%; }
}
</style>
