<template>
  <div v-if="modelValue" class="drawer-overlay" @click.self="close">
    <div class="drawer-panel drawer-panel--wide">
      <div class="drawer-head">
        <h3 class="drawer-title">{{ $t('catalog.stockGrid.title') }}</h3>
        <button class="drawer-close" :aria-label="$t('common.cancel')" @click="close">×</button>
      </div>

      <p class="drawer-help">{{ $t('catalog.stockGrid.help') }}</p>

      <div v-if="loading" class="grid-state">{{ $t('common.loading') }}</div>
      <div v-else-if="error" class="grid-state grid-state--err">{{ error }}</div>
      <div v-else-if="matrix && matrix.warehouses.length === 0" class="grid-state">
        {{ $t('catalog.stockGrid.noWarehouses') }}
      </div>

      <template v-else-if="matrix">
        <div class="form-group">
          <label class="form-label">{{ $t('catalog.stockGrid.reference') }} <span class="hint">{{ $t('inventory.delivery.optional') }}</span></label>
          <input v-model="reference" class="form-input" :placeholder="$t('catalog.stockGrid.refPlaceholder')" />
        </div>

        <div class="grid-scroll">
          <table class="stock-grid">
            <thead>
              <tr>
                <th class="col-variant">{{ $t('catalog.stockGrid.colVariant') }}</th>
                <th v-for="wh in matrix.warehouses" :key="wh.id" class="col-wh">
                  {{ wh.name }}
                  <span v-if="wh.is_default" class="wh-default">★</span>
                </th>
                <th class="col-cost">{{ $t('catalog.stockGrid.colUnitCost') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in matrix.rows" :key="rowKey(row)">
                <th class="col-variant" scope="row">
                  <span class="row-label">{{ row.label }}</span>
                  <code class="row-sku">{{ row.sku }}</code>
                </th>
                <td v-for="wh in matrix.warehouses" :key="wh.id" class="cell">
                  <input
                    type="number" min="0" step="1"
                    class="cell-input"
                    :value="qty[rowKey(row)]?.[wh.id] ?? 0"
                    :aria-label="`${row.label} · ${wh.name}`"
                    @input="setQty(rowKey(row), wh.id, ($event.target as HTMLInputElement).value)"
                  />
                  <span class="cell-current">{{ $t('catalog.stockGrid.inStock', { n: row.cells[wh.id]?.quantity ?? 0 }) }}</span>
                </td>
                <td class="cell cell-cost">
                  <input
                    type="number" min="0" step="any"
                    class="cell-input"
                    :value="unitCost[rowKey(row)] ?? ''"
                    :placeholder="'0'"
                    :aria-label="`${$t('catalog.stockGrid.colUnitCost')} · ${row.label}`"
                    @input="setCost(rowKey(row), ($event.target as HTMLInputElement).value)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="grid-footer-summary">
          {{ $t('catalog.stockGrid.totalToAdd', { n: totalToAdd }) }}
        </div>
      </template>

      <div v-if="submitError" class="alert alert-error">{{ submitError }}</div>

      <div class="drawer-actions">
        <button class="btn btn-ghost" @click="close">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving || totalToAdd === 0" @click="submit">
          <span v-if="saving" class="spinner-sm spinner-white"></span>
          {{ saving ? $t('common.saving') : $t('catalog.stockGrid.validate') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { inventoryService, type VariantStockMatrix, type StockMatrixRow, type DeliveryItem } from '@/modules/inventory/services/inventoryService'
import { t } from '@/i18n'

const props = defineProps<{ modelValue: boolean; productId: string }>()
const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const matrix = ref<VariantStockMatrix | null>(null)
const loading = ref(false)
const error = ref('')
const saving = ref(false)
const submitError = ref('')
const reference = ref('')

/** quantités à ajouter par ligne→entrepôt, et coût unitaire (unités majeures) par ligne. */
const qty = reactive<Record<string, Record<string, number>>>({})
const unitCost = reactive<Record<string, number>>({})

function rowKey(row: StockMatrixRow): string {
  return row.variant_id ?? 'product'
}

function setQty(rk: string, whId: string, raw: string): void {
  if (!qty[rk]) qty[rk] = {}
  const n = Math.max(0, Math.floor(Number(raw) || 0))
  qty[rk][whId] = n
}

function setCost(rk: string, raw: string): void {
  const n = Number(raw)
  unitCost[rk] = Number.isFinite(n) && n >= 0 ? n : 0
}

const totalToAdd = computed(() => {
  let total = 0
  for (const rk of Object.keys(qty)) {
    for (const whId of Object.keys(qty[rk])) total += qty[rk][whId] || 0
  }
  return total
})

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  matrix.value = null
  Object.keys(qty).forEach(k => delete qty[k])
  Object.keys(unitCost).forEach(k => delete unitCost[k])
  reference.value = ''
  submitError.value = ''
  try {
    matrix.value = await inventoryService.variantStockMatrix(props.productId)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('catalog.stockGrid.loadError')
  } finally {
    loading.value = false
  }
}

function close(): void {
  emit('update:modelValue', false)
}

async function submit(): Promise<void> {
  if (!matrix.value || totalToAdd.value === 0) return
  submitError.value = ''
  saving.value = true

  const items: DeliveryItem[] = []
  for (const row of matrix.value.rows) {
    const rk = rowKey(row)
    const costCents = Math.round((unitCost[rk] ?? 0) * 100)
    for (const wh of matrix.value.warehouses) {
      const n = qty[rk]?.[wh.id] ?? 0
      if (n > 0) {
        items.push({
          product_id: matrix.value.product_id,
          variant_id: row.variant_id,
          warehouse_id: wh.id,
          quantity: n,
          ...(costCents > 0 ? { unit_cost_cents: costCents } : {}),
        })
      }
    }
  }

  try {
    await inventoryService.receiveDelivery(items, reference.value)
    emit('saved')
    close()
  } catch (e: any) {
    submitError.value = e?.response?.data?.message ?? t('catalog.stockGrid.saveError')
  } finally {
    saving.value = false
  }
}

watch(() => props.modelValue, open => { if (open) void load() })
</script>

<style scoped>
.drawer-overlay {
  position: fixed; inset: 0; z-index: 60;
  background: rgba(15, 23, 42, 0.45);
  display: flex; justify-content: flex-end;
}
.drawer-panel {
  background: #fff; height: 100%; width: 100%;
  display: flex; flex-direction: column; gap: 1rem;
  padding: 1.5rem; overflow-y: auto;
  box-shadow: -8px 0 32px rgba(0, 0, 0, 0.12);
}
.drawer-panel--wide { max-width: 880px; }
.drawer-head { display: flex; align-items: center; justify-content: space-between; }
.drawer-title { font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--gray-900, #0f172a); }
.drawer-close { background: none; border: 0; font-size: 1.6rem; line-height: 1; cursor: pointer; color: var(--gray-400, #94a3b8); }
.drawer-help { font-size: 0.85rem; color: var(--gray-500, #64748b); margin: 0; }

.grid-state { padding: 2rem; text-align: center; color: var(--gray-400, #94a3b8); }
.grid-state--err { color: var(--color-error, #dc2626); }

.grid-scroll { overflow-x: auto; border: 1px solid var(--gray-200, #e2e8f0); border-radius: 10px; }
.stock-grid { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.stock-grid th, .stock-grid td { padding: 0.55rem 0.7rem; text-align: left; border-bottom: 1px solid var(--gray-100, #f1f5f9); }
.stock-grid thead th { background: var(--gray-50, #f8fafc); font-weight: 700; color: var(--gray-700, #334155); position: sticky; top: 0; }
.col-variant { min-width: 160px; }
.col-wh { text-align: center; min-width: 110px; }
.col-cost { min-width: 120px; }
.wh-default { color: #f59e0b; margin-left: 0.2rem; }
.row-label { display: block; font-weight: 600; color: var(--gray-800, #1e293b); }
.row-sku { font-size: 0.72rem; color: var(--gray-400, #94a3b8); }
.cell { text-align: center; vertical-align: top; }
.cell-cost { text-align: left; }
.cell-input {
  width: 84px; padding: 0.4rem 0.5rem;
  border: 1px solid var(--gray-200, #e2e8f0); border-radius: 6px;
  font: inherit; text-align: center;
}
.cell-input:focus { outline: 2px solid rgba(16,185,129,0.35); outline-offset: 1px; border-color: var(--brand-primary, #10b981); }
.cell-current { display: block; margin-top: 0.2rem; font-size: 0.68rem; color: var(--gray-400, #94a3b8); }

.grid-footer-summary { font-size: 0.875rem; font-weight: 600; color: var(--brand-primary-dark, #047857); }
.drawer-actions { display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: auto; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--gray-700, #334155); }
.form-input { width: 100%; padding: 0.5rem 0.7rem; border: 1px solid var(--gray-200, #e2e8f0); border-radius: 8px; font: inherit; }
.hint { font-weight: 400; color: var(--gray-400, #94a3b8); }
.alert-error { padding: 0.6rem 0.8rem; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 0.85rem; }
</style>
