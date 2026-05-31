<template>
  <div>
    <div class="page-header">
      <div>
        <h2>{{ isEdit ? 'Modifier le produit' : 'Nouveau produit' }}</h2>
        <p v-if="isEdit && product" class="page-subtitle">SKU : {{ product.sku }}</p>
      </div>
      <RouterLink to="/catalog" class="btn btn-ghost">← Retour au catalogue</RouterLink>
    </div>

    <div v-if="pageLoading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <form v-else @submit.prevent="handleSubmit" novalidate>
      <div class="form-layout">

        <!-- Main column -->
        <div class="form-main">

          <div class="card">
            <h3 class="card-section-title">Informations générales</h3>

            <div class="form-group">
              <label class="form-label" for="name">Nom du produit <span class="required">*</span></label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="form-input"
                :class="{ error: errors.name }"
                placeholder="Ex : T-shirt Premium Coton"
                @input="clearError('name')"
              />
              <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="sku">SKU</label>
                <input
                  id="sku"
                  v-model="form.sku"
                  type="text"
                  class="form-input"
                  placeholder="Auto-généré si vide"
                />
              </div>
              <div class="form-group">
                <label class="form-label" for="barcode">Code-barres</label>
                <input
                  id="barcode"
                  v-model="form.barcode"
                  type="text"
                  class="form-input"
                  placeholder="EAN-13, UPC…"
                />
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="description">Description</label>
              <textarea
                id="description"
                v-model="form.description"
                class="form-input"
                rows="4"
                placeholder="Description complète du produit…"
              ></textarea>
            </div>
          </div>

          <div class="card">
            <h3 class="card-section-title">Prix</h3>

            <div class="form-row">
              <div class="form-group" style="flex: 2;">
                <label class="form-label" for="price_amount">Prix de vente <span class="required">*</span></label>
                <div class="input-currency-wrap">
                  <input
                    id="price_amount"
                    v-model.number="form.price_amount_display"
                    type="number"
                    step="0.01"
                    min="0"
                    class="form-input"
                    :class="{ error: errors.price_amount }"
                    placeholder="0.00"
                    @input="clearError('price_amount')"
                  />
                  <span class="currency-badge">{{ form.price_currency }}</span>
                </div>
                <span v-if="errors.price_amount" class="form-error">{{ errors.price_amount }}</span>
              </div>
              <div class="form-group">
                <label class="form-label" for="currency">Devise</label>
                <select id="currency" v-model="form.price_currency" class="form-input">
                  <option v-for="c in currencies" :key="c.code" :value="c.code">
                    {{ c.code }} — {{ c.label }}
                  </option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="compare_at">Prix barré (optionnel)</label>
                <input
                  id="compare_at"
                  v-model.number="form.compare_at_price_amount_display"
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-input"
                  placeholder="0.00"
                />
              </div>
              <div class="form-group">
                <label class="form-label" for="cost">Coût d'achat (optionnel)</label>
                <input
                  id="cost"
                  v-model.number="form.cost_amount_display"
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-input"
                  placeholder="0.00"
                />
              </div>
            </div>

            <div v-if="margin !== null" class="margin-display">
              <span class="margin-label">Marge estimée :</span>
              <span :class="margin > 0 ? 'margin-positive' : 'margin-negative'">
                {{ margin.toFixed(1) }}%
              </span>
            </div>
          </div>

        </div>

        <!-- Side column -->
        <div class="form-side">

          <div class="card">
            <h3 class="card-section-title">Statut</h3>
            <div class="status-radios">
              <label
                v-for="s in statuses"
                :key="s.value"
                class="status-radio"
                :class="{ active: form.status === s.value }"
              >
                <input v-model="form.status" type="radio" :value="s.value" class="sr-only" />
                <span class="status-dot" :class="`dot-${s.value}`"></span>
                <div>
                  <div class="status-radio-label">{{ s.label }}</div>
                  <div class="status-radio-hint">{{ s.hint }}</div>
                </div>
              </label>
            </div>
          </div>

          <div class="card">
            <h3 class="card-section-title">Catégorie</h3>
            <select v-model="form.category_id" class="form-input" style="margin-bottom: 0;">
              <option value="">Aucune catégorie</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <div class="card">
            <h3 class="card-section-title">Expédition</h3>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="weight">Poids (kg)</label>
              <input
                id="weight"
                v-model.number="form.weight_kg"
                type="number"
                step="0.01"
                min="0"
                class="form-input"
                placeholder="0.00"
              />
            </div>
          </div>

        </div>
      </div>

      <!-- Global error -->
      <div v-if="globalError" class="alert alert-error" role="alert" style="margin-top: 1rem;">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0">
          <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ globalError }}
      </div>

      <!-- Actions -->
      <div class="form-actions">
        <RouterLink to="/catalog" class="btn btn-ghost">Annuler</RouterLink>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner-sm spinner-white"></span>
          {{ saving ? 'Enregistrement…' : (isEdit ? 'Mettre à jour' : 'Créer le produit') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { productService } from '../services/productService'
import type { Category, Product } from '../types'

const route  = useRoute()
const router = useRouter()

const isEdit      = computed(() => !!route.params.id)
const product     = ref<Product | null>(null)
const categories  = ref<Category[]>([])
const pageLoading = ref(false)
const saving      = ref(false)
const globalError = ref('')
const errors      = reactive<Record<string, string>>({})

const form = reactive({
  name:                          '',
  sku:                           '',
  description:                   '',
  price_amount_display:          0 as number | '',
  price_currency:                'EUR',
  compare_at_price_amount_display: '' as number | '',
  cost_amount_display:           '' as number | '',
  status:                        'draft' as 'draft' | 'active' | 'archived',
  category_id:                   '',
  barcode:                       '',
  weight_kg:                     '' as number | '',
})

const currencies = [
  { code: 'EUR', label: 'Euro' },
  { code: 'USD', label: 'Dollar US' },
  { code: 'GBP', label: 'Livre Sterling' },
  { code: 'XOF', label: 'Franc CFA (UEMOA)' },
  { code: 'XAF', label: 'Franc CFA (CEMAC)' },
  { code: 'MAD', label: 'Dirham Marocain' },
  { code: 'DZD', label: 'Dinar Algérien' },
  { code: 'TND', label: 'Dinar Tunisien' },
  { code: 'GHS', label: 'Cedi Ghanéen' },
]

const statuses = [
  { value: 'active',   label: 'Actif',      hint: 'Visible et disponible à la vente.' },
  { value: 'draft',    label: 'Brouillon',  hint: 'Invisible, en cours de création.' },
  { value: 'archived', label: 'Archivé',    hint: 'Masqué, non modifiable facilement.' },
]

const margin = computed<number | null>(() => {
  const price = Number(form.price_amount_display)
  const cost  = Number(form.cost_amount_display)
  if (!price || !cost) return null
  return ((price - cost) / price) * 100
})

function clearError(field: string) {
  delete errors[field]
  globalError.value = ''
}

function toApiCents(val: number | ''): number | undefined {
  if (val === '' || val === null) return undefined
  return Math.round(Number(val) * 100)
}

function validate(): boolean {
  let valid = true
  if (!form.name.trim()) { errors.name = 'Le nom est requis'; valid = false }
  if (form.price_amount_display === '' || Number(form.price_amount_display) < 0) {
    errors.price_amount = 'Le prix est requis'; valid = false
  }
  return valid
}

async function handleSubmit() {
  if (!validate()) return
  saving.value      = true
  globalError.value = ''

  const payload = {
    name:                    form.name,
    sku:                     form.sku || undefined,
    description:             form.description || undefined,
    price_amount:            toApiCents(form.price_amount_display)!,
    price_currency:          form.price_currency,
    compare_at_price_amount: toApiCents(form.compare_at_price_amount_display),
    cost_amount:             toApiCents(form.cost_amount_display),
    status:                  form.status,
    category_id:             form.category_id || undefined,
    barcode:                 form.barcode || undefined,
    weight_kg:               form.weight_kg === '' ? undefined : Number(form.weight_kg),
  }

  try {
    if (isEdit.value) {
      await productService.update(route.params.id as string, payload)
    } else {
      await productService.create(payload)
    }
    router.push('/catalog')
  } catch (err: any) {
    const status = err?.response?.status
    if (status === 422) {
      const apiErrors = err.response?.data?.errors ?? {}
      Object.entries(apiErrors).forEach(([field, msgs]: [string, any]) => {
        errors[field] = msgs[0]
      })
    } else {
      globalError.value = 'Une erreur est survenue. Réessayez.'
    }
  } finally {
    saving.value = false
  }
}

async function loadProduct() {
  if (!isEdit.value) return
  pageLoading.value = true
  try {
    product.value = await productService.get(route.params.id as string)
    const p = product.value
    form.name                             = p.name
    form.sku                              = p.sku
    form.description                      = p.description ?? ''
    form.price_amount_display             = p.price.amount / 100
    form.price_currency                   = p.price.currency
    form.compare_at_price_amount_display  = p.compare_at_price ? p.compare_at_price.amount / 100 : ''
    form.status                           = p.status
    form.category_id                      = p.category?.id ?? ''
    form.barcode                          = p.barcode ?? ''
    form.weight_kg                        = p.weight_kg ?? ''
  } catch {
    globalError.value = 'Produit introuvable.'
  } finally {
    pageLoading.value = false
  }
}

onMounted(async () => {
  categories.value = await productService.categories.list().catch(() => [])
  await loadProduct()
})
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }
.required { color: var(--color-error); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }

.form-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
  align-items: flex-start;
}
@media (max-width: 900px) { .form-layout { grid-template-columns: 1fr; } }

.card-section-title {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--gray-100);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.input-currency-wrap { position: relative; }
.currency-badge {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--gray-400);
  pointer-events: none;
}

.margin-display {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-sm);
  padding: 0.5rem 0.75rem;
  background: var(--gray-50);
  border-radius: var(--radius-md);
  margin-top: 0.5rem;
}
.margin-label    { color: var(--gray-500); }
.margin-positive { color: var(--brand-primary-dark); font-weight: 600; }
.margin-negative { color: var(--color-error); font-weight: 600; }

.status-radios { display: flex; flex-direction: column; gap: 0.5rem; }
.status-radio {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: border-color 0.12s, background 0.12s;
}
.status-radio.active { border-color: var(--brand-primary); background: var(--brand-primary-bg); }
.status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.dot-active   { background: var(--brand-primary); }
.dot-draft    { background: var(--gray-400); }
.dot-archived { background: var(--color-warning); }
.status-radio-label { font-size: var(--text-sm); font-weight: 500; color: var(--gray-900); }
.status-radio-hint  { font-size: var(--text-xs); color: var(--gray-400); margin-top: 1px; }

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}
</style>
