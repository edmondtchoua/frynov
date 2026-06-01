<template>
  <div class="promo-list">

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <span class="total-chip">{{ meta?.total ?? 0 }} promotion{{ (meta?.total ?? 0) !== 1 ? 's' : '' }}</span>
      </div>
      <button class="btn-create" @click="openCreate">+ Nouvelle promotion</button>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table class="admin-table" v-if="!loading && promos.length">
        <thead>
          <tr>
            <th>Code</th>
            <th>Type</th>
            <th>Remise</th>
            <th>Plans</th>
            <th>Validité</th>
            <th>Utilisations</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in promos" :key="p.id">
            <td>
              <span class="promo-code">{{ p.code }}</span>
              <div v-if="p.description" class="promo-desc">{{ p.description }}</div>
            </td>
            <td class="dim">{{ p.discount_type === 'percent' ? 'Pourcentage' : 'Montant fixe' }}</td>
            <td class="bold">
              {{ p.discount_type === 'percent' ? p.discount_value + ' %' : formatCents(p.discount_value) }}
            </td>
            <td class="dim">
              {{ p.applicable_plans?.length ? p.applicable_plans.join(', ') : 'Tous' }}
            </td>
            <td class="dim">
              <template v-if="p.valid_from || p.valid_until">
                <span v-if="p.valid_from">{{ fmtDate(p.valid_from) }}</span>
                <span v-if="p.valid_from && p.valid_until"> → </span>
                <span v-if="p.valid_until">{{ fmtDate(p.valid_until) }}</span>
              </template>
              <span v-else>—</span>
            </td>
            <td>
              <span class="uses-chip">{{ p.current_uses }}{{ p.max_uses ? ' / ' + p.max_uses : '' }}</span>
            </td>
            <td>
              <span :class="p.is_active ? 'badge-on' : 'badge-off'">{{ p.is_active ? 'Actif' : 'Inactif' }}</span>
            </td>
            <td>
              <div class="action-group">
                <button class="btn-sm" @click="openEdit(p)">Modifier</button>
                <button
                  class="btn-sm"
                  :class="p.is_active ? 'btn-sm--warn' : 'btn-sm--ok'"
                  @click="toggleActive(p)"
                >{{ p.is_active ? 'Désactiver' : 'Activer' }}</button>
                <button class="btn-sm btn-sm--danger" @click="doDelete(p)" v-if="p.current_uses === 0">
                  Suppr.
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else-if="loading" class="state-msg">Chargement…</div>
      <div v-else class="state-msg">Aucune promotion créée.</div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="meta && meta.last_page > 1">
      <button :disabled="page === 1" @click="page--; load()">← Précédent</button>
      <span>Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button :disabled="page === meta.last_page" @click="page++; load()">Suivant →</button>
    </div>

    <!-- ── Create / Edit modal ─────────────────────────────────────────────── -->
    <div v-if="modal.open" class="modal-overlay" @click.self="modal.open = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ modal.editing ? 'Modifier la promotion' : 'Nouvelle promotion' }}</h3>
          <button class="modal-close" @click="modal.open = false">✕</button>
        </div>

        <div class="modal-body">
          <div class="form-row">
            <label>Code *</label>
            <input
              v-model="form.code"
              :disabled="!!modal.editing"
              class="form-input"
              placeholder="ex. PROMO20"
              style="text-transform:uppercase"
            />
          </div>
          <div class="form-row">
            <label>Description</label>
            <input v-model="form.description" class="form-input" placeholder="Description courte (optionnel)" />
          </div>
          <div class="form-row-2">
            <div class="form-row">
              <label>Type de remise *</label>
              <select v-model="form.discount_type" class="form-select">
                <option value="percent">Pourcentage (%)</option>
                <option value="fixed_cents">Montant fixe (centimes)</option>
              </select>
            </div>
            <div class="form-row">
              <label>Valeur *</label>
              <input v-model.number="form.discount_value" type="number" min="1" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <label>Plans applicables <span class="hint">(vide = tous)</span></label>
            <input
              v-model="form.applicable_plans_raw"
              class="form-input"
              placeholder="ex. starter,pro (séparés par virgules)"
            />
          </div>
          <div class="form-row-2">
            <div class="form-row">
              <label>Valide à partir du</label>
              <input v-model="form.valid_from" type="datetime-local" class="form-input" />
            </div>
            <div class="form-row">
              <label>Valide jusqu'au</label>
              <input v-model="form.valid_until" type="datetime-local" class="form-input" />
            </div>
          </div>
          <div class="form-row">
            <label>Utilisations max <span class="hint">(vide = illimité)</span></label>
            <input v-model.number="form.max_uses" type="number" min="1" class="form-input" />
          </div>
          <div class="form-row form-row-check">
            <label>
              <input v-model="form.is_active" type="checkbox" />
              Activer immédiatement
            </label>
          </div>
          <div v-if="modal.error" class="form-error">{{ modal.error }}</div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="modal.open = false">Annuler</button>
          <button class="btn-submit" :disabled="modal.saving" @click="savePromo">
            {{ modal.saving ? 'Enregistrement…' : (modal.editing ? 'Mettre à jour' : 'Créer') }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { adminService, type AdminPromotion } from '../services/adminService'

// ── State ─────────────────────────────────────────────────────────────────────
const promos  = ref<AdminPromotion[]>([])
const meta    = ref<any>(null)
const loading = ref(true)
const page    = ref(1)

// ── Modal / form state ────────────────────────────────────────────────────────
const modal = reactive({
  open:    false,
  editing: null as AdminPromotion | null,
  saving:  false,
  error:   '',
})

const form = reactive({
  code:                '',
  description:         '',
  discount_type:       'percent' as 'percent' | 'fixed_cents',
  discount_value:      10,
  applicable_plans_raw: '',
  valid_from:          '',
  valid_until:         '',
  max_uses:            '' as number | '',
  is_active:           true,
})

// ── Data loading ──────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const result = await adminService.getPromotions(page.value)
    promos.value = result.data
    meta.value   = result.meta
  } finally {
    loading.value = false
  }
}

// ── Actions ───────────────────────────────────────────────────────────────────
function openCreate() {
  Object.assign(form, {
    code: '', description: '', discount_type: 'percent',
    discount_value: 10, applicable_plans_raw: '',
    valid_from: '', valid_until: '', max_uses: '', is_active: true,
  })
  modal.editing = null
  modal.error   = ''
  modal.open    = true
}

function openEdit(p: AdminPromotion) {
  Object.assign(form, {
    code:                 p.code,
    description:          p.description ?? '',
    discount_type:        p.discount_type,
    discount_value:       p.discount_value,
    applicable_plans_raw: p.applicable_plans?.join(',') ?? '',
    valid_from:           p.valid_from ? toLocalInput(p.valid_from) : '',
    valid_until:          p.valid_until ? toLocalInput(p.valid_until) : '',
    max_uses:             p.max_uses ?? '',
    is_active:            p.is_active,
  })
  modal.editing = p
  modal.error   = ''
  modal.open    = true
}

async function savePromo() {
  if (!form.code && !modal.editing) { modal.error = 'Le code est obligatoire.'; return }
  if (!form.discount_value)         { modal.error = 'La valeur de remise est obligatoire.'; return }

  modal.saving = true
  modal.error  = ''

  const payload: Partial<AdminPromotion> = {
    code:             form.code.toUpperCase(),
    description:      form.description || undefined,
    discount_type:    form.discount_type,
    discount_value:   form.discount_value,
    applicable_plans: form.applicable_plans_raw
      ? form.applicable_plans_raw.split(',').map(s => s.trim()).filter(Boolean)
      : undefined,
    valid_from:  form.valid_from  || undefined,
    valid_until: form.valid_until || undefined,
    max_uses:    form.max_uses !== '' ? Number(form.max_uses) : undefined,
    is_active:   form.is_active,
  }

  try {
    if (modal.editing) {
      await adminService.updatePromotion(modal.editing.id, payload)
    } else {
      await adminService.createPromotion(payload)
    }
    modal.open = false
    await load()
  } catch (err: any) {
    modal.error = err?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    modal.saving = false
  }
}

async function toggleActive(p: AdminPromotion) {
  await adminService.updatePromotion(p.id, { is_active: !p.is_active })
  await load()
}

async function doDelete(p: AdminPromotion) {
  if (!confirm(`Supprimer la promotion "${p.code}" ?`)) return
  await adminService.deletePromotion(p.id)
  await load()
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatCents(cents: number): string {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 })
    .format(cents / 100)
}

function toLocalInput(iso: string): string {
  // Convert ISO datetime to local datetime-local input format
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

onMounted(load)
</script>

<style scoped>
.promo-list { display: flex; flex-direction: column; gap: 1rem; }

/* ── Toolbar ─────────────────────────────────────────────────────────────── */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.toolbar-left { display: flex; align-items: center; gap: 0.75rem; }
.total-chip {
  font-size: 0.8125rem;
  color: #94a3b8;
}
.btn-create {
  background: #1d4ed8;
  color: white;
  border: none;
  border-radius: 7px;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
}
.btn-create:hover { opacity: 0.88; }

/* ── Table ───────────────────────────────────────────────────────────────── */
.table-wrap {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: auto;
}
.admin-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.admin-table th {
  text-align: left;
  font-weight: 500;
  color: #64748b;
  font-size: 0.75rem;
  padding: 0.625rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.admin-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f8fafc;
  color: #334155;
  vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }

.promo-code { font-family: ui-monospace, monospace; font-weight: 700; font-size: 0.875rem; color: #0f172a; }
.promo-desc { font-size: 0.6875rem; color: #94a3b8; margin-top: 2px; }
.dim  { color: #64748b; font-size: 0.8125rem; }
.bold { font-weight: 700; color: #0f172a; }
.uses-chip { font-size: 0.75rem; color: #475569; }

.badge-on  { display: inline-block; font-size: 0.6875rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; background: #dcfce7; color: #166534; text-transform: uppercase; }
.badge-off { display: inline-block; font-size: 0.6875rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; background: #f1f5f9; color: #94a3b8; text-transform: uppercase; }

/* ── Action buttons ──────────────────────────────────────────────────────── */
.action-group { display: flex; gap: 0.375rem; }
.btn-sm {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 3px 10px;
  border-radius: 5px;
  border: 1px solid #e2e8f0;
  background: white;
  cursor: pointer;
  color: #334155;
  transition: background 0.15s;
}
.btn-sm:hover         { background: #f8fafc; }
.btn-sm--warn         { border-color: #fecaca; color: #ef4444; }
.btn-sm--warn:hover   { background: #fff5f5; }
.btn-sm--ok           { border-color: #bbf7d0; color: #16a34a; }
.btn-sm--ok:hover     { background: #f0fdf4; }
.btn-sm--danger       { border-color: #e2e8f0; color: #9ca3af; }
.btn-sm--danger:hover { background: #fff5f5; border-color: #fecaca; color: #ef4444; }

/* ── Pagination ──────────────────────────────────────────────────────────── */
.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; font-size: 0.875rem; color: #475569; }
.pagination button { border: 1px solid #e2e8f0; background: white; border-radius: 7px; padding: 0.375rem 0.75rem; font-size: 0.8125rem; cursor: pointer; }
.pagination button:hover:not(:disabled) { background: #f8fafc; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}
.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 540px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}
.modal-header h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; }
.modal-close { background: none; border: none; font-size: 1.125rem; color: #94a3b8; cursor: pointer; padding: 0.25rem; }
.modal-close:hover { color: #334155; }

.modal-body { padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; }

.form-row { display: flex; flex-direction: column; gap: 0.375rem; }
.form-row label { font-size: 0.8125rem; font-weight: 500; color: #475569; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-row-check { flex-direction: row; align-items: center; gap: 0.5rem; }
.form-row-check label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.hint { font-size: 0.75rem; font-weight: 400; color: #94a3b8; }

.form-input, .form-select {
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: #0f172a;
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
  box-sizing: border-box;
}
.form-input:focus, .form-select:focus { border-color: #94a3b8; }
.form-input:disabled { background: #f8fafc; color: #94a3b8; }

.form-error { color: #ef4444; font-size: 0.8125rem; background: #fff5f5; border: 1px solid #fecaca; border-radius: 6px; padding: 0.5rem 0.75rem; }

.btn-cancel {
  padding: 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  background: white;
  color: #475569;
  font-size: 0.875rem;
  cursor: pointer;
}
.btn-cancel:hover { background: #f8fafc; }
.btn-submit {
  padding: 0.5rem 1.25rem;
  border: none;
  border-radius: 7px;
  background: #1d4ed8;
  color: white;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}
.btn-submit:hover:not(:disabled) { opacity: 0.88; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
