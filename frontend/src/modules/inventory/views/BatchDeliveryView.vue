<template>
  <div>
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">Réception de livraison</h1>
        <p class="page-subtitle">Enregistrez plusieurs produits en une seule opération</p>
      </div>
      <router-link to="/inventory" class="btn btn-ghost">← Stock</router-link>
    </div>

    <div class="card delivery-form">
      <div class="form-group">
        <label class="form-label">Référence livraison <span class="hint">(optionnel)</span></label>
        <input v-model="reference" class="form-input" placeholder="Ex: BL-2026-042, Commande fournisseur n°…" />
      </div>

      <h3 class="section-title">Articles reçus</h3>

      <div v-for="(item, i) in items" :key="i" class="delivery-item">
        <div class="item-search">
          <input
            v-model="item.search"
            class="form-input"
            :placeholder="`Produit ${i + 1} — nom ou SKU`"
            @input="onSearch(i)"
            @focus="item.showSuggestions = true"
          />
          <div v-if="item.suggestions.length && item.showSuggestions" class="suggestions" @mousedown.prevent>
            <button
              v-for="p in item.suggestions" :key="p.id"
              type="button" class="suggestion-item"
              @click="selectProduct(i, p)"
            >
              <span class="sug-name">{{ p.name }}</span>
              <code class="sug-sku">{{ p.sku }}</code>
            </button>
          </div>
          <div v-if="item.product" class="selected-product">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M2 6l3 3 5-5" stroke="#10b981" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span>{{ item.product.name }}</span>
            <code>{{ item.product.sku }}</code>
          </div>
        </div>
        <input
          v-model.number="item.quantity"
          type="number" min="1"
          class="form-input qty-input"
          placeholder="Qté"
        />
        <button
          class="btn btn-ghost btn-sm remove-btn"
          @click="removeItem(i)"
          :disabled="items.length === 1"
          title="Supprimer"
        >✕</button>
      </div>

      <button type="button" class="btn btn-secondary btn-sm mt-2" @click="addItem">
        + Ajouter un article
      </button>

      <div class="form-actions">
        <span class="items-summary">{{ validItemsCount }} article(s) prêt(s)</span>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="!canSubmit || submitting"
          @click="submit"
        >
          <span v-if="submitting" class="spinner-sm spinner-white"></span>
          {{ submitting ? 'Enregistrement…' : 'Valider la réception' }}
        </button>
      </div>

      <div v-if="success" class="alert alert-success">
        ✓ {{ success }} mouvement(s) enregistré(s). Redirection vers le stock…
      </div>
      <div v-if="error" class="alert alert-error">{{ error }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import InventoryTabNav from '../components/InventoryTabNav.vue'
import api from '@/services/api'

interface Product { id: string; name: string; sku: string }
interface DeliveryItem {
  search: string
  product: Product | null
  quantity: number
  suggestions: Product[]
  showSuggestions: boolean
}

const router    = useRouter()
const reference = ref('')
const submitting = ref(false)
const success    = ref<number | null>(null)
const error      = ref('')

const items = ref<DeliveryItem[]>([
  { search: '', product: null, quantity: 1, suggestions: [], showSuggestions: false },
])

const validItemsCount = computed(() => items.value.filter(i => i.product && i.quantity > 0).length)
const canSubmit       = computed(() => validItemsCount.value > 0)

function addItem() {
  items.value.push({ search: '', product: null, quantity: 1, suggestions: [], showSuggestions: false })
}

function removeItem(i: number) {
  if (items.value.length > 1) items.value.splice(i, 1)
}

const searchTimers: Record<number, ReturnType<typeof setTimeout>> = {}

function onSearch(i: number) {
  items.value[i].product = null
  clearTimeout(searchTimers[i])
  const q = items.value[i].search.trim()
  if (q.length < 2) { items.value[i].suggestions = []; return }
  searchTimers[i] = setTimeout(async () => {
    try {
      const r = await api.get('/catalog/products', { params: { search: q, per_page: 6 } })
      items.value[i].suggestions = r.data.data ?? []
      items.value[i].showSuggestions = true
    } catch {}
  }, 280)
}

function selectProduct(i: number, p: Product) {
  items.value[i].product         = p
  items.value[i].search          = p.name
  items.value[i].suggestions     = []
  items.value[i].showSuggestions = false
}

async function submit() {
  error.value   = ''
  success.value = null
  submitting.value = true
  try {
    const payload = {
      reference: reference.value.trim() || undefined,
      items: items.value
        .filter(i => i.product && i.quantity > 0)
        .map(i => ({ product_id: i.product!.id, quantity: i.quantity })),
    }
    const r = await api.post('/inventory/deliveries', payload)
    success.value = r.data.count ?? payload.items.length
    setTimeout(() => router.push('/inventory'), 2000)
  } catch (e: unknown) {
    error.value = 'Erreur lors de la réception. Vérifiez les données et réessayez.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.page-header    { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title     { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle  { color: var(--gray-500); font-size: 0.875rem; margin-top: 4px; }
.delivery-form  { padding: 24px; max-width: 740px; }
.section-title  { font-size: 1rem; font-weight: 600; margin: 20px 0 12px; color: var(--gray-800); }
.hint           { color: var(--gray-400); font-size: 0.8125rem; font-weight: 400; }

.delivery-item  { display: grid; grid-template-columns: 1fr 90px 34px; gap: 8px; margin-bottom: 8px; align-items: start; }
.item-search    { position: relative; }
.suggestions    { position: absolute; top: calc(100% + 2px); left: 0; right: 0; background: white; border: 1px solid var(--gray-200); border-radius: 8px; z-index: 20; box-shadow: 0 4px 12px rgba(0,0,0,.1); max-height: 200px; overflow-y: auto; }
.suggestion-item { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 8px 12px; background: none; border: none; border-bottom: 1px solid var(--gray-100); cursor: pointer; font-size: 0.875rem; }
.suggestion-item:hover { background: var(--gray-50); }
.sug-name       { color: var(--gray-800); font-weight: 500; flex: 1; }
.sug-sku        { color: var(--gray-400); font-size: 0.75rem; }
.selected-product { display: flex; align-items: center; gap: 4px; margin-top: 4px; font-size: 0.75rem; color: var(--gray-600); }
.selected-product code { color: var(--gray-400); font-family: monospace; }

.qty-input      { text-align: center; }
.remove-btn     { align-self: center; color: var(--gray-400); }
.mt-2           { margin-top: 8px; }

.form-actions   { display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--gray-100); }
.items-summary  { font-size: 0.875rem; color: var(--gray-500); }

.alert          { margin-top: 16px; padding: 12px 16px; border-radius: 8px; font-size: 0.875rem; }
.alert-success  { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.alert-error    { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
