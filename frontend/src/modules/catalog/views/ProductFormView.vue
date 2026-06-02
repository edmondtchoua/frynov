<template>
  <div>
    <div class="page-header">
      <div>
        <h2>{{ isEdit ? 'Modifier le produit' : 'Nouveau produit' }}</h2>
        <p v-if="isEdit && product" class="page-subtitle">
          <span class="mono">{{ product.sku }}</span>
          <span v-if="product.has_variants" class="variant-count-badge">{{ product.variants?.length ?? 0 }} variante(s)</span>
        </p>
      </div>
      <RouterLink to="/catalog" class="btn btn-ghost">← Catalogue</RouterLink>
    </div>

    <div v-if="pageLoading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <form v-else @submit.prevent="handleSubmit" novalidate>
      <div class="form-layout">

        <!-- ── Main column ──────────────────────────────────────── -->
        <div class="form-main">

          <!-- General info -->
          <div class="card">
            <h3 class="card-title">Informations générales</h3>
            <div class="form-group">
              <label class="form-label">Nom du produit <span class="req">*</span></label>
              <input v-model="form.name" type="text" class="form-input" :class="{ error: errors.name }"
                     placeholder="Ex : Boubou bazin brodé" @input="clearError('name')" />
              <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
            </div>
            <!-- Toggle identifiers mode (managers only) -->
            <div v-if="isManagerOrAbove" class="form-group" style="margin-bottom:0.5rem">
              <button type="button" class="btn btn-ghost btn-sm"
                      @click="showManualIdentifiers = !showManualIdentifiers">
                {{ showManualIdentifiers ? 'Identifiants automatiques' : 'Definir manuellement' }}
              </button>
            </div>

            <!-- SKU and internal barcode -->
            <div v-if="!showManualIdentifiers && !isEdit" class="form-row2">
              <div class="form-group">
                <label class="form-label">SKU</label>
                <input type="text" class="form-input mono" readonly
                       placeholder="(genere automatiquement)" />
              </div>
              <div class="form-group">
                <label class="form-label">Code-barres interne</label>
                <input type="text" class="form-input mono" readonly
                       placeholder="(genere automatiquement)" />
              </div>
            </div>
            <p v-if="!showManualIdentifiers && !isEdit" class="form-hint-auto">
              SKU et code-barres generes automatiquement a l'enregistrement
            </p>

            <!-- Editable SKU / internal barcode when manual mode or edit mode -->
            <div v-if="showManualIdentifiers || isEdit" class="form-row2">
              <div class="form-group">
                <label class="form-label">SKU</label>
                <input v-model="form.sku" type="text" class="form-input mono"
                       :readonly="isEdit"
                       :placeholder="isEdit ? '' : 'VET-0001'" />
                <span v-if="isEdit" class="form-hint-readonly">Non modifiable apres creation</span>
              </div>
              <div class="form-group">
                <label class="form-label">Préfixe SKU <span class="hint">(auto-gen)</span></label>
                <input v-model="form.sku_prefix" type="text" class="form-input mono"
                       placeholder="VET" maxlength="5" style="text-transform:uppercase" />
              </div>
            </div>
            <div v-if="showManualIdentifiers && !isEdit" class="form-group">
              <label class="form-label">Code-barres interne</label>
              <input v-model="form.internal_barcode" type="text" class="form-input mono"
                     placeholder="BC-0001" />
            </div>
            <div v-if="isEdit" class="form-group">
              <label class="form-label">Code-barres interne</label>
              <input :value="form.internal_barcode || form.barcode" type="text"
                     class="form-input mono" readonly />
              <span class="form-hint-readonly">Non modifiable apres creation</span>
            </div>

            <!-- GTIN (always visible, always editable) -->
            <div class="form-group">
              <label class="form-label">GTIN / EAN / UPC officiel</label>
              <input v-model="form.gtin" type="text" class="form-input mono"
                     placeholder="3700123456789" />
              <p class="form-hint-gtin">
                Uniquement si le produit possede un code officiel GS1 (EAN-13, UPC-A...). Ne jamais saisir un code fictif.
              </p>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" class="form-input" rows="3"
                        placeholder="Description du produit…"></textarea>
            </div>
          </div>

          <!-- Pricing -->
          <div class="card">
            <h3 class="card-title">Prix de base</h3>
            <div class="form-row2">
              <div class="form-group" style="flex:2">
                <label class="form-label">Prix de vente <span class="req">*</span></label>
                <div class="input-adorn-right">
                  <input v-model.number="form.price_display" type="number" step="1" min="0"
                         class="form-input" :class="{ error: errors.price_amount }"
                         placeholder="0" @input="clearError('price_amount')" />
                  <span class="adorn-text">{{ form.price_currency }}</span>
                </div>
                <span v-if="errors.price_amount" class="form-error">{{ errors.price_amount }}</span>
              </div>
              <div class="form-group">
                <label class="form-label">Devise</label>
                <select v-model="form.price_currency" class="form-input">
                  <option v-for="c in CURRENCIES" :key="c.code" :value="c.code">{{ c.code }} — {{ c.label }}</option>
                </select>
              </div>
            </div>
            <div class="form-row2">
              <div class="form-group">
                <label class="form-label">Prix barré <span class="hint">(optionnel)</span></label>
                <input v-model.number="form.compare_display" type="number" step="1" min="0"
                       class="form-input" placeholder="0" />
              </div>
              <div class="form-group">
                <label class="form-label">Coût d'achat <span class="hint">(CMUP)</span></label>
                <input v-model.number="form.cost_display" type="number" step="1" min="0"
                       class="form-input" placeholder="0" />
              </div>
            </div>
            <div v-if="margin !== null" class="margin-info" :class="margin > 0 ? 'margin-ok' : 'margin-bad'">
              Marge estimée : <strong>{{ margin.toFixed(1) }}%</strong>
            </div>

            <!-- Stock initial — uniquement à la création ET sans variantes -->
            <div v-if="!isEdit && !form.has_variants" class="form-group" style="margin-top:1rem;margin-bottom:0;padding-top:1rem;border-top:1px solid var(--gray-100)">
              <label class="form-label">
                Quantité en stock initiale
                <span class="hint">(optionnel — peut être ajoutée plus tard depuis l'inventaire)</span>
              </label>
              <input v-model.number="initialQty" type="number" min="0" class="form-input"
                     placeholder="0" style="width:120px" />
            </div>
          </div>

          <!-- Variants -->
          <div class="card">
            <div class="variants-header">
              <div>
                <h3 class="card-title" style="margin-bottom:0.25rem">Variantes</h3>
                <p class="card-hint">Activez si ce produit existe en plusieurs tailles, couleurs, etc.</p>
              </div>
              <label class="toggle-wrap">
                <input v-model="form.has_variants" type="checkbox" class="sr-only" />
                <span class="toggle-track">
                  <span class="toggle-thumb"></span>
                </span>
                <span class="toggle-lbl">{{ form.has_variants ? 'Activé' : 'Désactivé' }}</span>
              </label>
            </div>

            <Transition name="slide-up">
              <div v-if="form.has_variants" class="variants-body">

                <!-- ══ N-AXIS BUILDER (création et édition) ══════════════════ -->
                <div class="axes-builder">
                  <div class="axes-builder-header">
                    <span class="axes-builder-title">Axes de variation</span>
                    <span class="axes-hint">Ajoutez autant d'axes que nécessaire (Couleur, RAM, ROM, Taille…)</span>
                  </div>

                  <!-- Axis rows -->
                  <div v-for="(axis, i) in variantAxes" :key="i" class="axis-row">
                    <!-- Axis name with suggestions -->
                    <div class="axis-name-wrap">
                      <input
                        v-model="axis.name"
                        class="form-input axis-name-input"
                        placeholder="Nom de l'axe (ex: Couleur)"
                        list="axis-suggestions"
                      />
                      <datalist id="axis-suggestions">
                        <option v-for="s in AXIS_SUGGESTIONS" :key="s" :value="s" />
                      </datalist>
                    </div>

                    <!-- Values chips -->
                    <div class="axis-values-wrap">
                      <div class="axis-values">
                        <span v-for="(val, j) in axis.values" :key="j" class="axis-value-chip">
                          {{ val }}
                          <button type="button" class="chip-remove" @click="removeAxisValue(i, j)">×</button>
                        </span>
                        <input
                          v-model="axis.newValue"
                          class="form-input axis-value-input"
                          :placeholder="axis.name ? axis.name + '…' : 'Valeur'"
                          @keyup.enter="addAxisValue(i)"
                          @blur="addAxisValue(i)"
                        />
                      </div>
                    </div>

                    <!-- Remove axis -->
                    <button
                      type="button"
                      class="btn btn-ghost btn-sm axis-remove-btn"
                      @click="removeAxis(i)"
                      :disabled="variantAxes.length <= 1"
                      title="Supprimer cet axe"
                    >✕</button>
                  </div>

                  <!-- Add axis -->
                  <button type="button" class="btn btn-ghost btn-sm add-axis-btn" @click="addAxis">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                      <path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Ajouter un axe
                  </button>

                  <!-- Preview / combinaison count -->
                  <div v-if="combinationCount > 0" class="combo-preview">
                    <div class="combo-info">
                      <span class="combo-count">{{ combinationCount }} déclinaison(s)</span>
                      <span class="combo-formula">
                        = {{ variantAxes.filter(a => a.values.length).map(a => a.values.length).join(' × ') }}
                      </span>
                      <span class="combo-example">
                        Ex : {{ comboExample }}
                      </span>
                    </div>
                    <p v-if="isEdit" class="combo-note">
                      💡 Les combinaisons seront générées via le bouton ci-dessous
                      (les déclinaisons existantes sont conservées).
                    </p>
                    <p v-else class="combo-note">
                      💡 Les déclinaisons seront générées automatiquement à l'enregistrement du produit.
                    </p>
                    <button
                      v-if="isEdit"
                      type="button"
                      class="btn btn-primary btn-sm"
                      :disabled="generatingVariants"
                      @click="generateVariantsNow"
                    >
                      <span v-if="generatingVariants" class="spinner-sm spinner-white"></span>
                      {{ generatingVariants ? 'Génération…' : 'Générer les déclinaisons' }}
                    </button>
                    <div v-if="generateResult" class="generate-result" :class="generateResult.ok ? 'ok' : 'warn'">
                      {{ generateResult.message }}
                    </div>
                  </div>

                  <p v-else-if="!axesHaveValues" class="variant-hint">
                    Ajoutez des valeurs à chaque axe pour calculer les combinaisons.
                    <br><small>Ex : Axe "Couleur" → valeurs "Rouge", "Bleu", "Noir"</small>
                  </p>
                </div>

                <!-- ══ TABLE DES DÉCLINAISONS EXISTANTES (mode édition) ═══ -->
                <div v-if="isEdit && variants.length" class="variant-table-wrap" style="margin-top:16px">
                  <div class="variant-table-title">
                    Déclinaisons existantes ({{ variants.filter(v => !v._deleted).length }})
                  </div>
                  <table class="variant-table">
                    <thead>
                      <tr>
                        <th>Déclinaison</th>
                        <th>SKU</th>
                        <th>Prix ({{ form.price_currency }})</th>
                        <th>Actif</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(v, i) in variants" :key="v._key" :class="{ 'row-deleted': v._deleted }">
                        <td>
                          <input v-model="v.label" type="text" class="var-input" :disabled="v._deleted" />
                        </td>
                        <td>
                          <input v-model="v.sku" type="text" class="var-input mono" placeholder="auto" :disabled="v._deleted" />
                        </td>
                        <td>
                          <input v-model.number="v.price_display" type="number" step="1" min="0"
                                 class="var-input" :placeholder="String(form.price_display || 0)" :disabled="v._deleted" />
                        </td>
                        <td>
                          <input v-model="v.is_active" type="checkbox" :disabled="v._deleted" />
                        </td>
                        <td>
                          <button v-if="!v._deleted" type="button" class="var-delete-btn"
                                  @click="markDeleteVariant(i)" title="Supprimer">✕</button>
                          <button v-else type="button" class="var-restore-btn" @click="variants[i]._deleted = false">↩</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              </div>
            </Transition>
          </div>

        </div>

        <!-- ── Side column ──────────────────────────────────────── -->
        <div class="form-side">

          <div class="card">
            <h3 class="card-title">Statut</h3>
            <div class="status-list">
              <label v-for="s in STATUSES" :key="s.value"
                     class="status-opt" :class="{ active: form.status === s.value }">
                <input v-model="form.status" type="radio" :value="s.value" class="sr-only" />
                <span class="status-dot" :class="`dot-${s.value}`"></span>
                <div>
                  <div class="status-opt-name">{{ s.label }}</div>
                  <div class="status-opt-hint">{{ s.hint }}</div>
                </div>
              </label>
            </div>
          </div>

          <div class="card">
            <h3 class="card-title">Catégorie</h3>
            <select v-model="form.category_id" class="form-input" style="margin-bottom:0">
              <option value="">Aucune catégorie</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div class="card" v-if="isEdit && product">
            <h3 class="card-title">Étiquettes</h3>
            <div class="label-actions">
              <button type="button" class="btn btn-secondary btn-sm" style="width:100%"
                      @click="printLabel('thermal')">
                🖨 Étiquette thermique
              </button>
              <button type="button" class="btn btn-secondary btn-sm" style="width:100%"
                      @click="printLabel('a4sheet')">
                📄 Planche A4
              </button>
            </div>
          </div>

          <div class="card">
            <h3 class="card-title">Expédition</h3>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Poids (kg)</label>
              <input v-model.number="form.weight_kg" type="number" step="0.01" min="0"
                     class="form-input" placeholder="0.00" />
            </div>
          </div>

        </div>
      </div>

      <div v-if="globalError" class="alert alert-error" style="margin-top:1rem">
        {{ globalError }}
      </div>

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
import client from '@/api/client'
import type { Category, Product } from '../types'
import { usePermission } from '@/composables/usePermission'

const route  = useRoute()
const router = useRouter()
const { isManagerOrAbove } = usePermission()
const showManualIdentifiers = ref(false)

const isEdit      = computed(() => !!route.params.id)
const product     = ref<Product | null>(null)
const categories  = ref<Category[]>([])
const pageLoading = ref(false)
const saving      = ref(false)
const globalError = ref('')
const errors      = reactive<Record<string, string>>({})

// ── Form state ─────────────────────────────────────────────────────────────
const form = reactive({
  name:           '',
  sku:            '',
  sku_prefix:     '',
  description:    '',
  price_display:  0 as number | '',
  price_currency: 'XOF',
  compare_display:'' as number | '',
  cost_display:   '' as number | '',
  status:         'draft' as 'draft' | 'active' | 'archived',
  category_id:    '',
  barcode:        '',
  internal_barcode: '',
  gtin:           '',
  barcode_type:   'INTERNAL' as 'INTERNAL' | 'GTIN',
  weight_kg:      '' as number | '',
  has_variants:   false,
})

// ── Stock initial (produits sans variantes) ────────────────────────────────
const initialQty = ref(0)

// ── Variant state ──────────────────────────────────────────────────────────
interface VariantRow {
  _key:          string   // temp key for v-for
  _id:           string   // '' = new
  _deleted:      boolean
  label:         string   // e.g. "M", "Rouge"
  sku:           string
  price_display: number | ''
  barcode:       string
  is_active:     boolean
  initial_qty:   number   // stock initial à créer (0 = aucun move-in)
}

const variants = ref<VariantRow[]>([])
let _vKey = 0
function makeKey() { return `vk-${++_vKey}` }

function markDeleteVariant(i: number) {
  const v = variants.value[i]
  if (!v._id) { variants.value.splice(i, 1) }
  else { v._deleted = true }
}

// ── N-AXIS BUILDER (Sprint 17) ──────────────────────────────────────────────
interface VariantAxis { name: string; values: string[]; newValue: string }

const AXIS_SUGGESTIONS = ['Taille', 'Couleur', 'Matière', 'Volume', 'RAM', 'ROM', 'Stockage', 'Puissance', 'Format', 'Modèle']

const variantAxes = ref<VariantAxis[]>([
  { name: 'Taille', values: [], newValue: '' },
])

const generatingVariants = ref(false)
const generateResult = ref<{ ok: boolean; message: string } | null>(null)

const axesHaveValues = computed(() =>
  variantAxes.value.some(a => a.name && a.values.length > 0)
)

const combinationCount = computed(() => {
  const counts = variantAxes.value
    .filter(a => a.name.trim() && a.values.length > 0)
    .map(a => a.values.length)
  return counts.length ? counts.reduce((a, b) => a * b, 1) : 0
})

const comboExample = computed(() => {
  const first = variantAxes.value
    .filter(a => a.name.trim() && a.values.length > 0)
    .map(a => a.values[0])
  return first.join(' / ')
})

function addAxis() {
  variantAxes.value.push({ name: '', values: [], newValue: '' })
}

function removeAxis(i: number) {
  if (variantAxes.value.length > 1) variantAxes.value.splice(i, 1)
}

function addAxisValue(i: number) {
  const axis = variantAxes.value[i]
  const v = axis.newValue.trim()
  if (v && !axis.values.includes(v)) axis.values.push(v)
  axis.newValue = ''
}

function removeAxisValue(i: number, j: number) {
  variantAxes.value[i].values.splice(j, 1)
}

async function generateVariantsNow() {
  if (!product.value?.id) return
  generatingVariants.value = true
  generateResult.value = null
  try {
    const axes = variantAxes.value
      .filter(a => a.name.trim() && a.values.length > 0)
      .map(a => ({ name: a.name.trim(), values: a.values }))
    const basePrice = typeof form.price_display === 'number' ? form.price_display * 100 : 0
    const r = await productService.generateVariants(product.value.id, {
      axes,
      base_price:    basePrice || undefined,
      base_currency: form.price_currency,
    })
    generateResult.value = { ok: true, message: r.message ?? `${r.created} créée(s), ${r.skipped} ignorée(s).` }
    await loadVariants()
  } catch {
    generateResult.value = { ok: false, message: 'Erreur lors de la génération.' }
  } finally {
    generatingVariants.value = false
  }
}

// ── Label printing (opens backend HTML in new tab) ─────────────────────────
async function printLabel(format: 'thermal' | 'a4sheet') {
  if (!product.value) return
  const url = productService.getLabelUrl(product.value.id, { format, price: true, qr: true })
  const token = localStorage.getItem('auth_token') ?? ''
  const resp = await fetch(url, { headers: { Authorization: `Bearer ${token}` } })
  const html  = await resp.text()
  const win   = window.open('', '_blank')
  if (win) { win.document.write(html); win.document.close(); setTimeout(() => win.print(), 400) }
}

// ── Constants ──────────────────────────────────────────────────────────────
const CURRENCIES = [
  { code: 'XOF', label: 'Franc CFA (UEMOA)' },
  { code: 'XAF', label: 'Franc CFA (CEMAC)' },
  { code: 'GHS', label: 'Cedi Ghanéen' },
  { code: 'NGN', label: 'Naira Nigérian' },
  { code: 'MAD', label: 'Dirham Marocain' },
  { code: 'EUR', label: 'Euro' },
  { code: 'USD', label: 'Dollar US' },
]

const STATUSES = [
  { value: 'active',   label: 'Actif',     hint: 'Visible et en vente.' },
  { value: 'draft',    label: 'Brouillon', hint: 'Invisible, en cours de création.' },
  { value: 'archived', label: 'Archivé',   hint: 'Masqué, non disponible.' },
]

// ── Computed ───────────────────────────────────────────────────────────────
const margin = computed<number | null>(() => {
  const p = Number(form.price_display)
  const c = Number(form.cost_display)
  if (!p || !c) return null
  return ((p - c) / p) * 100
})

// ── Helpers ────────────────────────────────────────────────────────────────
function clearError(k: string) { delete errors[k]; globalError.value = '' }
function toCents(v: number | ''): number | undefined {
  if (v === '') return undefined
  return Math.round(Number(v) * 100)
}

// ── Submit ─────────────────────────────────────────────────────────────────
function validate(): boolean {
  let ok = true
  if (!form.name.trim()) { errors.name = 'Le nom est requis'; ok = false }
  if (form.price_display === '' || Number(form.price_display) < 0) {
    errors.price_amount = 'Le prix est requis'; ok = false
  }
  return ok
}

async function handleSubmit() {
  if (!validate()) return
  saving.value = true
  globalError.value = ''

  try {
    const payload: any = {
      name:                    form.name,
      sku:                     showManualIdentifiers.value ? (form.sku || undefined) : undefined,
      sku_prefix:              form.sku_prefix.toUpperCase() || undefined,
      description:             form.description || undefined,
      price_amount:            toCents(form.price_display)!,
      price_currency:          form.price_currency,
      compare_at_price_amount: toCents(form.compare_display),
      cost_amount:             toCents(form.cost_display),
      status:                  form.status,
      category_id:             form.category_id || undefined,
      barcode:                 form.barcode || undefined,
      weight_kg:               form.weight_kg === '' ? undefined : Number(form.weight_kg),
      has_variants:            form.has_variants,
    }

    if (showManualIdentifiers.value) {
      if (form.internal_barcode) payload.internal_barcode = form.internal_barcode
      if (form.barcode_type) payload.barcode_type = form.barcode_type
    }
    if (form.gtin) payload.gtin = form.gtin

    let savedProduct: Product
    if (isEdit.value) {
      savedProduct = await productService.update(route.params.id as string, payload)
    } else {
      savedProduct = await productService.create(payload)
    }

    // ── Sync variants ────────────────────────────────────────────────────
    if (form.has_variants) {
      const currency = form.price_currency

      // Sprint 17 N-AXIS: generate via cartesian product if axes are defined
      const axes = variantAxes.value.filter(a => a.name.trim() && a.values.length > 0)
      if (axes.length > 0) {
        const basePrice = typeof form.price_display === 'number' ? toCents(form.price_display) : 0
        await productService.generateVariants(savedProduct.id, {
          axes: axes.map(a => ({ name: a.name.trim(), values: a.values })),
          base_price:    basePrice || undefined,
          base_currency: currency,
        })
      }

      // Also process any manually edited rows (existing variants in edit mode)
      for (const v of variants.value) {
        if (v._deleted && v._id) {
          await productService.deleteVariant(savedProduct.id, v._id)
        } else if (!v._deleted && v._id) {
          await productService.updateVariant(savedProduct.id, v._id, {
            name:           v.label,
            label:          v.label,
            price_amount:   v.price_display !== '' ? toCents(v.price_display as number) : undefined,
            price_currency: currency,
            barcode:        v.barcode || null,
            is_active:      v.is_active,
          })
        }
      }
    } else if (!isEdit.value) {
      // No variants: initialize product stock if initial quantity provided
      if (initialQty.value > 0) {
        await client.post(`/api/inventory/stock/${savedProduct.id}/move-in`, {
          quantity: initialQty.value,
          reason:   'delivery',
          note:     'Stock initial à la création du produit',
        })
      }
    }

    router.push('/catalog')
  } catch (err: any) {
    if (err?.response?.status === 422) {
      const apiErrors = err.response?.data?.errors ?? {}
      Object.entries(apiErrors).forEach(([k, msgs]: any) => { errors[k] = msgs[0] })
    } else {
      globalError.value = err?.response?.data?.message ?? 'Une erreur est survenue.'
    }
  } finally {
    saving.value = false
  }
}

// ── Load product (edit mode) ───────────────────────────────────────────────
async function loadProduct() {
  if (!isEdit.value) return
  pageLoading.value = true
  try {
    const p = await productService.get(route.params.id as string)
    product.value = p

    form.name              = p.name
    form.sku               = p.sku
    form.description       = p.description ?? ''
    form.price_display     = p.price.amount / 100
    form.price_currency    = p.price.currency
    form.compare_display   = p.compare_at_price ? p.compare_at_price.amount / 100 : ''
    form.status            = p.status
    form.category_id       = p.category?.id ?? ''
    form.barcode           = p.barcode ?? ''
    form.internal_barcode  = (p as any).internal_barcode ?? ''
    form.gtin              = (p as any).gtin ?? ''
    form.barcode_type      = (p as any).barcode_type ?? 'INTERNAL'
    form.weight_kg         = p.weight_kg ?? ''
    form.has_variants      = p.has_variants

    if (p.has_variants && p.variants?.length) {
      const firstAttr = p.variants[0]?.attributes ? Object.keys(p.variants[0].attributes)[0] : 'Taille'
      attrAxis.value = firstAttr || 'Taille'
      variants.value = p.variants.map(v => ({
        _key:          makeKey(),
        _id:           v.id,
        _deleted:      false,
        label:         v.attributes?.[firstAttr] ?? v.name ?? v.sku,
        sku:           v.sku,
        price_display: v.price ? v.price.amount / 100 : '',
        barcode:       (v as any).barcode ?? '',
        is_active:     (v as any).is_active ?? true,
        initial_qty:   0,  // not editable on existing variants
      }))
    }
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
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.5rem; }
.mono { font-family: ui-monospace, monospace; font-size: 0.8125rem; }
.req  { color: var(--color-error); }
.hint { font-size: var(--text-xs); font-weight: 400; color: var(--gray-400); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }

.variant-count-badge {
  font-size: var(--text-xs);
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-weight: 600;
}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.form-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 1.5rem;
  align-items: flex-start;
}
@media (max-width: 900px) { .form-layout { grid-template-columns: 1fr; } }

.card-title {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--gray-100);
}
.card-hint { font-size: var(--text-xs); color: var(--gray-400); margin: 0; }

.form-row2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 600px) { .form-row2 { grid-template-columns: 1fr; } }

/* Price input with currency adornment */
.input-adorn-right { position: relative; }
.input-adorn-right .form-input { padding-right: 3.5rem; }
.adorn-text {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: var(--text-xs);
  font-weight: 700;
  color: var(--gray-400);
  pointer-events: none;
}

.margin-info {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: var(--text-sm);
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  margin-top: 0.5rem;
}
.margin-ok  { background: var(--brand-primary-bg);  color: var(--brand-primary-dark); }
.margin-bad { background: var(--color-error-bg);    color: #991b1b; }

/* ── Variants section ────────────────────────────────────────────────────── */
.variants-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 1rem;
  margin-bottom: 0;
  border-bottom: 1px solid var(--gray-100);
}

.toggle-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  flex-shrink: 0;
}
.toggle-track {
  width: 40px;
  height: 22px;
  border-radius: 11px;
  background: var(--gray-300);
  position: relative;
  transition: background 0.2s;
}
.sr-only:checked + .toggle-track { background: var(--brand-primary); }
.toggle-thumb {
  position: absolute;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: white;
  top: 2px;
  left: 2px;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.sr-only:checked + .toggle-track .toggle-thumb { transform: translateX(18px); }
.toggle-lbl { font-size: var(--text-sm); color: var(--gray-600); white-space: nowrap; }

.variants-body { padding-top: 1rem; display: flex; flex-direction: column; gap: 1rem; }

.attr-row { display: flex; flex-direction: column; gap: 0.375rem; }
.attr-chips { display: flex; gap: 0.375rem; flex-wrap: wrap; }
.attr-chip {
  padding: 0.25rem 0.75rem;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 600;
  background: white;
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.12s;
}
.attr-chip.active { border-color: var(--brand-primary); background: var(--brand-primary-bg); color: var(--brand-primary-dark); }
.attr-chip:hover:not(.active) { border-color: var(--gray-400); }

.variant-table-wrap { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-md); }
.variant-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
.variant-table th {
  background: var(--gray-50);
  padding: 0.5rem 0.75rem;
  text-align: left;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
  border-bottom: 1px solid var(--gray-200);
}
.variant-table td { padding: 0.5rem 0.5rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
.variant-table tr:last-child td { border-bottom: none; }
.row-deleted td { opacity: 0.4; }

.var-input {
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-sm);
  padding: 0.375rem 0.5rem;
  font-size: var(--text-sm);
  outline: none;
  width: 100%;
  min-width: 70px;
  background: white;
  transition: border-color 0.12s;
}
.var-input:focus { border-color: var(--brand-primary); }
.var-input.mono  { font-family: ui-monospace, monospace; }

.var-delete-btn {
  background: none;
  border: none;
  color: var(--color-error);
  cursor: pointer;
  font-size: 0.875rem;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  opacity: 0.7;
}
.var-delete-btn:hover { opacity: 1; background: var(--color-error-bg); }
.var-restore-btn { background: none; border: none; cursor: pointer; color: var(--brand-primary); font-size: 1rem; padding: 0 0.5rem; }

.add-variant-row { display: flex; gap: 0.5rem; align-items: center; }
.var-add-input { flex: 1; }
.variant-hint { font-size: var(--text-xs); color: var(--gray-400); margin: 0; }

/* ── Status selector ─────────────────────────────────────────────────────── */
.status-list { display: flex; flex-direction: column; gap: 0.5rem; }
.status-opt {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all 0.12s;
}
.status-opt.active { border-color: var(--brand-primary); background: var(--brand-primary-bg); }
.status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.dot-active   { background: var(--brand-primary); }
.dot-draft    { background: var(--gray-400); }
.dot-archived { background: var(--color-warning); }
.status-opt-name { font-size: var(--text-sm); font-weight: 600; color: var(--gray-900); }
.status-opt-hint { font-size: var(--text-xs); color: var(--gray-400); }

/* ── Label actions ───────────────────────────────────────────────────────── */
.label-actions { display: flex; flex-direction: column; gap: 0.5rem; }

/* ── Form footer ─────────────────────────────────────────────────────────── */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

/* ── Identifier helper texts ─────────────────────────────────────────────── */
.form-hint-auto {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin: -0.5rem 0 0.75rem;
  font-style: italic;
}
.form-hint-readonly {
  display: block;
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin-top: 0.25rem;
}
.form-hint-gtin {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin: 0.25rem 0 0;
  line-height: 1.4;
}
</style>
