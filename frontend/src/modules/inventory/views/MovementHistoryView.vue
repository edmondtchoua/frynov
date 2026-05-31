<template>
  <div>
    <!-- Header -->
    <div class="page-header">
      <div>
        <RouterLink to="/inventory" class="back-link">← Stock</RouterLink>
        <h2>Historique des mouvements</h2>
        <p v-if="stockInfo" class="page-subtitle">
          {{ stockInfo.product?.name }} · {{ stockInfo.product?.sku }}
        </p>
      </div>
    </div>

    <!-- Product summary card -->
    <div v-if="stockInfo" class="summary-card">
      <div class="summary-stat">
        <span class="summary-label">Quantité</span>
        <span class="summary-value">{{ stockInfo.stock.quantity }}</span>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-stat">
        <span class="summary-label">Réservé</span>
        <span class="summary-value">{{ stockInfo.stock.reserved_quantity }}</span>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-stat">
        <span class="summary-label">Disponible</span>
        <span class="summary-value" :style="stockInfo.stock.is_low_stock ? 'color:#b45309;' : 'color:#059669;'">
          {{ stockInfo.available }}
        </span>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-stat">
        <span class="summary-label">Seuil bas</span>
        <span class="summary-value">{{ stockInfo.stock.low_stock_threshold }}</span>
      </div>
      <span v-if="stockInfo.stock.is_low_stock" class="badge badge-warning" style="align-self: center;">Stock bas</span>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filters.type" class="form-input filter-select" style="max-width: 180px;" @change="load">
        <option value="">Tous les types</option>
        <option value="in">Entrée</option>
        <option value="out">Sortie</option>
        <option value="adjustment">Ajustement</option>
        <option value="return">Retour</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <!-- Empty -->
    <div v-else-if="movements.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <circle cx="20" cy="20" r="8" stroke="var(--brand-primary)" stroke-width="2"/>
        <path d="M20 16v4.5l2.5 2" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h3>Aucun mouvement</h3>
      <p>{{ filters.type ? 'Aucun mouvement de ce type pour ce produit.' : 'Aucun mouvement enregistré pour ce produit.' }}</p>
    </div>

    <!-- Timeline -->
    <div v-else class="timeline">
      <div
        v-for="(move, idx) in movements"
        :key="move.id"
        class="timeline-item"
        :class="{ 'timeline-item-last': idx === movements.length - 1 }"
      >
        <!-- Icon node -->
        <div class="timeline-node" :class="`node-${move.type}`">
          <!-- in -->
          <svg v-if="move.type === 'in'" width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <!-- out -->
          <svg v-else-if="move.type === 'out'" width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M2 7h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <!-- adjustment -->
          <svg v-else-if="move.type === 'adjustment'" width="14" height="14" viewBox="0 0 14 14" fill="none">
            <circle cx="7" cy="7" r="2.5" stroke="currentColor" stroke-width="1.4"/>
            <path d="M7 1v2M7 11v2M1 7h2M11 7h2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <!-- return -->
          <svg v-else width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M5 3L2 6l3 3M2 6h7a3 3 0 0 1 0 6H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <!-- Connector line -->
        <div v-if="idx < movements.length - 1" class="timeline-line"></div>

        <!-- Content -->
        <div class="timeline-content">
          <div class="timeline-main">
            <div class="timeline-left">
              <!-- Type + reason chips -->
              <div class="chips-row">
                <span :class="`type-chip type-chip-${move.type}`">{{ typeLabel(move.type) }}</span>
                <span class="reason-chip">{{ reasonLabel(move.reason) }}</span>
                <span v-if="move.reference" class="ref-chip">{{ move.reference }}</span>
              </div>
              <!-- Quantity change -->
              <div class="qty-change">
                <span class="qty-before">{{ move.quantity_before }}</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;">
                  <path d="M3 8h10M9 4l4 4-4 4" stroke="var(--gray-400)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="qty-after" :class="move.type === 'out' ? 'qty-after-down' : 'qty-after-up'">
                  {{ move.quantity_after }}
                </span>
                <span class="qty-delta" :class="move.quantity_after > move.quantity_before ? 'delta-pos' : move.quantity_after < move.quantity_before ? 'delta-neg' : 'delta-zero'">
                  {{ move.quantity_after > move.quantity_before ? '+' : '' }}{{ move.quantity_after - move.quantity_before }}
                </span>
              </div>
              <!-- Note -->
              <p v-if="move.note" class="move-note">{{ move.note }}</p>
            </div>
            <div class="timeline-right">
              <span class="move-date">{{ formatDate(move.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">← Précédent</button>
      <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Suivant →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { inventoryService } from '../services/inventoryService'
import type { StockMovement, MovementType } from '../types'

const route = useRoute()
const productId = route.params.productId as string

// ── State ──────────────────────────────────────────────────────────────────────
const movements = ref<StockMovement[]>([])
const stockInfo = ref<{ stock: any; available: number; is_low_stock: boolean } | null>(null)
const loading   = ref(false)
const meta      = reactive({ current_page: 1, last_page: 1, per_page: 30, total: 0 })
const filters   = reactive({ type: '', page: 1 })

// ── Data ───────────────────────────────────────────────────────────────────────
async function loadStock() {
  try {
    const res = await inventoryService.get(productId)
    stockInfo.value = res
  } catch {
    stockInfo.value = null
  }
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: filters.page, per_page: meta.per_page }
    if (filters.type) params.type = filters.type
    const res = await inventoryService.movements(productId, params as any)
    movements.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    movements.value = []
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) { filters.page = page; load() }

// ── Formatters ────────────────────────────────────────────────────────────────
function typeLabel(type: MovementType): string {
  return { in: 'Entrée', out: 'Sortie', adjustment: 'Ajustement', return: 'Retour' }[type] ?? type
}

function reasonLabel(reason: string): string {
  return ({
    delivery: 'Livraison',
    sale:     'Vente',
    return:   'Retour',
    loss:     'Perte',
    count:    'Inventaire',
    manual:   'Manuel',
  } as Record<string, string>)[reason] ?? reason
}

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  }).format(new Date(iso))
}

onMounted(() => { loadStock(); load() })
</script>

<style scoped>
.back-link {
  font-size: 0.8rem;
  color: var(--gray-500);
  text-decoration: none;
  display: inline-block;
  margin-bottom: 4px;
}
.back-link:hover { color: var(--brand-primary); }

/* Summary */
.summary-card {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 16px 20px;
  background: var(--card-bg);
  border: 1px solid var(--gray-200);
  border-radius: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.summary-stat { display: flex; flex-direction: column; gap: 2px; }
.summary-label { font-size: 0.72rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: .05em; }
.summary-value { font-size: 1.1rem; font-weight: 700; color: var(--gray-900); }
.summary-divider { width: 1px; height: 32px; background: var(--gray-200); }

/* Timeline */
.timeline {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0;
  padding-top: 4px;
}

.timeline-item {
  display: flex;
  gap: 16px;
  position: relative;
}

.timeline-node {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 10px;
  z-index: 1;
}
.node-in         { background: #d1fae5; color: #059669; }
.node-out        { background: #fee2e2; color: #dc2626; }
.node-adjustment { background: #dbeafe; color: #2563eb; }
.node-return     { background: #fef3c7; color: #d97706; }

.timeline-line {
  position: absolute;
  left: 15px;
  top: 42px;
  bottom: -10px;
  width: 2px;
  background: var(--gray-200);
  z-index: 0;
}

.timeline-content {
  flex: 1;
  padding: 10px 0 20px;
}

.timeline-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  background: var(--card-bg);
  border: 1px solid var(--gray-200);
  border-radius: 10px;
  padding: 12px 16px;
  transition: box-shadow 0.15s;
}
.timeline-main:hover { box-shadow: 0 2px 10px rgba(0,0,0,.07); }

.timeline-left  { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 0; }
.timeline-right { flex-shrink: 0; }

/* Chips */
.chips-row { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }

.type-chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 600;
}
.type-chip-in         { background: #d1fae5; color: #065f46; }
.type-chip-out        { background: #fee2e2; color: #991b1b; }
.type-chip-adjustment { background: #dbeafe; color: #1e40af; }
.type-chip-return     { background: #fef3c7; color: #92400e; }

.reason-chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 99px;
  font-size: 0.72rem;
  background: var(--gray-100);
  color: var(--gray-600);
}

.ref-chip {
  font-size: 0.72rem;
  font-family: var(--font-mono, 'Courier New', monospace);
  color: var(--gray-500);
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  padding: 1px 6px;
  border-radius: 4px;
}

/* Quantity change */
.qty-change {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
}
.qty-before { color: var(--gray-500); font-variant-numeric: tabular-nums; }
.qty-after  { font-weight: 700; font-variant-numeric: tabular-nums; }
.qty-after-up   { color: #059669; }
.qty-after-down { color: #dc2626; }

.qty-delta {
  font-size: 0.8rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 6px;
  font-variant-numeric: tabular-nums;
}
.delta-pos  { background: #d1fae5; color: #065f46; }
.delta-neg  { background: #fee2e2; color: #991b1b; }
.delta-zero { background: var(--gray-100); color: var(--gray-600); }

.move-note { font-size: 0.82rem; color: var(--gray-500); font-style: italic; }
.move-date { font-size: 0.78rem; color: var(--gray-400); white-space: nowrap; }
</style>
