<template>
  <div>
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h2>Alertes stock bas</h2>
        <p class="page-subtitle">{{ alerts.length }} article{{ alerts.length > 1 ? 's' : '' }} sous le seuil</p>
      </div>
      <RouterLink to="/inventory" class="btn btn-ghost">
        ← Retour au stock
      </RouterLink>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <!-- Empty (no alerts = good news) -->
    <div v-else-if="alerts.length === 0" class="empty-state">
      <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
        <circle cx="24" cy="24" r="20" fill="var(--success-bg, #d1fae5)"/>
        <path d="M15 24l6 6 12-12" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <h3>Tout est en ordre !</h3>
      <p>Aucun article ne se trouve sous son seuil de stock minimum.</p>
    </div>

    <!-- Alert banner -->
    <div v-else>
      <div class="alert-banner">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
          <path d="M10 2.5a6 6 0 0 1 6 6v3l1.5 2H2.5L4 11.5v-3a6 6 0 0 1 6-6Z" stroke="#92400e" stroke-width="1.5"/>
          <path d="M8 16.5a2 2 0 0 0 4 0" stroke="#92400e" stroke-width="1.5"/>
        </svg>
        <span>{{ alerts.length }} article{{ alerts.length > 1 ? 's nécessitent' : ' nécessite' }} un réapprovisionnement urgent.</span>
      </div>

      <!-- Alert cards grid -->
      <div class="alerts-grid">
        <div v-for="stock in alerts" :key="stock.id" class="alert-card">
          <div class="alert-card-header">
            <div class="product-thumb" style="background: var(--warning-bg, #fef3c7); color: #92400e; flex-shrink: 0;">
              {{ stock.product?.name?.charAt(0)?.toUpperCase() ?? '?' }}
            </div>
            <div class="alert-card-info">
              <div class="product-name">{{ stock.product?.name ?? '—' }}</div>
              <div class="product-sku">{{ stock.product?.sku ?? '—' }}</div>
            </div>
            <span class="badge badge-warning">Stock bas</span>
          </div>

          <!-- Stock numbers -->
          <div class="alert-numbers">
            <div class="alert-num-item">
              <span class="alert-num-label">Disponible</span>
              <span class="alert-num-value alert-num-critical">{{ stock.available }}</span>
            </div>
            <div class="alert-num-divider">vs</div>
            <div class="alert-num-item">
              <span class="alert-num-label">Seuil min</span>
              <span class="alert-num-value">{{ stock.low_stock_threshold }}</span>
            </div>
          </div>

          <!-- Progress bar -->
          <div class="stock-bar-wrap">
            <div class="stock-bar-track">
              <div
                class="stock-bar-fill"
                :style="{
                  width: Math.min(100, (stock.available / Math.max(1, stock.low_stock_threshold)) * 100) + '%',
                  background: stock.available === 0 ? '#dc2626' : '#f59e0b',
                }"
              ></div>
            </div>
            <span class="stock-bar-label">
              {{ stock.available === 0 ? 'Rupture' : Math.round((stock.available / stock.low_stock_threshold) * 100) + '% du seuil' }}
            </span>
          </div>

          <!-- Actions -->
          <div class="alert-card-actions">
            <button class="btn btn-primary btn-sm" style="flex: 1;" @click="openRestock(stock)">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              Réapprovisionner
            </button>
            <RouterLink :to="`/inventory/movements/${stock.product_id}`" class="btn btn-ghost btn-sm">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.4"/>
                <path d="M7 4v3.5l2 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
              </svg>
            </RouterLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Restock Modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="modal.open" title="Réapprovisionner" :subtitle="modal.stock?.product ? (modal.stock.product.name + ' · ' + modal.stock.product.sku) : ''">
      <div style="display: flex; flex-direction: column; gap: 16px;">
            <div class="stock-info-row">
              <div class="stock-info-item">
                <span class="stock-info-label">Disponible actuellement</span>
                <span class="stock-info-value alert-num-critical">{{ modal.stock?.available }}</span>
              </div>
              <div class="stock-info-item">
                <span class="stock-info-label">Seuil minimum</span>
                <span class="stock-info-value">{{ modal.stock?.low_stock_threshold }}</span>
              </div>
              <div class="stock-info-item">
                <span class="stock-info-label">Manque</span>
                <span class="stock-info-value">
                  {{ Math.max(0, (modal.stock?.low_stock_threshold ?? 0) - (modal.stock?.available ?? 0)) }}
                </span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Quantité à réceptionner <span class="required-star">*</span></label>
              <div class="input-affix">
                <input
                  v-model.number="form.quantity"
                  type="number"
                  min="1"
                  class="form-input"
                  placeholder="Ex : 50"
                />
                <span class="input-affix__suffix">unités</span>
              </div>
              <p v-if="form.quantity" class="form-hint">
                Après réception : {{ (modal.stock?.quantity ?? 0) + (form.quantity || 0) }} unités
              </p>
            </div>

            <div class="form-group">
              <label class="form-label">Raison</label>
              <select v-model="form.reason" class="form-input">
                <option value="delivery">Livraison fournisseur</option>
                <option value="return">Retour client</option>
                <option value="manual">Saisie manuelle</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Référence commande fournisseur</label>
              <input v-model="form.reference" type="text" class="form-input" placeholder="Ex : BC-2026-042" />
            </div>

            <div class="form-group">
              <label class="form-label">Note</label>
              <textarea v-model="form.note" class="form-input" style="resize: vertical; min-height: 60px;" rows="2" placeholder="Commentaire optionnel…"></textarea>
            </div>

            <p v-if="modal.error" class="form-error">{{ modal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="closeModal">Annuler</button>
        <button
          class="btn btn-primary"
          :disabled="modal.saving || !form.quantity || form.quantity <= 0"
          @click="submitRestock"
        >
          <span v-if="modal.saving" class="spinner-sm"></span>
          Confirmer la réception
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import BaseModal from '@/shared/ui/BaseModal.vue'
import { inventoryService } from '../services/inventoryService'
import type { Stock, MovementReason } from '../types'

const alerts  = ref<Stock[]>([])
const loading = ref(false)

// ── Modal ──────────────────────────────────────────────────────────────────────
const modal = reactive({ open: false, stock: null as Stock | null, saving: false, error: '' })
const form  = reactive({ quantity: undefined as number | undefined, reason: 'delivery' as MovementReason, reference: '', note: '' })

// ── Data ───────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    alerts.value = await inventoryService.alerts()
  } catch {
    alerts.value = []
  } finally {
    loading.value = false
  }
}

// ── Modal ──────────────────────────────────────────────────────────────────────
function openRestock(stock: Stock) {
  modal.stock  = stock
  modal.error  = ''
  modal.saving = false
  form.quantity  = Math.max(1, (stock.low_stock_threshold ?? 0) - (stock.available ?? 0) + 10)
  form.reason    = 'delivery'
  form.reference = ''
  form.note      = ''
  modal.open = true
}

function closeModal() { modal.open = false }

async function submitRestock() {
  if (!modal.stock || !form.quantity || form.quantity <= 0) return
  modal.saving = true
  modal.error  = ''
  try {
    await inventoryService.moveIn(modal.stock.product_id, {
      quantity:  form.quantity,
      reason:    form.reason,
      reference: form.reference || undefined,
      note:      form.note || undefined,
    })
    closeModal()
    load()
  } catch (e: any) {
    modal.error = e?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    modal.saving = false
  }
}

onMounted(load)
</script>

<style scoped>
.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 10px;
  color: #92400e;
  font-size: 0.9rem;
  margin-bottom: 20px;
}

.alerts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.alert-card {
  background: var(--card-bg);
  border: 1px solid var(--gray-200);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: box-shadow 0.2s;
}
.alert-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }

.alert-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}
.alert-card-info { flex: 1; min-width: 0; }
.alert-card-info .product-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.alert-numbers {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #fffbeb;
  border-radius: 8px;
}
.alert-num-item { display: flex; flex-direction: column; gap: 2px; flex: 1; text-align: center; }
.alert-num-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: .04em; }
.alert-num-value { font-size: 1.1rem; font-weight: 700; color: var(--gray-900); }
.alert-num-critical { color: #b45309; }
.alert-num-divider { color: var(--gray-400); font-size: 0.75rem; }

.stock-bar-wrap { display: flex; flex-direction: column; gap: 4px; }
.stock-bar-track { height: 6px; background: var(--gray-200); border-radius: 99px; overflow: hidden; }
.stock-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.stock-bar-label { font-size: 0.75rem; color: var(--gray-500); }

.alert-card-actions { display: flex; gap: 8px; }

.stock-info-row {
  display: flex;
  gap: 16px;
  padding: 12px 16px;
  background: var(--gray-50);
  border-radius: 8px;
  border: 1px solid var(--gray-200);
}
.stock-info-item { display: flex; flex-direction: column; gap: 2px; }
.stock-info-label { font-size: 0.75rem; color: var(--gray-500); }
.stock-info-value { font-size: 1rem; font-weight: 600; color: var(--gray-900); }
.modal-subtitle { font-size: 0.875rem; color: var(--gray-500); margin-top: 2px; }
.required-star { color: #dc2626; margin-left: 2px; }
.form-hint { font-size: 0.8rem; color: var(--gray-500); margin-top: 4px; }
.form-error { color: #dc2626; font-size: 0.875rem; }
</style>
