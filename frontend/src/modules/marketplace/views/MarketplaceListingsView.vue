<template>
  <div>
    <div class="page-header">
      <div>
        <h2>Connexions Marketplace</h2>
        <p class="page-subtitle">Synchronisez votre stock avec vos canaux de vente externes.</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
          <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Connecter une plateforme
      </button>
    </div>

    <!-- Alert banner -->
    <Transition name="slide-down">
      <div v-if="unreadAlerts > 0" class="alert-banner">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <span><strong>{{ unreadAlerts }} action{{ unreadAlerts > 1 ? 's' : '' }} requise{{ unreadAlerts > 1 ? 's' : '' }}</strong> — Vérifiez les alertes de synchronisation ci-dessous.</span>
        <button class="btn btn-sm btn-secondary" @click="activeTab = 'alerts'">Voir les alertes</button>
      </div>
    </Transition>

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab" :class="{ active: activeTab === 'listings' }" @click="activeTab = 'listings'">
        Connexions <span v-if="listings.length" class="tab-count">{{ listings.length }}</span>
      </button>
      <button class="tab" :class="{ active: activeTab === 'alerts' }" @click="activeTab = 'alerts'">
        Alertes
        <span v-if="unreadAlerts > 0" class="tab-count tab-count--red">{{ unreadAlerts }}</span>
      </button>
    </div>

    <!-- Listings tab -->
    <div v-if="activeTab === 'listings'">
      <div v-if="loadingListings" class="loading-center" style="min-height:200px">
        <span class="spinner-sm" style="width:24px;height:24px;border-width:3px"></span>
      </div>
      <div v-else-if="listings.length === 0" class="empty-state">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
          <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
          <path d="M10 20h20M20 10v20" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <h3>Aucune connexion</h3>
        <p>Connectez vos produits à Facebook Marketplace, WhatsApp Business, Shopify ou Jumia.</p>
        <button class="btn btn-primary" @click="openCreate">Première connexion</button>
      </div>
      <div v-else class="card" style="padding:0;overflow:hidden">
        <table class="data-table">
          <thead>
            <tr>
              <th>Plateforme</th>
              <th>Produit</th>
              <th>ID Externe</th>
              <th>Statut</th>
              <th>Fermeture auto</th>
              <th>Dernière sync</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in listings" :key="l.id">
              <td>
                <div class="platform-cell">
                  <span class="platform-icon" :class="`platform-${l.platform}`">
                    {{ platformEmoji(l.platform) }}
                  </span>
                  <span class="platform-name">{{ platformLabel(l.platform) }}</span>
                </div>
              </td>
              <td>
                <div class="product-cell-sm">
                  <div class="product-name-sm">{{ l.product?.name ?? '—' }}</div>
                  <div v-if="l.variant" class="variant-tag-sm">{{ l.variant?.name }}</div>
                </div>
              </td>
              <td>
                <a v-if="l.external_url" :href="l.external_url" target="_blank" class="ext-link">
                  {{ l.external_product_id }}
                  <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 10L10 2M10 2H5M10 2v5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                </a>
                <span v-else class="mono-sm">{{ l.external_product_id }}</span>
              </td>
              <td>
                <span class="sync-badge" :class="`sync-${l.sync_status}`">
                  {{ syncLabel(l.sync_status) }}
                </span>
              </td>
              <td>
                <span :class="l.is_auto_close_enabled ? 'badge badge-success' : 'badge badge-gray'">
                  {{ l.is_auto_close_enabled ? 'Oui' : 'Non' }}
                </span>
              </td>
              <td class="text-dim">{{ l.last_synced_at ? fmtDate(l.last_synced_at) : '—' }}</td>
              <td style="text-align:right">
                <div class="row-actions">
                  <button class="btn btn-ghost btn-sm" @click="openEdit(l)">Modifier</button>
                  <button class="btn btn-ghost btn-sm text-danger" @click="deleteListing(l.id)">Supprimer</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Alerts tab -->
    <div v-if="activeTab === 'alerts'">
      <div v-if="loadingAlerts" class="loading-center" style="min-height:200px">
        <span class="spinner-sm" style="width:24px;height:24px;border-width:3px"></span>
      </div>
      <div v-else-if="alerts.length === 0" class="empty-state" style="padding:3rem 2rem">
        <p>Aucune alerte de synchronisation. Tout est à jour.</p>
      </div>
      <div v-else class="alerts-list">
        <div v-for="a in alerts" :key="a.id" class="alert-card" :class="{ unread: !a.is_read }">
          <div class="alert-severity" :class="`sev-${a.severity}`">
            {{ a.severity === 'warning' ? '⚠️' : a.severity === 'error' ? '🔴' : 'ℹ️' }}
          </div>
          <div class="alert-body">
            <div class="alert-msg">{{ a.message }}</div>
            <div class="alert-meta">{{ fmtDate(a.created_at) }}</div>
          </div>
          <button v-if="!a.is_read" class="btn btn-sm btn-ghost" @click="markRead(a.id)">
            Marquer lu
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit modal -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.editing ? 'Modifier la connexion' : 'Nouvelle connexion marketplace' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="!modal.editing">
              <div class="form-group">
                <label class="form-label">Plateforme <span class="req">*</span></label>
                <div class="platform-grid">
                  <button
                    v-for="p in platforms"
                    :key="p.code"
                    type="button"
                    class="platform-btn"
                    :class="{ active: form.platform === p.code }"
                    @click="form.platform = p.code"
                  >
                    <span class="platform-icon" :class="`platform-${p.code}`">{{ platformEmoji(p.code) }}</span>
                    {{ p.label }}
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">ID produit externe <span class="req">*</span></label>
                <input v-model="form.external_product_id" class="form-input mono" placeholder="123456789" />
                <span class="form-hint">ID du produit/article sur la plateforme cible.</span>
              </div>
              <div class="form-group">
                <label class="form-label">URL de la listing <span class="hint">(optionnel)</span></label>
                <input v-model="form.external_url" type="url" class="form-input" placeholder="https://..." />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Seuil de fermeture</label>
              <input v-model.number="form.close_threshold" type="number" min="0" class="form-input" style="width:100px" />
              <span class="form-hint">Fermer la listing si le stock disponible tombe ≤ à ce seuil.</span>
            </div>
            <div class="form-switches">
              <label class="switch-row">
                <input v-model="form.is_auto_close_enabled" type="checkbox" class="switch-input" />
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">Fermeture automatique quand stock ≤ seuil</span>
              </label>
              <label class="switch-row">
                <input v-model="form.is_auto_reopen_enabled" type="checkbox" class="switch-input" />
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">Réouverture automatique après réapprovisionnement</span>
              </label>
              <label class="switch-row">
                <input v-model="form.is_price_sync_enabled" type="checkbox" class="switch-input" />
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">Synchroniser les prix automatiquement</span>
              </label>
            </div>
            <div v-if="modal.error" class="form-error">{{ modal.error }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" @click="closeModal">Annuler</button>
            <button class="btn btn-primary" :disabled="modal.saving" @click="save">
              <span v-if="modal.saving" class="spinner-sm spinner-white"></span>
              {{ modal.saving ? '…' : (modal.editing ? 'Mettre à jour' : 'Connecter') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { formatDateTime } from '@/shared/utils/date'
import client from '@/api/client'

const activeTab      = ref<'listings'|'alerts'>('listings')
const listings       = ref<any[]>([])
const alerts         = ref<any[]>([])
const platforms      = ref<any[]>([])
const loadingListings = ref(false)
const loadingAlerts   = ref(false)

const unreadAlerts = computed(() => alerts.value.filter(a => !a.is_read).length)

const modal = reactive({ open:false, editing:false, editId:'', saving:false, error:'' })
const form  = reactive({
  platform:'facebook', external_product_id:'', external_url:'',
  close_threshold:0, is_auto_close_enabled:true,
  is_auto_reopen_enabled:false, is_price_sync_enabled:false,
})

async function loadListings() {
  loadingListings.value = true
  try { const r = await client.get('/api/marketplace/listings'); listings.value = r.data.data ?? [] }
  catch { listings.value = [] } finally { loadingListings.value = false }
}

async function loadAlerts() {
  loadingAlerts.value = true
  try { const r = await client.get('/api/marketplace/alerts'); alerts.value = r.data.data ?? [] }
  catch { alerts.value = [] } finally { loadingAlerts.value = false }
}

async function loadPlatforms() {
  try { const r = await client.get('/api/marketplace/platforms'); platforms.value = r.data.data ?? [] }
  catch { platforms.value = [] }
}

function platformLabel(code: string) {
  return platforms.value.find(p => p.code === code)?.label ?? code
}

function platformEmoji(code: string) {
  return { facebook:'📘', whatsapp_catalog:'💬', shopify:'🛍', jumia:'🛒', amazon:'📦', tiktok:'🎵' }[code] ?? '🔗'
}

function syncLabel(s: string) {
  return { active:'Actif', closed:'Fermé', error:'Erreur', syncing:'Sync…', pending_manual:'Action req.', paused:'Pausé' }[s] ?? s
}

const fmtDate = formatDateTime

function openCreate() {
  Object.assign(form, { platform:'facebook', external_product_id:'', external_url:'', close_threshold:0, is_auto_close_enabled:true, is_auto_reopen_enabled:false, is_price_sync_enabled:false })
  Object.assign(modal, { open:true, editing:false, editId:'', error:'' })
}

function openEdit(l: any) {
  Object.assign(form, { close_threshold: l.close_threshold, is_auto_close_enabled: l.is_auto_close_enabled, is_auto_reopen_enabled: l.is_auto_reopen_enabled, is_price_sync_enabled: l.is_price_sync_enabled, external_url: l.external_url ?? '' })
  Object.assign(modal, { open:true, editing:true, editId:l.id, error:'' })
}

function closeModal() { modal.open = false }

async function save() {
  modal.saving = true; modal.error = ''
  try {
    if (modal.editing) {
      await client.patch(`/api/marketplace/listings/${modal.editId}`, { close_threshold: form.close_threshold, is_auto_close_enabled: form.is_auto_close_enabled, is_auto_reopen_enabled: form.is_auto_reopen_enabled, is_price_sync_enabled: form.is_price_sync_enabled, external_url: form.external_url || null })
    } else {
      await client.post('/api/marketplace/listings', { platform: form.platform, external_product_id: form.external_product_id, external_url: form.external_url || null, close_threshold: form.close_threshold, is_auto_close_enabled: form.is_auto_close_enabled, is_auto_reopen_enabled: form.is_auto_reopen_enabled, is_price_sync_enabled: form.is_price_sync_enabled })
    }
    closeModal(); loadListings()
  } catch (e: any) { modal.error = e?.response?.data?.message ?? 'Erreur.' }
  finally { modal.saving = false }
}

async function deleteListing(id: string) {
  if (!confirm('Supprimer cette connexion ?')) return
  await client.delete(`/api/marketplace/listings/${id}`)
  loadListings()
}

async function markRead(id: string) {
  await client.patch(`/api/marketplace/alerts/${id}/read`)
  loadAlerts()
}

onMounted(() => { loadListings(); loadAlerts(); loadPlatforms() })
</script>

<style scoped>
.page-subtitle { color:var(--gray-500); font-size:var(--text-sm); margin-top:.2rem; }
.mono { font-family:ui-monospace,monospace; }
.mono-sm { font-family:ui-monospace,monospace; font-size:var(--text-xs); color:var(--gray-500); }
.text-dim { font-size:var(--text-xs); color:var(--gray-400); }
.req { color:var(--color-error); }
.hint { font-size:var(--text-xs); font-weight:400; color:var(--gray-400); }

/* Alert banner */
.alert-banner {
  display:flex; align-items:center; gap:.75rem;
  background:#fffbeb; border:1px solid #fcd34d; border-radius:var(--radius-md);
  padding:.75rem 1rem; margin-bottom:1rem; font-size:var(--text-sm); color:#92400e;
}

/* Tabs */
.tabs { display:flex; gap:.25rem; border-bottom:1px solid var(--gray-200); margin-bottom:1rem; }
.tab { padding:.5rem 1rem; border:none; background:none; font-size:var(--text-sm); font-weight:500; color:var(--gray-500); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; display:flex; align-items:center; gap:.375rem; transition:color .15s,border-color .15s; }
.tab.active { color:var(--gray-900); border-bottom-color:var(--gray-900); }
.tab-count { font-size:.65rem; font-weight:700; padding:1px 6px; border-radius:10px; background:var(--gray-200); color:var(--gray-600); }
.tab-count--red { background:#fef2f2; color:#ef4444; }

/* Platform */
.platform-cell { display:flex; align-items:center; gap:.5rem; }
.platform-icon { font-size:1.125rem; }
.platform-name { font-size:var(--text-sm); font-weight:500; color:var(--gray-800); }

.platform-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.625rem; }
.platform-btn {
  display:flex; align-items:center; gap:.5rem;
  padding:.625rem .875rem; border:1.5px solid var(--gray-200); border-radius:var(--radius-md);
  background:white; font-size:var(--text-sm); font-weight:500; color:var(--gray-700);
  cursor:pointer; transition:all .12s;
}
.platform-btn.active { border-color:var(--brand-primary); background:var(--brand-primary-bg); color:var(--brand-primary-dark); }
.platform-btn:hover:not(.active) { border-color:var(--gray-400); }

/* Sync badge */
.sync-badge { font-size:.6875rem; font-weight:700; padding:2px 8px; border-radius:var(--radius-sm); text-transform:uppercase; letter-spacing:.04em; }
.sync-active  { background:#dcfce7; color:#166534; }
.sync-closed  { background:var(--gray-100); color:var(--gray-500); }
.sync-error   { background:#fef2f2; color:#991b1b; }
.sync-syncing { background:#eff6ff; color:#1d4ed8; }
.sync-pending_manual { background:#fef9c3; color:#854d0e; }
.sync-paused  { background:var(--gray-100); color:var(--gray-500); }

/* Product cell */
.product-cell-sm { display:flex; flex-direction:column; gap:1px; }
.product-name-sm { font-size:var(--text-sm); font-weight:500; color:var(--gray-900); }
.variant-tag-sm  { font-size:.6875rem; color:var(--gray-400); }

.ext-link { display:inline-flex; align-items:center; gap:3px; font-size:var(--text-xs); font-family:ui-monospace,monospace; color:var(--brand-secondary); text-decoration:none; }
.ext-link:hover { text-decoration:underline; }

/* Alerts */
.alerts-list { display:flex; flex-direction:column; gap:.75rem; }
.alert-card { display:flex; align-items:flex-start; gap:.875rem; background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-md); padding:.875rem 1rem; }
.alert-card.unread { background:#fffbeb; border-color:#fcd34d; }
.alert-severity { font-size:1.25rem; flex-shrink:0; }
.alert-body { flex:1; min-width:0; }
.alert-msg { font-size:var(--text-sm); color:var(--gray-800); line-height:1.5; }
.alert-meta { font-size:var(--text-xs); color:var(--gray-400); margin-top:.25rem; }
.sev-warning { color:#f59e0b; }
.sev-error   { color:#ef4444; }

/* Switches (same as WarehouseView) */
.form-switches { display:flex; flex-direction:column; gap:.75rem; }
.switch-row { display:flex; align-items:center; gap:.75rem; cursor:pointer; }
.switch-input { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
.switch-track { width:38px; height:22px; border-radius:11px; background:var(--gray-300); position:relative; transition:background .2s; flex-shrink:0; }
.switch-input:checked + .switch-track { background:var(--brand-primary); }
.switch-thumb { position:absolute; width:18px; height:18px; border-radius:50%; background:white; top:2px; left:2px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.switch-input:checked + .switch-track .switch-thumb { transform:translateX(16px); }
.switch-label { font-size:var(--text-sm); color:var(--gray-700); }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:2000; padding:1rem; }
.modal { background:white; border-radius:var(--radius-lg); width:100%; max-width:520px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid var(--gray-100); }
.modal-header h3 { font-size:var(--text-base); font-weight:700; margin:0; }
.modal-close { background:none; border:none; font-size:1.125rem; color:var(--gray-400); cursor:pointer; }
.modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:1rem; }
.modal-footer { display:flex; justify-content:flex-end; gap:.75rem; padding:1rem 1.5rem; border-top:1px solid var(--gray-100); }

.row-actions { display:flex; gap:.375rem; justify-content:flex-end; }
.text-danger { color:var(--color-error); }
</style>
