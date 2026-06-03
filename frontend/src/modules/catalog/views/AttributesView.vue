<template>
  <div>
    <CatalogTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">Attributs produit</h1>
        <p class="page-subtitle">Axes de variation configurés sur les produits avec déclinaisons</p>
      </div>
    </div>

    <div v-if="loading" class="loading-center" style="min-height: 180px;">
      <div class="spinner-sm" style="width:28px;height:28px;border-width:3px"></div>
    </div>

    <div v-else-if="products.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <path d="M10 20h20M20 10v10l4 4" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h3>Aucun attribut défini</h3>
      <p>Activez les déclinaisons sur un produit puis configurez ses axes de variation.</p>
      <router-link to="/catalog" class="btn btn-primary">Voir les produits</router-link>
    </div>

    <div v-else class="products-grid">
      <div v-for="product in products" :key="product.id" class="attr-card card">
        <div class="attr-card-header">
          <div class="product-info">
            <div class="product-thumb">{{ product.name.charAt(0).toUpperCase() }}</div>
            <div>
              <h3 class="product-name">{{ product.name }}</h3>
              <code class="product-sku">{{ product.sku }}</code>
            </div>
          </div>
          <div class="card-actions">
            <span class="variants-count">{{ product.variants?.length ?? 0 }} déclinaison(s)</span>
            <router-link :to="`/catalog/products/${product.id}`" class="btn btn-ghost btn-sm">
              Voir la fiche
            </router-link>
          </div>
        </div>

        <div v-if="product.attributes && product.attributes.length" class="axes-list">
          <div v-for="attr in product.attributes" :key="attr.id" class="axis-row">
            <span class="axis-name">{{ attr.name }}</span>
            <div class="axis-values">
              <span
                v-for="val in attr.values" :key="val.id"
                class="val-chip"
                :style="val.color_hex ? { background: val.color_hex, color: isLight(val.color_hex) ? '#333' : '#fff' } : {}"
              >{{ val.label }}</span>
            </div>
          </div>
        </div>

        <p v-else class="no-attrs-hint">
          Aucun attribut configuré — les axes se définissent depuis
          <router-link :to="`/catalog/products/${product.id}/edit`">la fiche produit</router-link>.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import CatalogTabNav from '../components/CatalogTabNav.vue'
import client from '@/api/client'

interface AttributeValue { id: string; label: string; color_hex?: string }
interface Attribute      { id: string; name: string; values: AttributeValue[] }
interface ProductWithAttrs {
  id: string; name: string; sku: string; has_variants: boolean; product_type: string
  variants?: any[]
  attributes?: Attribute[]
}

const products = ref<ProductWithAttrs[]>([])
const loading  = ref(false)

function isLight(hex: string): boolean {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return (r * 299 + g * 587 + b * 114) / 1000 > 128
}

async function load() {
  loading.value = true
  try {
    // Fetch products with variants (by flag OR actual variants via product_type)
    const r = await client.get('/api/catalog/products', { params: { per_page: 100 } })
    const withVariants = (r.data.data ?? []).filter(
      (p: ProductWithAttrs) => p.has_variants || p.product_type === 'variable' || (p.variants?.length ?? 0) > 0
    )

    const withAttrs = await Promise.all(
      withVariants.map(async (p: ProductWithAttrs) => {
        try {
          const attrResp = await client.get(`/api/catalog/products/${p.id}/attributes`)
          return { ...p, attributes: attrResp.data.data ?? [] }
        } catch {
          return { ...p, attributes: [] }
        }
      })
    )
    // Only show products that have at least one attribute configured
    products.value = withAttrs
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-header    { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title     { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle  { color: var(--gray-500); font-size: 0.875rem; margin-top: 4px; }

.products-grid  { display: flex; flex-direction: column; gap: 16px; }
.attr-card      { padding: 20px; }
.attr-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 12px; flex-wrap: wrap; }
.product-info   { display: flex; align-items: center; gap: 10px; }
.product-thumb  { width: 36px; height: 36px; background: var(--gray-100); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; color: var(--gray-600); flex-shrink: 0; }
.product-name   { font-size: 0.9375rem; font-weight: 600; margin: 0; }
.product-sku    { font-size: 0.75rem; color: var(--gray-400); font-family: monospace; }
.card-actions   { display: flex; align-items: center; gap: 10px; }
.variants-count { font-size: 0.75rem; color: var(--brand-primary); background: var(--brand-primary-bg, #ecfdf5); padding: 2px 8px; border-radius: 10px; white-space: nowrap; }

.axes-list      { display: flex; flex-direction: column; gap: 10px; }
.axis-row       { display: flex; align-items: center; gap: 12px; }
.axis-name      { font-size: 0.8125rem; font-weight: 600; color: var(--gray-600); min-width: 80px; flex-shrink: 0; }
.axis-values    { display: flex; flex-wrap: wrap; gap: 4px; }
.val-chip       { font-size: 0.75rem; background: var(--gray-100); color: var(--gray-700); padding: 2px 10px; border-radius: 10px; font-weight: 500; }

.no-attrs-hint  { font-size: 0.8125rem; color: var(--gray-400); margin: 0; }
.no-attrs-hint a { color: var(--brand-secondary, #3b82f6); }
</style>
