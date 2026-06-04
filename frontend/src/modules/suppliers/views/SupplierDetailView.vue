<template>
  <div class="supplier-detail">

    <!-- Back + Header -->
    <div class="page-header">
      <RouterLink to="/suppliers" class="back-link">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Fournisseurs
      </RouterLink>
      <div class="header-actions" v-if="supplier">
        <span class="status-badge" :class="supplier.status === 'active' ? 'badge-active' : 'badge-inactive'">
          {{ supplier.status === 'active' ? 'Actif' : 'Inactif' }}
        </span>
        <button v-if="isManagerOrAbove && !editing" class="btn btn-secondary" @click="startEdit">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <path d="M11.5 2.5a2.121 2.121 0 013 3L5 15H2v-3L11.5 2.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
          </svg>
          Modifier
        </button>
        <button v-if="isManagerOrAbove && !editing" class="btn btn-danger-outline" @click="showDeleteConfirm = true">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 9a1 1 0 001 1h6a1 1 0 001-1l1-9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Supprimer
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center">
      <span class="spinner-sm"></span> Chargement…
    </div>

    <!-- Error -->
    <div v-else-if="fetchError" class="card error-state">
      <p>{{ fetchError }}</p>
      <button class="btn btn-secondary" @click="load">Réessayer</button>
    </div>

    <!-- Content -->
    <template v-else-if="supplier">
      <div class="card supplier-card">
        <!-- Card header -->
        <div class="card-header">
          <div class="supplier-avatar">{{ supplier.name.charAt(0).toUpperCase() }}</div>
          <div class="supplier-title">
            <h2 class="supplier-name">{{ supplier.name }}</h2>
            <span v-if="supplier.code" class="code-badge">{{ supplier.code }}</span>
          </div>
        </div>

        <!-- Read mode -->
        <div v-if="!editing" class="info-grid">
          <div class="info-item">
            <label>Personne de contact</label>
            <span>{{ supplier.contact_name ?? '—' }}</span>
          </div>
          <div class="info-item">
            <label>Email</label>
            <a v-if="supplier.email" :href="`mailto:${supplier.email}`" class="link">{{ supplier.email }}</a>
            <span v-else>—</span>
          </div>
          <div class="info-item">
            <label>Téléphone</label>
            <a v-if="supplier.phone" :href="`tel:${supplier.phone}`" class="link">{{ supplier.phone }}</a>
            <span v-else>—</span>
          </div>
          <div class="info-item">
            <label>Conditions de paiement</label>
            <span>{{ supplier.payment_terms ?? '—' }}</span>
          </div>
          <div v-if="formattedAddress" class="info-item info-item--wide">
            <label>Adresse</label>
            <span>{{ formattedAddress }}</span>
          </div>
          <div v-if="supplier.notes" class="info-item info-item--wide">
            <label>Notes</label>
            <span class="notes-text">{{ supplier.notes }}</span>
          </div>
          <div class="info-item">
            <label>Créé le</label>
            <span>{{ formatDate(supplier.created_at) }}</span>
          </div>
          <div class="info-item">
            <label>Dernière modification</label>
            <span>{{ formatDate(supplier.updated_at) }}</span>
          </div>
        </div>

        <!-- Edit mode -->
        <form v-else class="edit-form" @submit.prevent="saveEdit">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Nom <span class="required">*</span></label>
              <input v-model="form.name" class="form-input" required />
            </div>
            <div class="form-group">
              <label class="form-label">Personne de contact</label>
              <input v-model="form.contact_name" class="form-input" placeholder="Nom du contact" />
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input v-model="form.email" type="email" class="form-input" placeholder="email@fournisseur.com" />
            </div>
            <div class="form-group">
              <label class="form-label">Téléphone</label>
              <input v-model="form.phone" class="form-input" placeholder="+221 77 000 00 00" />
            </div>
            <div class="form-group">
              <label class="form-label">Conditions de paiement</label>
              <input v-model="form.payment_terms" class="form-input" placeholder="ex : 30 jours nets" />
            </div>
            <div class="form-group">
              <label class="form-label">Statut</label>
              <select v-model="form.status" class="form-input">
                <option value="active">Actif</option>
                <option value="inactive">Inactif</option>
              </select>
            </div>
            <div class="form-group form-group--wide">
              <label class="form-label">Notes</label>
              <textarea v-model="form.notes" class="form-input" rows="3" placeholder="Informations supplémentaires…" />
            </div>
          </div>
          <div class="edit-actions">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving" class="spinner-sm"></span>
              {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
            <button type="button" class="btn btn-ghost" @click="cancelEdit">Annuler</button>
            <p v-if="saveError" class="form-error">{{ saveError }}</p>
          </div>
        </form>
      </div>
    </template>

    <!-- Delete confirm modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal-box">
        <h3 class="modal-title">Supprimer ce fournisseur ?</h3>
        <p class="modal-desc">Cette action est irréversible. Le fournisseur <strong>{{ supplier?.name }}</strong> sera définitivement supprimé.</p>
        <div class="modal-actions">
          <button class="btn btn-danger" @click="doDelete" :disabled="deleting">
            {{ deleting ? 'Suppression…' : 'Supprimer' }}
          </button>
          <button class="btn btn-ghost" @click="showDeleteConfirm = false">Annuler</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { supplierService } from '../services/supplierService'
import { usePermission } from '@/composables/usePermission'
import type { Supplier, UpdateSupplierPayload } from '../types'

const route  = useRoute()
const router = useRouter()
const { isManagerOrAbove } = usePermission()

const id = route.params.id as string

const supplier          = ref<Supplier | null>(null)
const loading           = ref(false)
const fetchError        = ref<string | null>(null)
const editing           = ref(false)
const saving            = ref(false)
const saveError         = ref<string | null>(null)
const deleting          = ref(false)
const showDeleteConfirm = ref(false)

const form = ref<UpdateSupplierPayload & { contact_name?: string }>({
  name: '', contact_name: '', email: '', phone: '', payment_terms: '', notes: '', status: 'active',
})

const formattedAddress = computed(() => {
  const a = supplier.value?.address
  if (!a) return ''
  return [a.street, a.city, a.zip, a.country].filter(Boolean).join(', ')
})

async function load() {
  loading.value    = true
  fetchError.value = null
  try {
    const res        = await supplierService.get(id)
    supplier.value   = res.data
  } catch {
    fetchError.value = 'Impossible de charger ce fournisseur.'
  } finally {
    loading.value = false
  }
}

function startEdit() {
  if (!supplier.value) return
  form.value = {
    name:          supplier.value.name,
    contact_name:  supplier.value.contact_name ?? '',
    email:         supplier.value.email ?? '',
    phone:         supplier.value.phone ?? '',
    payment_terms: supplier.value.payment_terms ?? '',
    notes:         supplier.value.notes ?? '',
    status:        supplier.value.status,
  }
  saveError.value = null
  editing.value   = true
}

function cancelEdit() {
  editing.value   = false
  saveError.value = null
}

async function saveEdit() {
  saving.value    = true
  saveError.value = null
  try {
    const res      = await supplierService.update(id, form.value)
    supplier.value = res.data
    editing.value  = false
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Erreur lors de la mise à jour.'
  } finally {
    saving.value = false
  }
}

async function doDelete() {
  deleting.value = true
  try {
    await supplierService.delete(id)
    router.push('/suppliers')
  } catch {
    showDeleteConfirm.value = false
  } finally {
    deleting.value = false
  }
}


onMounted(load)
</script>

<style scoped>
.supplier-detail { padding: 24px; }

.page-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--gray-500);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  transition: color 0.15s;
}
.back-link:hover { color: var(--gray-900); }

.header-actions {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.status-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.65rem;
  border-radius: 20px;
}
.badge-active   { background: #d1fae5; color: #065f46; }
.badge-inactive { background: var(--gray-100); color: var(--gray-500); }

.btn-danger-outline {
  background: none;
  border: 1px solid #fca5a5;
  color: #ef4444;
}
.btn-danger-outline:hover { background: #fef2f2; border-color: #ef4444; }

/* Card */
.card {
  background: white;
  border-radius: var(--radius-lg);
  border: 1px solid var(--gray-200);
  padding: 1.5rem;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--gray-100);
}

.supplier-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  font-weight: 700;
  flex-shrink: 0;
}

.supplier-title { display: flex; align-items: center; gap: 0.75rem; }
.supplier-name  { font-size: 1.25rem; font-weight: 700; color: var(--gray-900); margin: 0; }
.code-badge     { font-size: 0.75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-600); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm); }

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.25rem 2rem;
}
.info-item { display: flex; flex-direction: column; gap: 0.25rem; }
.info-item label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray-400); }
.info-item span, .info-item a { font-size: 0.875rem; color: var(--gray-800); }
.info-item--wide { grid-column: 1 / -1; }
.notes-text { white-space: pre-line; color: var(--gray-600); }
.link { color: var(--brand-primary); text-decoration: none; }
.link:hover { text-decoration: underline; }

/* Edit form */
.edit-form   { margin-top: 0.5rem; }
.form-grid   { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.form-group  { display: flex; flex-direction: column; gap: 4px; }
.form-group--wide { grid-column: 1 / -1; }
.form-label  { font-size: 0.8rem; font-weight: 600; color: var(--gray-600); }
.required    { color: #ef4444; }
.form-input  { padding: 0.45rem 0.7rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.875rem; background: white; transition: border-color 0.15s; width: 100%; box-sizing: border-box; }
.form-input:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 3px var(--brand-primary-bg); }
textarea.form-input { resize: vertical; }
.edit-actions { display: flex; align-items: center; gap: 0.75rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--gray-100); }
.form-error  { font-size: 0.8rem; color: #ef4444; margin: 0; }

/* Loading / Error */
.loading-center { display: flex; align-items: center; gap: 0.5rem; padding: 3rem; justify-content: center; color: var(--gray-500); }
.error-state    { padding: 2rem; text-align: center; color: var(--gray-600); }

/* Modal */
.modal-overlay  { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal-box      { background: white; border-radius: var(--radius-lg); padding: 1.5rem; max-width: 420px; width: 90%; box-shadow: var(--shadow-lg); }
.modal-title    { margin: 0 0 0.75rem; font-size: 1.1rem; font-weight: 700; color: var(--gray-900); }
.modal-desc     { margin: 0 0 1.5rem; font-size: 0.875rem; color: var(--gray-600); }
.modal-actions  { display: flex; gap: 0.75rem; }
.btn-danger     { background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; }
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner-sm {
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
  vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
