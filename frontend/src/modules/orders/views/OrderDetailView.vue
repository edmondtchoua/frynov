<template>
  <div style="max-width: 760px;">
    <!-- Header -->
    <div class="page-header">
      <div>
        <RouterLink to="/orders" style="font-size: 0.85rem; color: #6b7280;">← Commandes</RouterLink>
        <h2 style="margin-top: 0.25rem;">{{ order?.number ?? '…' }}</h2>
      </div>
      <span v-if="order" class="badge" :class="statusBadge(order.status)">
        {{ statusLabel(order.status) }}
      </span>
    </div>

    <div v-if="loading" class="loading-center">
      <span class="spinner-sm"></span>
      Chargement…
    </div>

    <div v-else-if="error" class="empty-state">
      <div class="empty-state-icon">⚠️</div>
      <h3>Erreur</h3>
      <p>{{ error }}</p>
    </div>

    <template v-else-if="order">
      <!-- Lines -->
      <div class="card" style="margin-bottom: 1rem; padding: 0; overflow: hidden;">
        <table class="data-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Produit</th>
              <th style="text-align:right;">Qté</th>
              <th style="text-align:right;">Prix unit.</th>
              <th style="text-align:right;">Sous-total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in order.lines" :key="line.id">
              <td><code style="font-size: 0.8rem;">{{ line.sku }}</code></td>
              <td>{{ line.name }}</td>
              <td style="text-align:right;">{{ line.quantity }}</td>
              <td style="text-align:right;">{{ fmt(line.unit_price_cents) }}</td>
              <td style="text-align:right; font-weight: 600;">{{ fmt(line.quantity * line.unit_price_cents) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="text-align: right; font-weight: 700; padding: 0.8rem 1rem;">Total</td>
              <td style="text-align: right; font-weight: 700; padding: 0.8rem 1rem; font-size: 1.05rem;">
                {{ fmt(order.total_amount) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Meta -->
      <div class="card" style="margin-bottom: 1.5rem; font-size: 0.875rem; color: #6b7280;">
        <div v-if="order.note" style="margin-bottom: 0.5rem;"><strong>Note :</strong> {{ order.note }}</div>
        <div><strong>Créée le :</strong> {{ fmtDate(order.created_at) }}</div>
        <div v-if="order.fulfilled_at"><strong>Livrée le :</strong> {{ fmtDate(order.fulfilled_at) }}</div>
        <div v-if="order.cancelled_at"><strong>Annulée le :</strong> {{ fmtDate(order.cancelled_at) }}</div>
      </div>

      <!-- Action buttons -->
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <button
          v-if="order.status === 'draft'"
          class="btn btn-primary"
          :disabled="actionLoading"
          @click="act('confirm')"
        >
          <span v-if="actionLoading === 'confirm'" class="spinner-sm"></span>
          Confirmer
        </button>

        <button
          v-if="order.status === 'confirmed'"
          class="btn btn-primary"
          :disabled="actionLoading"
          @click="act('fulfill')"
        >
          <span v-if="actionLoading === 'fulfill'" class="spinner-sm"></span>
          Marquer livrée
        </button>

        <button
          v-if="order.status === 'draft' || order.status === 'confirmed'"
          class="btn btn-danger"
          :disabled="actionLoading"
          @click="act('cancel')"
        >
          <span v-if="actionLoading === 'cancel'" class="spinner-sm"></span>
          Annuler
        </button>
      </div>

      <!-- Action error -->
      <div v-if="actionError" class="form-error" style="margin-top: 1rem;">{{ actionError }}</div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { orderService } from '../services/orderService'
import type { Order } from '../types'

const route  = useRoute()
const id     = route.params.id as string

const order       = ref<Order | null>(null)
const loading     = ref(false)
const error       = ref<string | null>(null)
const actionLoading = ref<string | false>(false)
const actionError   = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    order.value = await orderService.get(id)
  } catch {
    error.value = 'Commande introuvable.'
  } finally {
    loading.value = false
  }
}

async function act(action: 'confirm' | 'fulfill' | 'cancel') {
  actionLoading.value = action
  actionError.value   = null
  try {
    order.value = await orderService[action](id)
  } catch (e: any) {
    actionError.value = e?.response?.data?.message ?? `Erreur lors de l'action "${action}".`
  } finally {
    actionLoading.value = false
  }
}

onMounted(load)

function statusLabel(s: string) {
  return { draft: 'Brouillon', confirmed: 'Confirmée', fulfilled: 'Livrée', cancelled: 'Annulée' }[s] ?? s
}

function statusBadge(s: string) {
  return { draft: 'badge-gray', confirmed: 'badge-blue', fulfilled: 'badge-green', cancelled: 'badge-red' }[s] ?? ''
}

function fmt(cents: number) {
  return new Intl.NumberFormat('fr-SN', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(cents)
}

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('fr-SN', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
