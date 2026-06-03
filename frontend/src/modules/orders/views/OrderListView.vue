<template>
  <div>
    <SalesTabNav />
    <div class="page-header">
      <h2>Commandes</h2>
      <RouterLink to="/orders/new" class="btn btn-primary">
        + Nouvelle commande
      </RouterLink>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9 3a6 6 0 100 12A6 6 0 009 3zM1 9a8 8 0 1114.32 4.906l3.387 3.387a1 1 0 01-1.414 1.414l-3.387-3.387A8 8 0 011 9z" clip-rule="evenodd" />
        </svg>
        <input
          v-model="search"
          type="text"
          class="form-input search-input"
          placeholder="N° commande, client..."
          @input="debouncedLoad"
        />
      </div>
      <input v-model="dateFrom" type="date" class="form-input date-input" @change="load" title="Depuis" />
      <input v-model="dateTo"   type="date" class="form-input date-input" @change="load" title="Jusqu'au" />
    </div>

    <!-- Status tabs -->
    <div class="status-tabs">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        class="tab-btn"
        :class="{ active: activeTab === tab.value }"
        @click="activeTab = tab.value"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="card" style="margin-top: 1rem; padding: 0; overflow: hidden;">
      <!-- Loading -->
      <div v-if="loading" class="loading-center">
        <span class="spinner-sm"></span>
        Chargement…
      </div>

      <!-- Error -->
      <div v-else-if="error" class="empty-state">
        <div class="empty-state-icon">⚠️</div>
        <h3>Erreur de chargement</h3>
        <p>{{ error }}</p>
        <button class="btn btn-secondary" style="margin-top:1rem" @click="load">Réessayer</button>
      </div>

      <!-- Empty -->
      <div v-else-if="orders.length === 0" class="empty-state">
        <div class="empty-state-icon">📋</div>
        <h3>Aucune commande</h3>
        <p>Les commandes apparaîtront ici.</p>
      </div>

      <!-- Table -->
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>N°</th>
            <th>Statut</th>
            <th>Articles</th>
            <th>Total</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>
              <RouterLink :to="`/orders/${order.id}`" style="font-weight:600; color:#059669;">
                {{ order.number }}
              </RouterLink>
            </td>
            <td>
              <span class="badge" :class="statusBadge(order.status)">
                {{ statusLabel(order.status) }}
              </span>
            </td>
            <td>{{ order.lines.length }} article{{ order.lines.length > 1 ? 's' : '' }}</td>
            <td>{{ formatMoney(order.total_amount) }}</td>
            <td>{{ formatDate(order.created_at) }}</td>
            <td>
              <RouterLink :to="`/orders/${order.id}`" class="btn btn-secondary" style="padding:0.35rem 0.75rem; font-size:0.8rem;">
                Voir
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button class="btn btn-secondary" :disabled="page === 1" @click="page--">‹ Préc.</button>
      <span>Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-secondary" :disabled="page >= meta.last_page" @click="page++">Suiv. ›</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import SalesTabNav from '../components/SalesTabNav.vue'
import { orderService } from '../services/orderService'
import type { Order } from '../types'

const tabs = [
  { label: 'Toutes',     value: '' },
  { label: 'Brouillons', value: 'draft' },
  { label: 'Confirmées', value: 'confirmed' },
  { label: 'Livrées',    value: 'fulfilled' },
  { label: 'Annulées',   value: 'cancelled' },
]

const activeTab = ref('')
const search    = ref('')
const dateFrom  = ref('')
const dateTo    = ref('')
const orders    = ref<Order[]>([])
const meta      = ref<any>(null)
const page      = ref(1)
const loading   = ref(false)
const error     = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await orderService.list({
      status:    activeTab.value || undefined,
      search:    search.value    || undefined,
      from_date: dateFrom.value  || undefined,
      to_date:   dateTo.value    || undefined,
      page:      page.value,
      per_page:  20,
    })
    orders.value = res.data
    meta.value   = res.meta
  } catch {
    error.value = 'Impossible de charger les commandes.'
  } finally {
    loading.value = false
  }
}

let _searchTimer: ReturnType<typeof setTimeout> | null = null
function debouncedLoad() {
  if (_searchTimer) clearTimeout(_searchTimer)
  _searchTimer = setTimeout(() => load(), 280)
}

watch([activeTab, page], () => load())
onMounted(() => load())

function statusLabel(s: string) {
  return { draft: 'Brouillon', confirmed: 'Confirmée', fulfilled: 'Livrée', cancelled: 'Annulée' }[s] ?? s
}

function statusBadge(s: string) {
  return { draft: 'badge-gray', confirmed: 'badge-blue', fulfilled: 'badge-green', cancelled: 'badge-red' }[s] ?? ''
}


function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('fr-SN', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<style scoped>
.status-tabs {
  display: flex;
  gap: 0.25rem;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0.25rem;
  width: fit-content;
}

.tab-btn {
  padding: 0.4rem 0.9rem;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
  color: #6b7280;
  transition: background 0.15s, color 0.15s;
}

.tab-btn.active {
  background: #059669;
  color: white;
  font-weight: 600;
}

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
}

.search-icon {
  position: absolute;
  left: 0.6rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1rem;
  height: 1rem;
  color: #9ca3af;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding-left: 2rem !important;
}

.date-input {
  width: 10rem;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}
</style>
