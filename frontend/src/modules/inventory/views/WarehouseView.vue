<template>
  <div>
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h2>Entrepôts & Boutiques</h2>
        <p class="page-subtitle">{{ warehouses.length }} emplacement{{ warehouses.length !== 1 ? 's' : '' }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
          <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Nouvel emplacement
      </button>
    </div>

    <div v-if="loading" class="loading-center" style="min-height:260px">
      <span class="spinner-sm" style="width:28px;height:28px;border-width:3px"></span>
    </div>

    <div v-else-if="warehouses.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <path d="M8 28V14l12-7 12 7v14" stroke="var(--brand-primary)" stroke-width="2" stroke-linejoin="round"/>
        <path d="M16 28v-8h8v8" stroke="var(--brand-primary)" stroke-width="2" stroke-linejoin="round"/>
      </svg>
      <h3>Aucun emplacement</h3>
      <p>Créez votre premier entrepôt ou boutique pour gérer le stock par localisation.</p>
      <button class="btn btn-primary" @click="openCreate">Créer un emplacement</button>
    </div>

    <div v-else class="wh-grid">
      <div
        v-for="wh in warehouses"
        :key="wh.id"
        class="wh-card"
        :class="{ 'wh-card--default': wh.is_default }"
      >
        <div class="wh-card-header">
          <div class="wh-type-badge" :class="`type-${wh.type}`">
            {{ typeLabel(wh.type) }}
          </div>
          <div v-if="wh.is_default" class="wh-default-badge">Par défaut</div>
        </div>

        <div class="wh-name">{{ wh.name }}</div>
        <div class="wh-code">{{ wh.code }}</div>

        <div v-if="wh.address?.city || wh.address?.country" class="wh-address">
          {{ [wh.address?.city, wh.address?.country].filter(Boolean).join(', ') }}
        </div>

        <div class="wh-meta">
          <span :class="wh.is_active ? 'badge badge-success' : 'badge badge-gray'">
            {{ wh.is_active ? 'Actif' : 'Inactif' }}
          </span>
          <span v-if="wh.sells_online" class="badge badge-blue">En ligne</span>
        </div>

        <div class="wh-actions">
          <button class="btn btn-ghost btn-sm" @click="openEdit(wh)">Modifier</button>
          <button
            v-if="!wh.is_default"
            class="btn btn-ghost btn-sm"
            @click="setDefault(wh)"
            :disabled="settingDefault === wh.id"
          >{{ settingDefault === wh.id ? '…' : 'Définir par défaut' }}</button>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modal.editing ? 'Modifier' : 'Nouvel emplacement' }}</h3>
            <button class="modal-close" @click="closeModal">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Nom <span style="color:var(--color-error)">*</span></label>
              <input v-model="form.name" class="form-input" placeholder="Ex : Entrepôt Dakar" />
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Code <span style="color:var(--color-error)">*</span></label>
                <input v-model="form.code" class="form-input mono" placeholder="WH-DKR" style="text-transform:uppercase" />
              </div>
              <div class="form-group">
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                  <option value="warehouse">Entrepôt</option>
                  <option value="shop">Boutique physique</option>
                  <option value="dropship">Dropship</option>
                  <option value="virtual">Virtuel / En ligne</option>
                </select>
              </div>
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input v-model="form.phone" class="form-input" placeholder="+221 77 000 00 00" />
              </div>
              <div class="form-group">
                <label class="form-label">Devise</label>
                <select v-model="form.currency" class="form-input">
                  <option value="XOF">XOF — Franc CFA UEMOA</option>
                  <option value="XAF">XAF — Franc CFA CEMAC</option>
                  <option value="GHS">GHS — Cedi ghanéen</option>
                  <option value="NGN">NGN — Naira nigérian</option>
                  <option value="EUR">EUR — Euro</option>
                  <option value="USD">USD — Dollar US</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Ville / Pays</label>
              <div class="form-row-2">
                <input v-model="form.city" class="form-input" placeholder="Dakar" />
                <input v-model="form.country" class="form-input" placeholder="SN" maxlength="2" style="text-transform:uppercase" />
              </div>
            </div>
            <div class="form-switches">
              <label class="switch-row">
                <input v-model="form.is_active" type="checkbox" role="switch" class="switch-input" />
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">Emplacement actif</span>
              </label>
              <label class="switch-row">
                <input v-model="form.sells_online" type="checkbox" role="switch" class="switch-input" />
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-label">Expose le stock en ligne</span>
              </label>
            </div>
            <div v-if="modal.error" class="form-error" style="margin-top:.5rem">{{ modal.error }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" @click="closeModal">Annuler</button>
            <button class="btn btn-primary" :disabled="modal.saving" @click="save">
              <span v-if="modal.saving" class="spinner-sm spinner-white"></span>
              {{ modal.saving ? 'Enregistrement…' : (modal.editing ? 'Mettre à jour' : 'Créer') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import client from '@/api/client'

interface Warehouse {
  id: string; name: string; code: string; type: string; currency: string
  is_active: boolean; is_default: boolean; sells_online: boolean
  phone?: string | null; email?: string | null
  address?: { city?: string; country?: string } | null
}

const warehouses    = ref<Warehouse[]>([])
const loading       = ref(false)
const settingDefault = ref<string | null>(null)

const modal = reactive({ open: false, editing: false, editId: '', saving: false, error: '' })
const form  = reactive({ name:'', code:'', type:'warehouse', currency:'XOF', phone:'', city:'', country:'', is_active:true, sells_online:false })

async function load() {
  loading.value = true
  try {
    const res = await client.get('/api/inventory/warehouses')
    warehouses.value = res.data.data ?? []
  } catch { warehouses.value = [] } finally { loading.value = false }
}

function typeLabel(t: string) {
  return { warehouse:'Entrepôt', shop:'Boutique', dropship:'Dropship', virtual:'Virtuel' }[t] ?? t
}

function openCreate() {
  Object.assign(form, { name:'', code:'', type:'warehouse', currency:'XOF', phone:'', city:'', country:'', is_active:true, sells_online:false })
  Object.assign(modal, { open:true, editing:false, editId:'', error:'' })
}

function openEdit(wh: Warehouse) {
  Object.assign(form, {
    name: wh.name, code: wh.code, type: wh.type, currency: wh.currency,
    phone: wh.phone ?? '', city: wh.address?.city ?? '', country: wh.address?.country ?? '',
    is_active: wh.is_active, sells_online: wh.sells_online,
  })
  Object.assign(modal, { open:true, editing:true, editId:wh.id, error:'' })
}

function closeModal() { modal.open = false }

async function save() {
  if (!form.name.trim() || !form.code.trim()) { modal.error = 'Nom et code sont obligatoires.'; return }
  modal.saving = true; modal.error = ''
  const payload = {
    name: form.name, code: form.code.toUpperCase(), type: form.type,
    currency: form.currency, phone: form.phone || null,
    address: form.city || form.country ? { city: form.city, country: form.country.toUpperCase() } : null,
    is_active: form.is_active, sells_online: form.sells_online,
  }
  try {
    if (modal.editing) {
      await client.patch(`/api/inventory/warehouses/${modal.editId}`, payload)
    } else {
      await client.post('/api/inventory/warehouses', payload)
    }
    closeModal(); load()
  } catch (e: any) {
    modal.error = e?.response?.data?.message ?? 'Erreur lors de l\'enregistrement.'
  } finally { modal.saving = false }
}

async function setDefault(wh: Warehouse) {
  settingDefault.value = wh.id
  try { await client.patch(`/api/inventory/warehouses/${wh.id}/default`); load() }
  catch { /* ignore */ } finally { settingDefault.value = null }
}

onMounted(load)
</script>

<style scoped>
.page-subtitle { color:var(--gray-500); font-size:var(--text-sm); margin-top:.2rem; }
.mono { font-family:ui-monospace,monospace; }

.wh-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem; }

.wh-card {
  background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg);
  padding:1.5rem; display:flex; flex-direction:column; gap:.75rem;
  transition:border-color .15s, box-shadow .15s;
}
.wh-card:hover { border-color:var(--brand-primary-light); box-shadow:0 4px 16px rgba(0,0,0,.06); }
.wh-card--default { border-color:var(--brand-primary); background:var(--brand-primary-bg); }

.wh-card-header { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }

.wh-type-badge {
  font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
  padding:2px 8px; border-radius:var(--radius-sm);
}
.type-warehouse { background:#eff6ff; color:#1d4ed8; }
.type-shop      { background:#f0fdf4; color:#166534; }
.type-dropship  { background:#fef9c3; color:#854d0e; }
.type-virtual   { background:#fdf4ff; color:#7e22ce; }

.wh-default-badge { font-size:.65rem; font-weight:700; color:var(--brand-primary-dark); background:var(--brand-primary-bg); padding:2px 8px; border-radius:var(--radius-sm); border:1px solid var(--brand-primary-light); }

.wh-name    { font-size:var(--text-base); font-weight:700; color:var(--gray-900); }
.wh-code    { font-family:ui-monospace,monospace; font-size:var(--text-xs); color:var(--gray-400); }
.wh-address { font-size:var(--text-xs); color:var(--gray-500); }
.wh-meta    { display:flex; gap:.5rem; flex-wrap:wrap; }
.wh-actions { display:flex; gap:.5rem; margin-top:.25rem; }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:2000; padding:1rem; }
.modal { background:white; border-radius:var(--radius-lg); width:100%; max-width:520px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid var(--gray-100); }
.modal-header h3 { font-size:var(--text-base); font-weight:700; margin:0; }
.modal-close { background:none; border:none; font-size:1.125rem; color:var(--gray-400); cursor:pointer; }
.modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:1rem; }
.modal-footer { display:flex; justify-content:flex-end; gap:.75rem; padding:1rem 1.5rem; border-top:1px solid var(--gray-100); }

.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media (max-width:480px) { .form-row-2 { grid-template-columns:1fr; } }

.form-switches { display:flex; flex-direction:column; gap:.75rem; }
.switch-row { display:flex; align-items:center; gap:.75rem; cursor:pointer; }
.switch-input { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
.switch-track { width:38px; height:22px; border-radius:11px; background:var(--gray-300); position:relative; transition:background .2s; flex-shrink:0; }
.switch-input:checked + .switch-track { background:var(--brand-primary); }
.switch-thumb { position:absolute; width:18px; height:18px; border-radius:50%; background:white; top:2px; left:2px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.switch-input:checked + .switch-track .switch-thumb { transform:translateX(16px); }
.switch-label { font-size:var(--text-sm); color:var(--gray-700); }
</style>
