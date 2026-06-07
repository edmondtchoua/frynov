<template>
  <div>
    <SalesTabNav />
    <div class="page-header">
      <div>
        <h2>Paiements</h2>
        <p class="page-subtitle">{{ meta.total ?? '—' }} enregistrement{{ meta.total !== 1 ? 's' : '' }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        Nouveau paiement
      </button>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filters.method" class="form-input filter-select" @change="load">
        <option value="">Tous les moyens</option>
        <option value="cash">Espèces</option>
        <option value="mobile_money">Mobile Money</option>
        <option value="card">Carte</option>
        <option value="transfer">Virement</option>
        <option value="cheque">Chèque</option>
      </select>
      <!-- Site / entrepôt filter (Sprint 20 multi-sites) -->
      <select v-model="filters.warehouse_id" class="form-input filter-select" @change="load" aria-label="Filtrer par entrepôt">
        <option value="">Tous les entrepôts</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">
          {{ w.is_default ? '⭐ ' : '' }}{{ w.name }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <StateBlock v-if="loading" variant="loading" />

    <!-- Empty -->
    <StateBlock
      v-else-if="payments.length === 0"
      variant="empty"
      title="Aucun paiement"
      :message="filters.method ? 'Aucun paiement pour ce moyen de paiement.' : 'Les paiements enregistrés apparaîtront ici.'"
    />

    <!-- Table -->
    <div v-else class="card table-scroll" style="padding: 0;">
      <table class="data-table data-table--cards">
        <thead>
          <tr>
            <th>Date</th>
            <th class="hide-mobile">Commande</th>
            <th>Moyen</th>
            <th class="hide-mobile">Référence</th>
            <th style="text-align: right;">Montant</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in payments" :key="p.id">
            <td data-label="Date" style="white-space: nowrap; font-size: 0.85rem; color: var(--gray-600);">
              {{ fmtDate(p.paid_at) }}
            </td>
            <td class="hide-mobile" data-label="Commande">
              <RouterLink
                v-if="p.order_id"
                :to="`/orders/${p.order_id}`"
                style="font-family: var(--font-mono, monospace); font-size: 0.82rem; color: var(--brand-secondary);"
              >
                {{ p.order_number ?? p.order_id.slice(0, 8) + '…' }}
              </RouterLink>
              <span v-else class="text-muted">—</span>
            </td>
            <td data-label="Moyen">
              <span :class="`badge ${methodBadge(p.method)}`">{{ methodLabel(p.method) }}</span>
            </td>
            <td class="hide-mobile" data-label="Référence">
              <span v-if="p.reference" style="font-family: monospace; font-size: 0.82rem;">{{ p.reference }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td data-label="Montant" style="text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;">
              {{ fmtAmount(p.amount_cents, p.currency) }}
            </td>
            <td class="cell-actions" style="text-align: right;">
              <button
                class="btn btn-ghost btn-sm"
                style="color: #dc2626;"
                title="Annuler ce paiement"
                @click="voidPayment(p)"
              >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M4 4l6 6M10 4l-6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                <span class="hide-mobile">Annuler</span>
              </button>
            </td>
          </tr>
        </tbody>
        <!-- Total row -->
        <tfoot>
          <tr>
            <td colspan="4" style="text-align: right; font-weight: 700; padding: 12px 16px; color: var(--gray-700);">
              Total affiché
            </td>
            <td style="text-align: right; font-weight: 700; padding: 12px 16px; font-size: 1rem;">
              {{ fmtAmount(pageTotal, 'XOF') }}
            </td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">← Précédent</button>
      <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Suivant →</button>
    </div>

    <!-- Create payment modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="modal.open"
      title="Enregistrer un paiement"
      @update:model-value="(v: boolean) => { if (!v) closeModal() }"
    >
      <div style="display: flex; flex-direction: column; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Montant <span style="color:#dc2626;">*</span></label>
          <div style="display: flex; gap: 8px;">
            <input
              v-model.number="form.amount"
              type="number"
              min="0"
              step="0.01"
              class="form-input"
              style="flex: 1;"
              placeholder="0.00"
            />
            <select v-model="form.currency" class="form-input" style="width: 100px;">
              <option>XOF</option>
              <option>XAF</option>
              <option>EUR</option>
              <option>USD</option>
              <option>MAD</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Moyen de paiement <span style="color:#dc2626;">*</span></label>
          <select v-model="form.method" class="form-input">
            <option value="cash">Espèces</option>
            <option value="mobile_money">Mobile Money (Orange/Wave/MTN)</option>
            <option value="card">Carte bancaire</option>
            <option value="transfer">Virement</option>
            <option value="cheque">Chèque</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Référence transaction</label>
          <input v-model="form.reference" type="text" class="form-input" placeholder="N° reçu, TX ID…" />
        </div>

        <div class="form-group">
          <label class="form-label">Note</label>
          <textarea v-model="form.note" class="form-input" style="resize: vertical; min-height: 60px;" rows="2"></textarea>
        </div>

        <p v-if="modal.error" style="color: #dc2626; font-size: 0.875rem;">{{ modal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="closeModal">Annuler</button>
        <button
          class="btn btn-primary"
          :disabled="modal.saving || !form.amount || form.amount <= 0"
          @click="submitPayment"
        >
          <span v-if="modal.saving" class="spinner-sm"></span>
          Enregistrer
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { formatDate } from '@/shared/utils/date'
import { useUrlFilters } from '@/composables/useUrlFilters'
import { RouterLink } from 'vue-router'
import SalesTabNav from '../../orders/components/SalesTabNav.vue'
import { paymentService } from '../services/paymentService'
import { useWarehouses } from '@/composables/useWarehouses'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import type { Payment, PaymentMethod } from '../types'

const payments = ref<Payment[]>([])
const loading  = ref(false)
const meta     = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const filters  = reactive({ method: '', warehouse_id: '', page: 1 })
const { warehouses, loadWarehouses } = useWarehouses()

// Keep filters in the URL (refresh / back / shareable links) — UX-12
const { hydrate: hydrateFilters, push: pushFilters } = useUrlFilters(filters, { defaults: { page: 1 } })

const pageTotal = computed(() => payments.value.reduce((s, p) => s + p.amount_cents, 0))

// ── Modal ──────────────────────────────────────────────────────────────────────
const modal = reactive({ open: false, saving: false, error: '' })
const form  = reactive({ amount: undefined as number | undefined, currency: 'XOF', method: 'cash' as PaymentMethod, reference: '', note: '' })

// ── Data ───────────────────────────────────��───────────────────────────────────
async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: filters.page, per_page: meta.per_page }
    if (filters.method) params.method = filters.method
    if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id
    const res = await paymentService.list(params as any)
    payments.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    payments.value = []
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) { filters.page = page; load() }

async function voidPayment(p: Payment) {
  if (!confirm(`Annuler ce paiement de ${fmtAmount(p.amount_cents, p.currency)} ?`)) return
  try {
    await paymentService.void(p.id)
    load()
  } catch { /* ignore */ }
}

// ── Modal ──────────────────────────────────────────────────────────────────────
function openCreate() {
  form.amount    = undefined
  form.currency  = 'XOF'
  form.method    = 'cash'
  form.reference = ''
  form.note      = ''
  modal.error    = ''
  modal.saving   = false
  modal.open     = true
}

function closeModal() { modal.open = false }

async function submitPayment() {
  if (!form.amount || form.amount <= 0) return
  modal.saving = true
  modal.error  = ''
  try {
    await paymentService.record({
      amount_cents: Math.round(form.amount * 100),
      currency:     form.currency,
      method:       form.method,
      reference:    form.reference || undefined,
      note:         form.note || undefined,
    })
    closeModal()
    load()
  } catch (e: any) {
    modal.error = e?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    modal.saving = false
  }
}

// ── Formatters ────────────────────────────────────────────────────────────────
function methodLabel(m: PaymentMethod): string {
  return ({
    cash:         'Espèces',
    mobile_money: 'Mobile Money',
    card:         'Carte',
    transfer:     'Virement',
    cheque:       'Chèque',
  } as Record<PaymentMethod, string>)[m] ?? m
}

function methodBadge(m: PaymentMethod): string {
  return ({
    cash:         'badge-success',
    mobile_money: 'badge-blue',
    card:         'badge-blue',
    transfer:     'badge-gray',
    cheque:       'badge-gray',
  } as Record<PaymentMethod, string>)[m] ?? 'badge-gray'
}

function fmtAmount(cents: number, currency: string): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency,
    maximumFractionDigits: currency === 'XOF' || currency === 'XAF' ? 0 : 2,
  }).format(cents / 100)
}

const fmtDate = formatDate

onMounted(() => {
  hydrateFilters()                                   // restore filters from the URL…
  watch(filters, pushFilters, { deep: true })        // …then mirror changes back to it
  loadWarehouses()
  load()
})
</script>
