<template>
  <div>
    <InventoryTabNav />

    <div class="page-header">
      <div>
        <h2>Ajustements de stock</h2>
        <p class="page-subtitle">Demandes d'ajustement (perte, casse, comptage…) avec validation manager.</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ Nouvelle demande</button>
    </div>

    <div v-if="error" class="empty-state"><p>{{ error }}</p><button class="btn btn-secondary" @click="load">Réessayer</button></div>

    <!-- Pending queue -->
    <section class="card" style="padding:0; overflow:hidden; margin-bottom:1.25rem;">
      <div class="section-head"><strong>En attente de validation</strong><span class="count">{{ pending.length }}</span></div>
      <div v-if="loading" class="loading-center"><span class="spinner-sm"></span> Chargement…</div>
      <p v-else-if="!pending.length" class="muted-row">Aucune demande en attente.</p>
      <div v-else class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Produit</th><th>Avant → Demandé</th><th>Δ</th><th>Motif</th><th>Note</th><th></th></tr></thead>
          <tbody>
            <tr v-for="a in pending" :key="a.id">
              <td><strong>{{ a.product?.name ?? a.product_id }}</strong><div class="muted">{{ a.product?.sku }}</div></td>
              <td>{{ a.quantity_before }} → {{ a.quantity_requested }}</td>
              <td :class="a.delta < 0 ? 'neg' : 'pos'">{{ a.delta > 0 ? '+' : '' }}{{ a.delta }}</td>
              <td>{{ reasonLabel(a.reason) }}</td>
              <td class="muted">{{ a.note || '—' }}</td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-primary" :disabled="busy" @click="approve(a)">Approuver</button>
                  <button class="btn btn-sm btn-danger" :disabled="busy" @click="openReject(a)">Rejeter</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- History -->
    <section class="card" style="padding:0; overflow:hidden;">
      <div class="section-head"><strong>Historique</strong></div>
      <p v-if="!history.length" class="muted-row">Aucun historique.</p>
      <div v-else class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Produit</th><th>Δ</th><th>Motif</th><th>Statut</th></tr></thead>
          <tbody>
            <tr v-for="a in history" :key="a.id">
              <td>{{ a.product?.name ?? a.product_id }}</td>
              <td :class="a.delta < 0 ? 'neg' : 'pos'">{{ a.delta > 0 ? '+' : '' }}{{ a.delta }}</td>
              <td>{{ reasonLabel(a.reason) }}</td>
              <td><span class="tag" :class="`tag--${a.status}`">{{ statusLabel(a.status) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Create modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="createModal.open" title="Nouvelle demande d'ajustement">
      <div v-if="createModal.error" class="form-error">{{ createModal.error }}</div>
      <label class="form-label">Article en stock *</label>
      <select v-model="form.stock_id" class="form-input" @change="onStockPick">
        <option value="">— Sélectionner —</option>
        <option v-for="s in stocks" :key="s.id" :value="s.id">
          {{ s.product?.name }}{{ s.warehouse ? ` · ${s.warehouse.name}` : '' }} (stock : {{ s.quantity }})
        </option>
      </select>
      <label class="form-label">Nouvelle quantité réelle *</label>
      <input v-model.number="form.new_quantity" type="number" min="0" class="form-input" />
      <label class="form-label">Motif *</label>
      <select v-model="form.reason" class="form-input">
        <option v-for="r in REASONS" :key="r" :value="r">{{ reasonLabel(r) }}</option>
      </select>
      <label class="form-label">Note</label>
      <textarea v-model="form.note" class="form-input" rows="2" placeholder="Précision (optionnel)"></textarea>

      <template #footer>
        <button class="btn btn-secondary" @click="createModal.open = false">Annuler</button>
        <button class="btn btn-primary" :disabled="createModal.saving || !form.stock_id" @click="submitCreate">
          {{ createModal.saving ? 'Envoi…' : 'Soumettre' }}
        </button>
      </template>
    </BaseModal>

    <!-- Reject modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="rejectModal.open" size="sm" title="Rejeter la demande">
      <label class="form-label">Motif du rejet *</label>
      <textarea v-model="rejectModal.reason" class="form-input" rows="2"></textarea>

      <template #footer>
        <button class="btn btn-secondary" @click="rejectModal.open = false">Annuler</button>
        <button class="btn btn-danger" :disabled="busy || !rejectModal.reason" @click="confirmReject">Rejeter</button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import client from '@/api/client'
import InventoryTabNav from '../components/InventoryTabNav.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { stockAdjustmentService, ADJUSTMENT_REASONS, REASON_LABELS, type StockAdjustment } from '../services/stockAdjustmentService'

interface StockRow { id: string; quantity: number; product?: { name: string; sku: string }; warehouse?: { name: string } }

const REASONS = ADJUSTMENT_REASONS
const pending = ref<StockAdjustment[]>([])
const history = ref<StockAdjustment[]>([])
const stocks  = ref<StockRow[]>([])
const loading = ref(true)
const busy    = ref(false)
const error   = ref<string | null>(null)

const createModal = reactive({ open: false, saving: false, error: '' })
const form = reactive({ stock_id: '', new_quantity: 0, reason: 'count' as string, note: '' })
const rejectModal = reactive({ open: false, reason: '', id: '' })

function reasonLabel(r: string) { return REASON_LABELS[r] ?? r }
function statusLabel(s: string) { return ({ pending: 'En attente', approved: 'Approuvé', rejected: 'Rejeté', executed: 'Exécuté' } as Record<string, string>)[s] ?? s }

async function load() {
  loading.value = true
  error.value = null
  try {
    pending.value = (await stockAdjustmentService.pending()).data
    history.value = (await stockAdjustmentService.history()).data
  } catch {
    error.value = 'Impossible de charger les ajustements.'
  } finally {
    loading.value = false
  }
}

async function loadStocks() {
  try { stocks.value = (await client.get('/api/inventory/stock')).data.data ?? [] }
  catch { stocks.value = [] }
}

function openCreate() {
  Object.assign(form, { stock_id: '', new_quantity: 0, reason: 'count', note: '' })
  createModal.error = ''
  createModal.open = true
  if (!stocks.value.length) loadStocks()
}

function onStockPick() {
  const s = stocks.value.find(x => x.id === form.stock_id)
  if (s) form.new_quantity = s.quantity
}

async function submitCreate() {
  createModal.saving = true
  createModal.error = ''
  try {
    await stockAdjustmentService.request({ stock_id: form.stock_id, new_quantity: form.new_quantity, reason: form.reason, note: form.note || undefined })
    createModal.open = false
    await load()
  } catch (e: any) {
    createModal.error = e?.response?.data?.message ?? 'Soumission impossible.'
  } finally {
    createModal.saving = false
  }
}

async function approve(a: StockAdjustment) {
  busy.value = true
  try { await stockAdjustmentService.approve(a.id); await load() }
  catch (e: any) { error.value = e?.response?.data?.message ?? 'Approbation impossible.' }
  finally { busy.value = false }
}

function openReject(a: StockAdjustment) { rejectModal.id = a.id; rejectModal.reason = ''; rejectModal.open = true }

async function confirmReject() {
  busy.value = true
  try { await stockAdjustmentService.reject(rejectModal.id, rejectModal.reason); rejectModal.open = false; await load() }
  catch (e: any) { error.value = e?.response?.data?.message ?? 'Rejet impossible.' }
  finally { busy.value = false }
}

onMounted(load)
</script>

<style scoped>
.page-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.25rem; }
.page-subtitle { color: var(--gray-500,#64748b); font-size:0.875rem; margin:0.25rem 0 0; }
.section-head { display:flex; align-items:center; gap:0.6rem; padding:0.75rem 1rem; border-bottom:1px solid var(--gray-100,#f1f5f9); }
.count { background:var(--gray-100,#f1f5f9); color:var(--gray-600,#475569); border-radius:999px; padding:0 0.5rem; font-size:0.75rem; font-weight:600; }
.table-scroll { overflow-x:auto; }
.muted, .muted-row { color: var(--gray-400,#94a3b8); font-size:0.8125rem; }
.muted-row { padding:1rem; }
.actions { display:flex; gap:0.4rem; }
.neg { color:#dc2626; font-weight:600; } .pos { color:#059669; font-weight:600; }
.form-label { display:block; font-size:0.8125rem; font-weight:600; color:var(--gray-600,#475569); margin:0.75rem 0 0.25rem; }
.form-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:8px; padding:0.5rem 0.75rem; font-size:0.85rem; }
.btn-danger { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.btn-sm { padding:0.3rem 0.6rem; font-size:0.8125rem; }
.tag { display:inline-block; padding:0.12rem 0.5rem; border-radius:999px; font-size:0.72rem; font-weight:600; }
.tag--pending { background:#fef9c3; color:#a16207; }
.tag--approved, .tag--executed { background:#ecfdf5; color:#059669; }
.tag--rejected { background:#fef2f2; color:#dc2626; }
</style>
