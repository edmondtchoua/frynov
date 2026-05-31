<template>
  <div class="import-history-view">

    <!-- ── Header ────────────────────────────────────────────────────────── -->
    <div class="page-header">
      <div class="page-title">
        <h1>Historique des imports</h1>
        <span class="count-badge">{{ meta.total }} session{{ meta.total !== 1 ? 's' : '' }}</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="$router.push('/import/new')">
          ⬆️ Nouvel import
        </button>
        <div class="export-menu">
          <span class="export-label">Exporter :</span>
          <button class="btn btn-outline btn-sm" @click="doExport('products', 'xlsx')">📊 Produits</button>
          <button class="btn btn-outline btn-sm" @click="doExport('customers', 'xlsx')">👥 Clients</button>
          <button class="btn btn-outline btn-sm" @click="doExport('suppliers', 'xlsx')">🏭 Fournisseurs</button>
        </div>
      </div>
    </div>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <div class="filters-bar">
      <select v-model="typeFilter" class="filter-select" @change="load(1)">
        <option value="">Tous les types</option>
        <option value="products">Produits</option>
        <option value="customers">Clients</option>
        <option value="suppliers">Fournisseurs</option>
      </select>
      <select v-model="statusFilter" class="filter-select" @change="load(1)">
        <option value="">Tous les statuts</option>
        <option value="completed">Terminé</option>
        <option value="partial">Partiel</option>
        <option value="awaiting_approval">En attente d'approbation</option>
        <option value="analyzed">Analysé (erreurs)</option>
        <option value="failed">Échoué</option>
        <option value="cancelled">Annulé</option>
      </select>
    </div>

    <!-- ── Table ─────────────────────────────────────────────────────────── -->
    <div class="table-card">
      <div v-if="loading" class="table-loading">
        <div class="spinner"></div>
        <span>Chargement…</span>
      </div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Fichier</th>
            <th>Mode</th>
            <th>Statut</th>
            <th class="text-right">Total</th>
            <th class="text-right">Importés</th>
            <th class="text-right">Erreurs</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="sessions.length === 0">
            <td colspan="9" class="empty-state">
              <div class="empty-inner">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="40" height="40">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <p>Aucun import trouvé</p>
                <button class="btn btn-primary btn-sm" @click="$router.push('/import/new')">Premier import</button>
              </div>
            </td>
          </tr>

          <tr v-for="s in sessions" :key="s.id" class="table-row" @click="openDetail(s)" style="cursor:pointer">
            <td>
              <span :class="['type-badge', `type-${s.type}`]">
                {{ TYPE_ICONS[s.type] }} {{ ENTITY_LABELS[s.type] }}
              </span>
            </td>
            <td class="col-filename">
              <span class="filename">{{ s.original_filename }}</span>
            </td>
            <td>
              <span class="mode-text">{{ MODE_SHORT[s.mode] }}</span>
            </td>
            <td>
              <span :class="['status-badge', `status-${s.status}`]">{{ STATUS_LABELS[s.status] }}</span>
            </td>
            <td class="text-right text-muted">{{ s.total_rows }}</td>
            <td class="text-right">
              <span v-if="s.imported_rows > 0" class="text-success">{{ s.imported_rows }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="text-right">
              <span v-if="s.error_rows > 0" class="text-error">{{ s.error_rows }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="text-muted date-col">{{ fmtDate(s.created_at) }}</td>
            <td @click.stop>
              <div class="action-group">
                <button v-if="s.status === 'awaiting_approval'" class="btn-action btn-approve" title="Continuer" @click="continueSession(s)">▶</button>
                <button v-if="['completed','partial'].includes(s.status)" class="btn-action btn-report" title="Rapport PDF" @click="importExportService.downloadReport(s.id)">📄</button>
                <button v-if="canCancel(s)" class="btn-action btn-delete" title="Annuler" @click.stop="cancelSession(s)">✕</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="pagination">
        <button class="page-btn" :disabled="meta.current_page === 1" @click="load(meta.current_page - 1)">←</button>
        <span class="page-info">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="page-btn" :disabled="meta.current_page === meta.last_page" @click="load(meta.current_page + 1)">→</button>
      </div>
    </div>

    <!-- ── Session Detail Modal ───────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="detailSession" class="modal-overlay" @click.self="detailSession = null">
        <div class="modal-card">
          <div class="modal-header">
            <div>
              <h2>Détail de l'import</h2>
              <p class="modal-sub">{{ detailSession.original_filename }}</p>
            </div>
            <button class="modal-close" @click="detailSession = null">✕</button>
          </div>

          <div class="modal-body">
            <!-- Summary stats -->
            <div class="detail-stats">
              <div class="detail-stat"><span class="ds-value">{{ detailSession.total_rows }}</span><span class="ds-label">Total</span></div>
              <div class="detail-stat valid"><span class="ds-value">{{ detailSession.valid_rows }}</span><span class="ds-label">Valides</span></div>
              <div class="detail-stat warning"><span class="ds-value">{{ detailSession.warning_rows }}</span><span class="ds-label">Avert.</span></div>
              <div class="detail-stat error"><span class="ds-value">{{ detailSession.error_rows }}</span><span class="ds-label">Erreurs</span></div>
              <div class="detail-stat imported"><span class="ds-value">{{ detailSession.imported_rows }}</span><span class="ds-label">Importés</span></div>
              <div class="detail-stat skipped"><span class="ds-value">{{ detailSession.skipped_rows }}</span><span class="ds-label">Ignorés</span></div>
            </div>

            <!-- Summary breakdown -->
            <div v-if="detailSession.summary" class="summary-box">
              <div class="summary-row"><span>Créés</span><strong>{{ detailSession.summary.created }}</strong></div>
              <div class="summary-row"><span>Mis à jour</span><strong>{{ detailSession.summary.updated }}</strong></div>
              <div class="summary-row"><span>Ignorés</span><strong>{{ detailSession.summary.skipped }}</strong></div>
              <div class="summary-row"><span>Erreurs</span><strong class="text-error">{{ detailSession.summary.errors }}</strong></div>
            </div>

            <div v-if="detailSession.error_message" class="error-banner">
              ⚠️ {{ detailSession.error_message }}
            </div>

            <div class="meta-grid">
              <div class="meta-item"><span>Type</span><strong>{{ ENTITY_LABELS[detailSession.type] }}</strong></div>
              <div class="meta-item"><span>Mode</span><strong>{{ MODE_LABELS[detailSession.mode] }}</strong></div>
              <div class="meta-item"><span>Statut</span><strong>{{ STATUS_LABELS[detailSession.status] }}</strong></div>
              <div class="meta-item"><span>Créé le</span><strong>{{ fmtDate(detailSession.created_at) }}</strong></div>
              <div v-if="detailSession.completed_at" class="meta-item"><span>Terminé le</span><strong>{{ fmtDate(detailSession.completed_at) }}</strong></div>
            </div>
          </div>

          <div class="modal-footer">
            <button v-if="['completed','partial'].includes(detailSession.status)" class="btn btn-outline" @click="importExportService.downloadReport(detailSession.id)">
              📄 Rapport PDF
            </button>
            <button v-if="detailSession.status === 'awaiting_approval'" class="btn btn-primary" @click="continueSession(detailSession)">
              ▶ Continuer l'import
            </button>
            <button class="btn btn-ghost" @click="detailSession = null">Fermer</button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { importExportService } from '../services/importExportService'
import type { ImportSession } from '../types'
import { ENTITY_LABELS, MODE_LABELS, STATUS_LABELS } from '../types'

const router = useRouter()

const sessions      = ref<ImportSession[]>([])
const loading       = ref(false)
const meta          = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const typeFilter    = ref('')
const statusFilter  = ref('')
const detailSession = ref<ImportSession | null>(null)

const TYPE_ICONS: Record<string, string> = { products: '📦', customers: '👥', suppliers: '🏭' }
const MODE_SHORT: Record<string, string> = {
  create_update: 'C+U', create_only: 'Créer', update_only: 'MàJ', simulate: 'Simu.'
}

async function load(page = 1) {
  loading.value = true
  try {
    const res = await importExportService.history({
      type: typeFilter.value || undefined,
      status: statusFilter.value || undefined,
      page,
    })
    sessions.value = res.data
    Object.assign(meta, res.meta)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())

function openDetail(s: ImportSession) {
  detailSession.value = s
}

function continueSession(s: ImportSession) {
  detailSession.value = null
  router.push({ path: '/import/new', query: { session: s.id } })
}

function canCancel(s: ImportSession): boolean {
  return !['completed', 'partial', 'failed', 'cancelled'].includes(s.status)
}

async function cancelSession(s: ImportSession) {
  if (!confirm(`Annuler l'import « ${s.original_filename} » ?`)) return
  try {
    await importExportService.cancel(s.id)
    await load(meta.current_page)
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'Annulation impossible.')
  }
}

function doExport(type: string, format: string) {
  importExportService.exportExcel(type as any, {})
}

function fmtDate(iso: string): string {
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
}
</script>

<style scoped>
.import-history-view { padding: 24px; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.page-title  { display: flex; align-items: center; gap: 12px; }
.page-title h1 { font-size: 24px; font-weight: 700; color: var(--gray-900); }
.count-badge { background: var(--brand-primary-light, #e0f2f1); color: var(--brand-primary, #0d9488); padding: 2px 10px; border-radius: 12px; font-size: 13px; font-weight: 600; }
.header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.export-menu { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.export-label { font-size: 13px; color: var(--gray-500); }

.filters-bar { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-select { padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; outline: none; background: white; cursor: pointer; }

.table-card     { background: white; border-radius: 12px; border: 1px solid var(--gray-200); overflow: hidden; }
.table-loading  { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 48px; color: var(--gray-500); }

.data-table     { width: 100%; border-collapse: collapse; }
.data-table th  { padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--gray-200); background: var(--gray-50); }
.data-table td  { padding: 12px 14px; font-size: 13px; color: var(--gray-700); border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
.table-row:hover td { background: var(--gray-50); }
.text-right { text-align: right; }
.text-muted { color: var(--gray-400); }
.text-success { color: #059669; font-weight: 700; }
.text-error   { color: #dc2626; font-weight: 700; }

.empty-state  { text-align: center; padding: 64px 24px !important; }
.empty-inner  { display: flex; flex-direction: column; align-items: center; gap: 12px; color: var(--gray-400); }
.empty-inner p { font-size: 15px; color: var(--gray-500); }

.type-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: var(--gray-100); color: var(--gray-600); }
.col-filename .filename { font-family: monospace; font-size: 12px; color: var(--gray-700); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
.mode-text { font-size: 12px; color: var(--gray-500); font-weight: 600; }
.date-col  { white-space: nowrap; font-size: 12px; }

.status-badge   { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-draft             { background: #f3f4f6; color: #6b7280; }
.status-analyzing         { background: #dbeafe; color: #1d4ed8; }
.status-analyzed          { background: #fef3c7; color: #92400e; }
.status-awaiting_approval { background: #ede9fe; color: #5b21b6; }
.status-importing         { background: #dbeafe; color: #1d4ed8; }
.status-completed         { background: #d1fae5; color: #065f46; }
.status-partial           { background: #fef3c7; color: #92400e; }
.status-failed            { background: #fee2e2; color: #991b1b; }
.status-cancelled         { background: #f3f4f6; color: #6b7280; }

.action-group { display: flex; gap: 4px; }
.btn-action   { background: none; border: none; padding: 5px 8px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: background 0.15s; }
.btn-action:hover { background: var(--gray-100); }
.btn-approve  { color: var(--brand-primary, #0d9488); }
.btn-report   { color: #6b7280; }
.btn-delete   { color: #dc2626; }

.pagination  { display: flex; align-items: center; justify-content: center; gap: 16px; padding: 16px; border-top: 1px solid var(--gray-100); }
.page-btn    { padding: 6px 14px; border: 1px solid var(--gray-200); background: white; border-radius: 8px; cursor: pointer; font-size: 14px; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info   { font-size: 14px; color: var(--gray-500); }

.spinner { width: 20px; height: 20px; border: 2px solid var(--gray-200); border-top-color: var(--brand-primary, #0d9488); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-card    { background: white; border-radius: 16px; width: 100%; max-width: 560px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
.modal-header  { display: flex; align-items: flex-start; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--gray-200); }
.modal-header h2 { font-size: 18px; font-weight: 700; color: var(--gray-900); }
.modal-sub     { font-size: 13px; color: var(--gray-500); margin-top: 3px; font-family: monospace; }
.modal-close   { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--gray-400); }
.modal-body    { padding: 24px; display: flex; flex-direction: column; gap: 20px; }

.detail-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.detail-stat  { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 8px; padding: 12px; text-align: center; }
.detail-stat.valid    { background: #f0fdf4; border-color: #a7f3d0; }
.detail-stat.warning  { background: #fffbeb; border-color: #fde68a; }
.detail-stat.error    { background: #fef2f2; border-color: #fca5a5; }
.detail-stat.imported { background: #f0fdfa; border-color: #5eead4; }
.detail-stat.skipped  { background: #f9fafb; border-color: #e5e7eb; }
.ds-value { display: block; font-size: 22px; font-weight: 800; color: var(--gray-900); }
.ds-label { display: block; font-size: 11px; color: var(--gray-500); margin-top: 2px; }

.summary-box { background: var(--gray-50); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; }
.summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
.summary-row span { color: var(--gray-500); }
.summary-row strong { color: var(--gray-900); }

.error-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; }

.meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.meta-item { display: flex; flex-direction: column; gap: 2px; }
.meta-item span { font-size: 12px; color: var(--gray-400); }
.meta-item strong { font-size: 13px; color: var(--gray-800); }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--gray-100); flex-wrap: wrap; }

/* Buttons */
.btn         { padding: 9px 18px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
.btn-primary { background: var(--brand-primary, #0d9488); color: white; }
.btn-primary:hover { background: #0b8070; }
.btn-ghost   { background: transparent; border: 1px solid var(--gray-200); color: var(--gray-700); }
.btn-ghost:hover { background: var(--gray-50); }
.btn-outline  { background: white; border: 1px solid var(--brand-primary, #0d9488); color: var(--brand-primary, #0d9488); }
.btn-outline:hover { background: #f0fdfa; }
.btn-sm      { padding: 6px 14px; font-size: 13px; }
</style>
