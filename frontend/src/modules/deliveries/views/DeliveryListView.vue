<template>
  <div>
    <SalesTabNav />
    <div class="page-header">
      <div>
        <h2>Livraisons</h2>
        <p class="page-subtitle">{{ meta.total ?? '—' }} livraison{{ meta.total !== 1 ? 's' : '' }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        Nouvelle livraison
      </button>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filters.status" class="form-input filter-select" style="max-width: 200px;" @change="load">
        <option value="">Tous les statuts</option>
        <option value="pending">En attente</option>
        <option value="dispatched">Expédiée</option>
        <option value="in_transit">En transit</option>
        <option value="delivered">Livrée</option>
        <option value="failed">Échec</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <!-- Empty -->
    <div v-else-if="deliveries.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <path d="M8 22l4-8h12l4 8v4H8v-4Z" stroke="var(--brand-primary)" stroke-width="2" stroke-linejoin="round"/>
        <circle cx="14" cy="27" r="2" fill="var(--brand-primary)"/>
        <circle cx="26" cy="27" r="2" fill="var(--brand-primary)"/>
      </svg>
      <h3>Aucune livraison</h3>
      <p>{{ filters.status ? 'Aucune livraison avec ce statut.' : 'Les livraisons apparaîtront ici.' }}</p>
    </div>

    <!-- Table -->
    <div v-else class="card" style="padding: 0; overflow: hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Statut</th>
            <th class="hide-mobile">Commande</th>
            <th class="hide-mobile">Transporteur</th>
            <th class="hide-mobile">Suivi</th>
            <th>Dates</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in deliveries" :key="d.id">
            <td>
              <span :class="`badge ${statusBadge(d.status)}`">{{ statusLabel(d.status) }}</span>
            </td>
            <td class="hide-mobile">
              <RouterLink
                v-if="d.order_id"
                :to="`/orders/${d.order_id}`"
                style="font-family: monospace; font-size: 0.82rem; color: var(--brand-secondary);"
              >
                {{ d.order_number ?? d.order_id.slice(0, 8) + '…' }}
              </RouterLink>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="hide-mobile">{{ d.carrier ?? '—' }}</td>
            <td class="hide-mobile">
              <span v-if="d.tracking_number" style="font-family: monospace; font-size: 0.82rem;">
                {{ d.tracking_number }}
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td style="font-size: 0.8rem; color: var(--gray-600);">
              <div v-if="d.dispatched_at">📤 {{ fmtDate(d.dispatched_at) }}</div>
              <div v-if="d.delivered_at">✅ {{ fmtDate(d.delivered_at) }}</div>
              <div v-if="d.failed_at">❌ {{ fmtDate(d.failed_at) }}</div>
              <div v-if="!d.dispatched_at && !d.delivered_at && !d.failed_at" class="text-muted">
                Créée {{ fmtDate(d.created_at) }}
              </div>
            </td>
            <td>
              <div class="row-actions" style="justify-content: flex-end;">
                <!-- Dispatch -->
                <button
                  v-if="d.status === 'pending'"
                  class="btn btn-ghost btn-sm"
                  :disabled="actionId === d.id"
                  @click="doDispatch(d)"
                >
                  <span v-if="actionId === d.id" class="spinner-sm"></span>
                  Expédier
                </button>
                <!-- Deliver -->
                <button
                  v-if="d.status === 'dispatched' || d.status === 'in_transit'"
                  class="btn btn-ghost btn-sm"
                  style="color: #059669;"
                  :disabled="actionId === d.id"
                  @click="doDeliver(d)"
                >
                  <span v-if="actionId === d.id" class="spinner-sm"></span>
                  Livré
                </button>
                <!-- Fail -->
                <button
                  v-if="d.status !== 'delivered' && d.status !== 'failed'"
                  class="btn btn-ghost btn-sm"
                  style="color: #dc2626;"
                  :disabled="actionId === d.id"
                  @click="openFail(d)"
                >
                  Échec
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">← Précédent</button>
      <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Suivant →</button>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="createModal.open" class="modal-backdrop" @click.self="createModal.open = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3 class="modal-title">Nouvelle livraison</h3>
            <button class="modal-close" @click="createModal.open = false">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M4 4l10 10M14 4L4 14" stroke="var(--gray-500)" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
            <div class="form-group">
              <label class="form-label">Transporteur</label>
              <input v-model="createForm.carrier" type="text" class="form-input" placeholder="DHL, FedEx, Colissimo…" />
            </div>
            <div class="form-group">
              <label class="form-label">N° de suivi</label>
              <input v-model="createForm.tracking_number" type="text" class="form-input" placeholder="ABC123456789" />
            </div>
            <div class="form-group">
              <label class="form-label">Notes</label>
              <textarea v-model="createForm.notes" class="form-input" rows="2" style="resize: vertical; min-height: 56px;"></textarea>
            </div>
            <p v-if="createModal.error" style="color: #dc2626; font-size: 0.875rem;">{{ createModal.error }}</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" @click="createModal.open = false">Annuler</button>
            <button class="btn btn-primary" :disabled="createModal.saving" @click="submitCreate">
              <span v-if="createModal.saving" class="spinner-sm"></span>
              Créer
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Fail reason modal -->
    <Teleport to="body">
      <div v-if="failModal.open" class="modal-backdrop" @click.self="failModal.open = false">
        <div class="modal-box" style="max-width: 420px;">
          <div class="modal-header">
            <h3 class="modal-title">Signaler un échec</h3>
            <button class="modal-close" @click="failModal.open = false">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M4 4l10 10M14 4L4 14" stroke="var(--gray-500)" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <div class="modal-body" style="display: flex; flex-direction: column; gap: 12px;">
            <div class="form-group">
              <label class="form-label">Raison de l'échec <span style="color:#dc2626;">*</span></label>
              <textarea v-model="failModal.reason" class="form-input" rows="3" style="resize: vertical;" placeholder="Ex : Adresse introuvable, client absent…"></textarea>
            </div>
            <p v-if="failModal.error" style="color: #dc2626; font-size: 0.875rem;">{{ failModal.error }}</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" @click="failModal.open = false">Annuler</button>
            <button class="btn btn-danger" :disabled="failModal.saving || !failModal.reason.trim()" @click="submitFail">
              <span v-if="failModal.saving" class="spinner-sm"></span>
              Confirmer l'échec
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import SalesTabNav from '../../orders/components/SalesTabNav.vue'
import { deliveryService } from '../services/deliveryService'
import type { Delivery, DeliveryStatus } from '../types'

const deliveries = ref<Delivery[]>([])
const loading    = ref(false)
const actionId   = ref<string | null>(null)
const meta       = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const filters    = reactive({ status: '', page: 1 })

// ── Create modal ──────────────────────────────────────────────────────────────
const createModal = reactive({ open: false, saving: false, error: '' })
const createForm  = reactive({ carrier: '', tracking_number: '', notes: '' })

// ── Fail modal ────────────────────────────────────────────────────────────────
const failModal = reactive({ open: false, saving: false, error: '', deliveryId: '', reason: '' })

// ── Data ───────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: filters.page, per_page: meta.per_page }
    if (filters.status) params.status = filters.status
    const res = await deliveryService.list(params as any)
    deliveries.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    deliveries.value = []
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) { filters.page = page; load() }

// ── Actions ────────────────────────────────────────────────────────────────────
async function doDispatch(d: Delivery) {
  actionId.value = d.id
  try {
    await deliveryService.dispatch(d.id)
    load()
  } catch { /* ignore */ } finally { actionId.value = null }
}

async function doDeliver(d: Delivery) {
  actionId.value = d.id
  try {
    await deliveryService.deliver(d.id)
    load()
  } catch { /* ignore */ } finally { actionId.value = null }
}

function openFail(d: Delivery) {
  failModal.deliveryId = d.id
  failModal.reason     = ''
  failModal.error      = ''
  failModal.saving     = false
  failModal.open       = true
}

async function submitFail() {
  if (!failModal.reason.trim()) return
  failModal.saving = true
  failModal.error  = ''
  try {
    await deliveryService.fail(failModal.deliveryId, failModal.reason)
    failModal.open = false
    load()
  } catch (e: any) {
    failModal.error = e?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    failModal.saving = false
  }
}

// ── Create ─────────────────────────────────────────────────────────────────────
function openCreate() {
  createForm.carrier          = ''
  createForm.tracking_number  = ''
  createForm.notes            = ''
  createModal.error           = ''
  createModal.saving          = false
  createModal.open            = true
}

async function submitCreate() {
  createModal.saving = true
  createModal.error  = ''
  try {
    await deliveryService.create({
      carrier:         createForm.carrier || undefined,
      tracking_number: createForm.tracking_number || undefined,
      notes:           createForm.notes || undefined,
    })
    createModal.open = false
    load()
  } catch (e: any) {
    createModal.error = e?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    createModal.saving = false
  }
}

// ── Formatters ────────────────────────────────────────────────────────────────
function statusLabel(s: DeliveryStatus): string {
  return ({
    pending:    'En attente',
    dispatched: 'Expédiée',
    in_transit: 'En transit',
    delivered:  'Livrée',
    failed:     'Échec',
  } as Record<DeliveryStatus, string>)[s] ?? s
}

function statusBadge(s: DeliveryStatus): string {
  return ({
    pending:    'badge-gray',
    dispatched: 'badge-blue',
    in_transit: 'badge-blue',
    delivered:  'badge-success',
    failed:     'badge-error',
  } as Record<DeliveryStatus, string>)[s] ?? 'badge-gray'
}

function fmtDate(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short' }).format(new Date(iso))
}

onMounted(load)
</script>
