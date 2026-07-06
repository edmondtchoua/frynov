<template>
  <div class="demo-admin">
    <!-- KPIs -->
    <div class="demo-kpis">
      <div class="kpi"><div class="kpi-val">{{ stats.total }}</div><div class="kpi-lbl">Demandes</div></div>
      <div class="kpi"><div class="kpi-val">{{ stats.new }}</div><div class="kpi-lbl">À traiter</div></div>
      <div class="kpi"><div class="kpi-val">{{ stats.active_demo }}</div><div class="kpi-lbl">Démos actives</div></div>
      <div class="kpi"><div class="kpi-val">{{ stats.expired }}</div><div class="kpi-lbl">Expirées</div></div>
      <div class="kpi"><div class="kpi-val">{{ stats.converted }}</div><div class="kpi-lbl">Converties</div></div>
    </div>

    <!-- Filtres -->
    <div class="demo-filters">
      <input v-model.trim="q" class="form-input" placeholder="Rechercher (email, entreprise…)" @keyup.enter="reload" />
      <select v-model="status" class="form-input" @change="reload">
        <option value="">Tous les statuts</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
      </select>
      <button class="btn btn-secondary" @click="reload">Filtrer</button>
    </div>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <!-- Table -->
    <div class="demo-table-wrap">
      <table class="demo-table">
        <thead>
          <tr>
            <th>Prospect</th><th>Entreprise</th><th>Statut</th><th>Expire</th><th>Reçue</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="6" class="demo-empty">Chargement…</td></tr>
          <tr v-else-if="!rows.length"><td colspan="6" class="demo-empty">Aucune demande.</td></tr>
          <tr v-for="r in rows" :key="r.id">
            <td>
              <div class="demo-name">{{ r.first_name }} {{ r.last_name }}</div>
              <div class="demo-sub">{{ r.email }}</div>
            </td>
            <td>{{ r.company || '—' }}</td>
            <td><span class="badge" :class="'badge--' + r.status">{{ statusLabel(r.status) }}</span></td>
            <td>{{ fmt(r.demo_access_expires_at) }}</td>
            <td>{{ fmt(r.created_at) }}</td>
            <td class="demo-actions">
              <button v-if="canApprove(r)" class="btn-mini btn-mini--primary" :disabled="busyId === r.id"
                      @click="run(r, 'approve')">Approuver</button>
              <button v-if="isSent(r)" class="btn-mini" :disabled="busyId === r.id" @click="run(r, 'resend')">Renvoyer</button>
              <button v-if="isSent(r)" class="btn-mini" :disabled="busyId === r.id" @click="run(r, 'expire')">Expirer</button>
              <button v-if="canConvert(r)" class="btn-mini btn-mini--success" :disabled="busyId === r.id"
                      @click="run(r, 'convert')">Convertir</button>
              <button v-if="canApprove(r)" class="btn-mini btn-mini--danger" :disabled="busyId === r.id"
                      @click="reject(r)">Rejeter</button>
              <button class="btn-mini" @click="openNotes(r)">Notes</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal notes -->
    <div v-if="notesFor" class="demo-modal" @click.self="notesFor = null">
      <div class="demo-modal-card">
        <h3>Notes internes — {{ notesFor.email }}</h3>
        <textarea v-model="notesDraft" class="form-input" rows="5"></textarea>
        <div class="demo-modal-actions">
          <button class="btn btn-secondary" @click="notesFor = null">Fermer</button>
          <button class="btn btn-primary" :disabled="savingNotes" @click="saveNotes">Enregistrer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { demoAdminService, type DemoRequest, type DemoStats } from '@/modules/admin/services/demoAdminService'

const statuses = ['new', 'pending_review', 'approved', 'demo_access_sent', 'rejected', 'expired', 'converted_to_customer']
const labels: Record<string, string> = {
  new: 'Nouvelle', pending_review: 'À traiter', approved: 'Approuvée',
  demo_access_sent: 'Accès envoyé', rejected: 'Rejetée', expired: 'Expirée',
  converted_to_customer: 'Convertie',
}

const rows = ref<DemoRequest[]>([])
const stats = ref<DemoStats>({ total: 0, new: 0, sent: 0, expired: 0, rejected: 0, converted: 0, active_demo: 0 })
const loading = ref(false)
const busyId = ref<string | null>(null)
const error = ref('')
const q = ref('')
const status = ref('')

const notesFor = ref<DemoRequest | null>(null)
const notesDraft = ref('')
const savingNotes = ref(false)

function statusLabel(s: string) { return labels[s] ?? s }
function fmt(d?: string | null) { return d ? new Date(d).toLocaleDateString('fr-FR') : '—' }
function isSent(r: DemoRequest) { return r.status === 'demo_access_sent' }
function canApprove(r: DemoRequest) { return ['new', 'pending_review', 'approved'].includes(r.status) }
function canConvert(r: DemoRequest) { return ['demo_access_sent', 'expired'].includes(r.status) }

async function reload() {
  loading.value = true; error.value = ''
  try {
    const res = await demoAdminService.list({ status: status.value || undefined, q: q.value || undefined })
    rows.value = res.data.data
    stats.value = res.stats
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Erreur de chargement.'
  } finally {
    loading.value = false
  }
}

type Action = 'approve' | 'resend' | 'expire' | 'convert'
async function run(r: DemoRequest, action: Action) {
  busyId.value = r.id; error.value = ''
  try {
    await demoAdminService[action](r.id)
    await reload()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? "Action impossible."
  } finally {
    busyId.value = null
  }
}

async function reject(r: DemoRequest) {
  const reason = window.prompt('Motif du rejet (optionnel) :') ?? undefined
  busyId.value = r.id
  try { await demoAdminService.reject(r.id, reason); await reload() }
  catch (e: any) { error.value = e?.response?.data?.message ?? 'Rejet impossible.' }
  finally { busyId.value = null }
}

function openNotes(r: DemoRequest) { notesFor.value = r; notesDraft.value = r.internal_notes ?? '' }
async function saveNotes() {
  if (!notesFor.value) return
  savingNotes.value = true
  try { await demoAdminService.saveNotes(notesFor.value.id, notesDraft.value); notesFor.value = null; await reload() }
  catch (e: any) { error.value = e?.response?.data?.message ?? 'Enregistrement impossible.' }
  finally { savingNotes.value = false }
}

onMounted(reload)
</script>

<style scoped>
.demo-admin { display: flex; flex-direction: column; gap: 1.25rem; }
.demo-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; }
.kpi { background: #fff; border: 1px solid #eaecf0; border-radius: 10px; padding: 0.9rem 1rem; }
.kpi-val { font-size: 1.6rem; font-weight: 700; }
.kpi-lbl { color: #667085; font-size: 0.8rem; }
.demo-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.demo-filters .form-input { max-width: 260px; }
.demo-table-wrap { overflow-x: auto; background: #fff; border: 1px solid #eaecf0; border-radius: 10px; }
.demo-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.demo-table th, .demo-table td { text-align: left; padding: 0.7rem 0.9rem; border-bottom: 1px solid #f2f4f7; vertical-align: top; }
.demo-table th { color: #667085; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; }
.demo-name { font-weight: 600; }
.demo-sub { color: #667085; font-size: 0.82rem; }
.demo-empty { text-align: center; color: #98a2b3; padding: 2rem; }
.demo-actions { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.btn-mini { border: 1px solid #d0d5dd; background: #fff; border-radius: 6px; padding: 0.25rem 0.55rem; font-size: 0.8rem; cursor: pointer; }
.btn-mini--primary { background: #2563eb; color: #fff; border-color: transparent; }
.btn-mini--success { background: #059669; color: #fff; border-color: transparent; }
.btn-mini--danger { background: #fff; color: #d92d20; border-color: #fda29b; }
.badge { padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: #f2f4f7; color: #475467; }
.badge--demo_access_sent { background: #d1fae5; color: #059669; }
.badge--rejected { background: #fee4e2; color: #d92d20; }
.badge--expired { background: #fef0c7; color: #b54708; }
.badge--converted_to_customer { background: #e0eaff; color: #2563eb; }
.demo-modal { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: flex; align-items: center; justify-content: center; z-index: 50; }
.demo-modal-card { background: #fff; border-radius: 12px; padding: 1.5rem; width: 90%; max-width: 480px; }
.demo-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
</style>
