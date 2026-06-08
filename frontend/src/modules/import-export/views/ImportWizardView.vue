<template>
  <div class="import-wizard">

    <!-- ── Page Header ───────────────────────────────────────────────────── -->
    <div class="page-header">
      <div class="page-title">
        <button class="back-btn" @click="$router.push('/import/history')">← Historique</button>
        <h1>Assistant d'import</h1>
      </div>
    </div>

    <!-- ── Step Progress Bar ─────────────────────────────────────────────── -->
    <div class="wizard-steps">
      <div v-for="(step, i) in steps" :key="i" class="step-item" :class="{ active: currentStep === i, done: currentStep > i }">
        <div class="step-circle">
          <span v-if="currentStep > i">✓</span>
          <span v-else>{{ i + 1 }}</span>
        </div>
        <span class="step-label">{{ step }}</span>
        <div v-if="i < steps.length - 1" class="step-connector"></div>
      </div>
    </div>

    <!-- ── Step Content ───────────────────────────────────────────────────── -->
    <div class="wizard-body">

      <!-- ══ STEP 0: Choose type ══════════════════════════════════════════ -->
      <div v-if="currentStep === 0" class="step-panel">
        <h2>Que souhaitez-vous importer ?</h2>
        <p class="step-desc">Choisissez le type de données à importer dans Frynov ERP.</p>

        <div class="type-grid">
          <button v-for="t in entityTypes" :key="t.value" class="type-card" :class="{ selected: selectedType === t.value }" @click="selectedType = t.value">
            <div class="type-icon">{{ t.icon }}</div>
            <div class="type-info">
              <span class="type-name">{{ t.label }}</span>
              <span class="type-desc">{{ t.description }}</span>
            </div>
            <div v-if="selectedType === t.value" class="type-check">✓</div>
          </button>
        </div>

        <div class="mode-section">
          <h3>Mode d'import</h3>
          <div class="mode-grid">
            <label v-for="m in importModes" :key="m.value" class="mode-card" :class="{ selected: selectedMode === m.value }">
              <input v-model="selectedMode" type="radio" :value="m.value" class="mode-radio" />
              <div class="mode-info">
                <span class="mode-name">{{ m.label }}</span>
                <span class="mode-desc">{{ m.description }}</span>
              </div>
            </label>
          </div>
          <div v-if="selectedMode === 'simulate'" class="simulate-hint">
            ℹ️ La simulation analyse le fichier sans écrire de données. Recommandé avant tout import important.
          </div>
        </div>

        <div class="step-actions">
          <button class="btn btn-primary" :disabled="!selectedType" @click="nextStep">
            Continuer →
          </button>
        </div>
      </div>

      <!-- ══ STEP 1: Download template ════════════════════════════════════ -->
      <div v-if="currentStep === 1" class="step-panel">
        <h2>Téléchargez le modèle de fichier</h2>
        <p class="step-desc">Utilisez notre modèle Excel pour garantir un import sans erreur.</p>

        <div class="template-card">
          <div class="template-icon">📊</div>
          <div class="template-info">
            <span class="template-name">Modèle {{ entityTypeLabel }} — Frynov ERP</span>
            <span class="template-desc">Fichier Excel avec colonnes, exemples et consignes de remplissage.</span>
          </div>
          <button class="btn btn-outline" @click="downloadTemplate">
            ⬇️ Télécharger le modèle
          </button>
        </div>

        <div class="template-columns">
          <h4>Colonnes du modèle :</h4>
          <div class="column-tags">
            <span v-for="col in templateColumns" :key="col.name" class="col-tag" :class="{ required: col.required }">
              {{ col.name }}
              <small v-if="col.required">*</small>
            </span>
          </div>
          <p class="legend-note">* Champ obligatoire</p>
        </div>

        <div class="step-actions">
          <button class="btn btn-ghost" @click="prevStep">← Retour</button>
          <button class="btn btn-primary" @click="nextStep">J'ai préparé mon fichier →</button>
        </div>
      </div>

      <!-- ══ STEP 2: Upload file ═══════════════════════════════════════════ -->
      <div v-if="currentStep === 2" class="step-panel">
        <h2>Chargez votre fichier</h2>
        <p class="step-desc">Fichier Excel accepté (.xlsx, .xls). Taille max : 10 Mo.</p>

        <div class="upload-zone" :class="{ dragover: isDragging, 'has-file': selectedFile }"
             @dragover.prevent="isDragging = true"
             @dragleave="isDragging = false"
             @drop.prevent="onDrop">
          <div v-if="!selectedFile" class="upload-empty">
            <div class="upload-icon">📁</div>
            <p class="upload-hint">Glissez votre fichier ici ou</p>
            <label class="btn btn-outline btn-sm">
              Parcourir
              <input type="file" accept=".xlsx,.xls,.csv" class="file-input" @change="onFileSelect" />
            </label>
            <p class="upload-formats">Formats : .xlsx, .xls, .csv</p>
          </div>
          <div v-else class="upload-file-info">
            <span class="file-icon">📄</span>
            <div class="file-details">
              <span class="file-name">{{ selectedFile.name }}</span>
              <span class="file-size">{{ formatFileSize(selectedFile.size) }}</span>
            </div>
            <button class="remove-file" @click="selectedFile = null">✕</button>
          </div>
        </div>

        <div v-if="uploadError" class="error-banner">
          ⚠️ {{ uploadError }}
        </div>

        <div class="step-actions">
          <button class="btn btn-ghost" @click="prevStep">← Retour</button>
          <button class="btn btn-primary" :disabled="!selectedFile || uploading" @click="uploadFile">
            <span v-if="uploading" class="spinner-sm"></span>
            {{ uploading ? 'Analyse en cours…' : 'Analyser le fichier →' }}
          </button>
        </div>
      </div>

      <!-- ══ STEP 3: Column mapping ════════════════════════════════════════ -->
      <div v-if="currentStep === 3 && session" class="step-panel">
        <h2>Vérifiez le mapping des colonnes</h2>
        <p class="step-desc">
          Le système a détecté automatiquement vos colonnes.
          Corrigez si nécessaire avant de valider.
        </p>

        <div v-if="session.status === 'analyzing'" class="analyzing-state">
          <div class="spinner"></div>
          <p>Analyse du fichier en cours…</p>
          <p class="text-muted">Actualisation automatique toutes les 2 secondes.</p>
        </div>

        <div v-else class="mapping-table-wrapper">
          <table class="mapping-table">
            <thead>
              <tr>
                <th>Colonne du fichier</th>
                <th>→ Champ Frynov ERP</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(systemField, fileCol) in localMapping" :key="fileCol">
                <td class="file-col-name">{{ fileCol }}</td>
                <td>
                  <select v-model="localMapping[fileCol]" class="mapping-select">
                    <option value="">— Ignorer cette colonne —</option>
                    <option v-for="f in availableFields" :key="f.value" :value="f.value">{{ f.label }}</option>
                  </select>
                </td>
                <td>
                  <span v-if="localMapping[fileCol]" class="map-badge map-ok">✓ Mappé</span>
                  <span v-else class="map-badge map-skip">Ignoré</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="step-actions">
          <button class="btn btn-ghost" @click="prevStep">← Retour</button>
          <button class="btn btn-outline" @click="applyMapping" :disabled="mappingLoading">
            {{ mappingLoading ? 'Ré-analyse…' : '↻ Appliquer le mapping' }}
          </button>
          <button class="btn btn-primary" :disabled="session.status === 'analyzing'" @click="nextStep">
            Voir la prévisualisation →
          </button>
        </div>
      </div>

      <!-- ══ STEP 4: Preview + Validation + Approval ══════════════════════ -->
      <div v-if="currentStep === 4 && session" class="step-panel">
        <h2>Prévisualisation et approbation</h2>

        <!-- Stats bar -->
        <div class="stats-bar">
          <div class="stat-pill total">
            <span class="stat-value">{{ session.total_rows }}</span>
            <span class="stat-label">Lignes total</span>
          </div>
          <div class="stat-pill valid">
            <span class="stat-value">{{ session.valid_rows }}</span>
            <span class="stat-label">Valides</span>
          </div>
          <div class="stat-pill warning">
            <span class="stat-value">{{ session.warning_rows }}</span>
            <span class="stat-label">Avertissements</span>
          </div>
          <div class="stat-pill error">
            <span class="stat-value">{{ session.error_rows }}</span>
            <span class="stat-label">Erreurs</span>
          </div>
        </div>

        <!-- Mode indicator -->
        <div v-if="session.mode === 'simulate'" class="simulate-banner">
          🔬 Mode Simulation — aucune donnée ne sera écrite en base.
        </div>

        <!-- Row filter -->
        <div class="row-filters">
          <button v-for="f in rowFilters" :key="f.value" class="filter-btn" :class="{ active: rowFilter === f.value }" @click="rowFilter = f.value">
            {{ f.label }}
            <span class="filter-count">{{ rowCount(f.value) }}</span>
          </button>
        </div>

        <!-- Rows preview table -->
        <div class="preview-table-wrapper">
          <table class="preview-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Entité</th>
                <th>Action</th>
                <th>Statut</th>
                <th>Problèmes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredRows.length === 0">
                <td colspan="5" class="empty-rows">Aucune ligne dans ce filtre.</td>
              </tr>
              <tr v-for="row in filteredRows" :key="row.id" :class="`row-${row.status}`">
                <td class="row-num">{{ row.row_number }}</td>
                <td class="row-entity">{{ rowEntityName(row) }}</td>
                <td>
                  <span :class="['action-badge', `action-${row.action}`]">
                    {{ actionLabel(row.action) }}
                  </span>
                </td>
                <td>
                  <span :class="['status-badge', `status-${row.status}`]">{{ rowStatusLabel(row.status) }}</span>
                </td>
                <td class="row-issues">
                  <div v-if="row.errors?.length" class="issue-list">
                    <span v-for="e in row.errors" :key="e.field" class="issue-error">⚠ {{ e.message }}</span>
                  </div>
                  <div v-else-if="row.warnings?.length" class="issue-list">
                    <span v-for="w in row.warnings" :key="w.field" class="issue-warn">ℹ {{ w.message }}</span>
                  </div>
                  <span v-else class="no-issues">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Approval section -->
        <div v-if="canExecute" class="approval-section">
          <div class="approval-summary">
            <h3>Résumé de l'import</h3>
            <ul class="summary-list">
              <li>
                <strong>{{ session.valid_rows + session.warning_rows }}</strong> ligne(s) seront traitées
                <span v-if="session.mode === 'simulate'"> (simulation)</span>
              </li>
              <li v-if="session.error_rows > 0">
                <span class="text-error">{{ session.error_rows }} erreur(s) bloquante(s)</span> — ces lignes seront ignorées
              </li>
              <li>Type : <strong>{{ entityTypeLabel }}</strong></li>
              <li>Mode : <strong>{{ modeLabel }}</strong></li>
            </ul>
          </div>

          <div v-if="executeError" class="error-banner">⚠️ {{ executeError }}</div>

          <div class="approval-actions">
            <button class="btn btn-ghost" @click="prevStep">← Retour</button>
            <button class="btn btn-danger" @click="cancelSession">Annuler l'import</button>
            <button class="btn btn-primary" :disabled="executing" @click="executeImport">
              <span v-if="executing" class="spinner-sm"></span>
              {{ executing ? 'Import en cours…' : session.mode === 'simulate' ? '▶ Lancer la simulation' : '▶ Confirmer et importer' }}
            </button>
          </div>
        </div>

        <!-- Completed summary -->
        <div v-if="session.isTerminal || ['completed','partial','failed','cancelled'].includes(session.status)" class="result-section">
          <div :class="['result-card', `result-${session.status}`]">
            <div class="result-icon">{{ resultIcon }}</div>
            <div class="result-info">
              <h3>{{ STATUS_LABELS[session.status as ImportStatus] }}</h3>
              <p v-if="session.summary">
                {{ session.summary.created }} créé(s) · {{ session.summary.updated }} mis à jour ·
                {{ session.summary.skipped }} ignoré(s) · {{ session.summary.errors }} erreur(s)
              </p>
              <p v-if="session.error_message" class="error-msg">{{ session.error_message }}</p>
            </div>
          </div>
          <div class="result-actions">
            <button class="btn btn-outline" @click="importExportService.downloadReport(session!.id)">📄 Télécharger le rapport PDF</button>
            <button class="btn btn-primary" @click="$router.push('/import/history')">Voir l'historique</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch, onUnmounted } from 'vue'
import { useConfirm } from '@/composables/useConfirm'
import { pushToast } from '@/composables/useNotifications'
import { useRouter } from 'vue-router'
import { importExportService } from '../services/importExportService'
import type { ImportEntityType, ImportMode, ImportSession, ImportRow, ImportStatus, RowAction, RowStatus } from '../types'
import { ENTITY_LABELS, MODE_LABELS, STATUS_LABELS } from '../types'

const router = useRouter()

// ── Wizard State ─────────────────────────────────────────────────────────────
const steps = ['Type & mode', 'Modèle', 'Fichier', 'Mapping', 'Validation']
const currentStep = ref(0)

// Step 0
const selectedType = ref<ImportEntityType>('products')
const selectedMode = ref<ImportMode>('create_update')

// Step 2
const selectedFile = ref<File | null>(null)
const isDragging   = ref(false)
const uploading    = ref(false)
const uploadError  = ref('')

// Step 3
const session      = ref<ImportSession | null>(null)
const localMapping = reactive<Record<string, string | null>>({})
const mappingLoading = ref(false)
let   pollingInterval: ReturnType<typeof setInterval> | null = null

// Step 4
const rowFilter   = ref<string>('all')
const executing   = ref(false)
const executeError = ref('')

// ── Computed ─────────────────────────────────────────────────────────────────
const entityTypeLabel = computed(() => ENTITY_LABELS[selectedType.value])
const modeLabel       = computed(() => MODE_LABELS[selectedMode.value])

const entityTypes = [
  { value: 'products',  label: 'Produits',     icon: '📦', description: 'Catalogue produit, prix, catégories, fournisseurs.' },
  { value: 'customers', label: 'Clients',       icon: '👥', description: 'Base client, contacts, emails, téléphones.' },
  { value: 'suppliers', label: 'Fournisseurs',  icon: '🏭', description: 'Fournisseurs, contacts et conditions de paiement.' },
] as const

const importModes = [
  { value: 'create_update', label: 'Création + Mise à jour',   description: 'Crée les nouveaux, met à jour les existants.' },
  { value: 'create_only',   label: 'Création uniquement',       description: 'Ignore les données déjà existantes.' },
  { value: 'update_only',   label: 'Mise à jour uniquement',    description: 'Ignore les nouvelles données.' },
  { value: 'simulate',      label: 'Simulation',                description: 'Analyse sans écrire. Recommandé avant un import important.' },
] as const

const templateColumns = computed(() => {
  const cols: Record<string, Array<{ name: string; required: boolean }>> = {
    products:  [
      { name: 'SKU', required: true }, { name: 'Nom Produit', required: true }, { name: 'Prix', required: true },
      { name: 'Description', required: false }, { name: 'Coût', required: false }, { name: 'Code Barre', required: false },
      { name: 'Catégorie', required: false }, { name: 'Fournisseur', required: false }, { name: 'Statut', required: false },
    ],
    customers: [
      { name: 'Nom', required: true }, { name: 'Email', required: false },
      { name: 'Téléphone', required: false }, { name: 'Adresse', required: false }, { name: 'Notes', required: false },
    ],
    suppliers: [
      { name: 'Nom', required: true }, { name: 'Code Fournisseur', required: false },
      { name: 'Email', required: false }, { name: 'Téléphone', required: false },
      { name: 'Contact', required: false }, { name: 'Conditions Paiement', required: false }, { name: 'Statut', required: false },
    ],
  }
  return cols[selectedType.value] ?? []
})

const availableFields = computed(() => {
  const fields: Record<string, Array<{ value: string; label: string }>> = {
    products:  [
      { value: 'sku', label: 'SKU / Référence' }, { value: 'name', label: 'Nom du produit' },
      { value: 'price', label: 'Prix de vente' }, { value: 'cost', label: "Prix d'achat" },
      { value: 'description', label: 'Description' }, { value: 'barcode', label: 'Code-barres' },
      { value: 'category', label: 'Catégorie' }, { value: 'supplier', label: 'Fournisseur' },
      { value: 'weight_kg', label: 'Poids (kg)' }, { value: 'status', label: 'Statut' },
    ],
    customers: [
      { value: 'name', label: 'Nom' }, { value: 'email', label: 'Email' },
      { value: 'phone', label: 'Téléphone' }, { value: 'address', label: 'Adresse' }, { value: 'notes', label: 'Notes' },
    ],
    suppliers: [
      { value: 'name', label: 'Nom' }, { value: 'code', label: 'Code fournisseur' },
      { value: 'email', label: 'Email' }, { value: 'phone', label: 'Téléphone' },
      { value: 'contact_name', label: 'Contact principal' }, { value: 'payment_terms', label: 'Conditions paiement' },
      { value: 'notes', label: 'Notes' }, { value: 'status', label: 'Statut' },
    ],
  }
  return fields[selectedType.value] ?? []
})

const filteredRows = computed(() => {
  if (!session.value?.rows) return []
  if (rowFilter.value === 'all') return session.value.rows
  return session.value.rows.filter(r => {
    if (rowFilter.value === 'valid')   return r.status === 'valid'
    if (rowFilter.value === 'error')   return r.status === 'error'
    if (rowFilter.value === 'warning') return r.status === 'warning'
    if (rowFilter.value === 'create')  return r.action === 'create'
    if (rowFilter.value === 'update')  return r.action === 'update'
    return true
  })
})

const canExecute = computed(() =>
  session.value && ['awaiting_approval', 'analyzed'].includes(session.value.status)
)

const resultIcon = computed(() => {
  if (!session.value) return ''
  return { completed: '✅', partial: '⚠️', failed: '❌', cancelled: '🚫' }[session.value.status] ?? ''
})

const rowFilters = [
  { value: 'all',     label: 'Toutes' },
  { value: 'valid',   label: 'Valides' },
  { value: 'error',   label: 'Erreurs' },
  { value: 'warning', label: 'Avertissements' },
  { value: 'create',  label: 'Nouvelles données' },
  { value: 'update',  label: 'Mises à jour' },
]

// ── Navigation ────────────────────────────────────────────────────────────────
function nextStep() { currentStep.value++ }
function prevStep()  { currentStep.value-- }

// ── Step 1: Template ──────────────────────────────────────────────────────────
function downloadTemplate() {
  importExportService.downloadTemplate(selectedType.value)
}

// ── Step 2: Upload ────────────────────────────────────────────────────────────
function onFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.[0]) selectedFile.value = input.files[0]
}

function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files[0]
  if (file) selectedFile.value = file
}

async function uploadFile() {
  if (!selectedFile.value) return
  uploading.value = true
  uploadError.value = ''
  try {
    const s = await importExportService.upload(selectedFile.value, selectedType.value, selectedMode.value)
    session.value = s
    // Populate mapping
    Object.assign(localMapping, s.column_mapping ?? {})
    nextStep()
    // If still analyzing, start polling
    if (s.status === 'analyzing') startPolling()
  } catch (e: any) {
    uploadError.value = e?.response?.data?.message ?? 'Erreur lors de l\'upload.'
  } finally {
    uploading.value = false
  }
}

// ── Polling ───────────────────────────────────────────────────────────────────
function startPolling() {
  if (pollingInterval) return
  pollingInterval = setInterval(async () => {
    if (!session.value) return
    try {
      const s = await importExportService.getSession(session.value.id)
      session.value = s
      if (s.column_mapping) Object.assign(localMapping, s.column_mapping)
      if (s.status !== 'analyzing') stopPolling()
    } catch {}
  }, 2000)
}

function stopPolling() {
  if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null }
}

onUnmounted(() => stopPolling())

// ── Step 3: Mapping ───────────────────────────────────────────────────────────
async function applyMapping() {
  if (!session.value) return
  mappingLoading.value = true
  try {
    const s = await importExportService.updateMapping(session.value.id, { ...localMapping })
    session.value = s
    if (s.status === 'analyzing') startPolling()
  } finally {
    mappingLoading.value = false
  }
}

// ── Step 4: Execute ───────────────────────────────────────────────────────────
async function executeImport() {
  if (!session.value) return
  executing.value = true
  executeError.value = ''
  try {
    const s = await importExportService.execute(session.value.id)
    session.value = s
    if (s.status === 'importing') startPolling()
  } catch (e: any) {
    executeError.value = e?.response?.data?.message ?? 'Erreur lors de l\'import.'
  } finally {
    executing.value = false
  }
}

const { confirm } = useConfirm()

async function cancelSession() {
  if (!session.value) return
  if (!(await confirm({
    title: 'Annuler l\'import',
    message: 'Annuler cet import ?',
    confirmLabel: 'Annuler l\'import',
    danger: true,
  }))) return
  try {
    await importExportService.cancel(session.value.id)
    router.push('/import/history')
  } catch (e: any) {
    pushToast(e?.response?.data?.message ?? 'Annulation impossible.')
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function rowEntityName(row: ImportRow): string {
  const d = row.mapped_data
  if (!d) return `Ligne ${row.row_number}`
  return d.name ?? d.sku ?? d.code ?? `Ligne ${row.row_number}`
}

function actionLabel(action: RowAction | null): string {
  return { create: 'Créer', update: 'Mettre à jour', skip: 'Ignorer' }[action ?? ''] ?? '—'
}

function rowStatusLabel(status: RowStatus): string {
  return { valid: 'Valide', error: 'Erreur', warning: 'Avert.', imported: 'Importé', skipped: 'Ignoré', pending: 'En attente' }[status] ?? status
}

function rowCount(filter: string): number {
  if (!session.value?.rows) return 0
  if (filter === 'all') return session.value.rows.length
  if (filter === 'create') return session.value.rows.filter(r => r.action === 'create').length
  if (filter === 'update') return session.value.rows.filter(r => r.action === 'update').length
  return session.value.rows.filter(r => r.status === filter).length
}
</script>

<style scoped>
.import-wizard { padding: 24px; max-width: 900px; margin: 0 auto; }

.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.page-title  { display: flex; align-items: center; gap: 16px; }
.page-title h1 { font-size: 24px; font-weight: 700; color: var(--gray-900); }
.back-btn    { background: none; border: none; color: var(--brand-primary, #0d9488); font-size: 14px; cursor: pointer; font-weight: 500; }
.back-btn:hover { text-decoration: underline; }

/* Step progress */
.wizard-steps { display: flex; align-items: center; margin-bottom: 32px; overflow-x: auto; padding-bottom: 4px; }
.step-item    { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.step-circle  { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; background: var(--gray-200); color: var(--gray-500); flex-shrink: 0; }
.step-item.active .step-circle  { background: var(--brand-primary, #0d9488); color: white; }
.step-item.done .step-circle    { background: #d1fae5; color: #065f46; }
.step-label   { font-size: 13px; font-weight: 500; color: var(--gray-500); }
.step-item.active .step-label   { color: var(--brand-primary, #0d9488); font-weight: 700; }
.step-item.done .step-label     { color: #065f46; }
.step-connector { flex: 1; height: 2px; min-width: 24px; background: var(--gray-200); margin: 0 8px; }

/* Wizard body */
.wizard-body  { background: white; border-radius: 16px; border: 1px solid var(--gray-200); padding: 32px; }
.step-panel   { display: flex; flex-direction: column; gap: 24px; }
.step-panel h2 { font-size: 20px; font-weight: 700; color: var(--gray-900); }
.step-desc    { color: var(--gray-500); font-size: 14px; margin-top: -16px; }

/* Type grid */
.type-grid    { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
.type-card    { display: flex; align-items: center; gap: 12px; padding: 16px; border: 2px solid var(--gray-200); border-radius: 12px; background: white; cursor: pointer; transition: all 0.2s; text-align: left; }
.type-card:hover { border-color: var(--brand-primary, #0d9488); background: var(--gray-50); }
.type-card.selected { border-color: var(--brand-primary, #0d9488); background: #f0fdfa; }
.type-icon    { font-size: 28px; flex-shrink: 0; }
.type-name    { display: block; font-size: 15px; font-weight: 700; color: var(--gray-900); }
.type-desc    { display: block; font-size: 12px; color: var(--gray-500); margin-top: 2px; }
.type-check   { margin-left: auto; color: var(--brand-primary, #0d9488); font-size: 18px; font-weight: 700; }

/* Mode grid */
.mode-section h3 { font-size: 15px; font-weight: 700; color: var(--gray-700); margin-bottom: 10px; }
.mode-grid    { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.mode-card    { display: flex; align-items: flex-start; gap: 10px; padding: 14px; border: 2px solid var(--gray-200); border-radius: 10px; cursor: pointer; }
.mode-card.selected { border-color: var(--brand-primary, #0d9488); background: #f0fdfa; }
.mode-radio   { margin-top: 2px; accent-color: var(--brand-primary, #0d9488); }
.mode-name    { display: block; font-size: 14px; font-weight: 600; color: var(--gray-900); }
.mode-desc    { display: block; font-size: 12px; color: var(--gray-500); margin-top: 3px; }
.simulate-hint { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px 14px; border-radius: 8px; font-size: 13px; }

/* Template */
.template-card { display: flex; align-items: center; gap: 16px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 12px; padding: 20px; }
.template-icon { font-size: 36px; }
.template-name { display: block; font-size: 15px; font-weight: 700; color: var(--gray-900); }
.template-desc { display: block; font-size: 13px; color: var(--gray-500); margin-top: 4px; }
.template-info { flex: 1; }

.template-columns h4 { font-size: 14px; font-weight: 700; color: var(--gray-700); margin-bottom: 10px; }
.column-tags   { display: flex; flex-wrap: wrap; gap: 6px; }
.col-tag       { padding: 3px 10px; border-radius: 20px; font-size: 12px; background: var(--gray-100); color: var(--gray-600); font-weight: 500; }
.col-tag.required { background: #fee2e2; color: #991b1b; }
.col-tag small { margin-left: 2px; }
.legend-note   { font-size: 12px; color: var(--gray-400); margin-top: 8px; }

/* Upload */
.upload-zone  { border: 2px dashed var(--gray-300); border-radius: 12px; padding: 40px; text-align: center; transition: all 0.2s; }
.upload-zone.dragover, .upload-zone:hover { border-color: var(--brand-primary, #0d9488); background: #f0fdfa; }
.upload-zone.has-file { border-style: solid; border-color: #a7f3d0; background: #f0fdf4; }
.upload-empty  { display: flex; flex-direction: column; align-items: center; gap: 10px; }
.upload-icon   { font-size: 48px; }
.upload-hint   { font-size: 15px; color: var(--gray-600); }
.upload-formats { font-size: 12px; color: var(--gray-400); }
.file-input    { display: none; }
.upload-file-info { display: flex; align-items: center; gap: 14px; }
.file-icon    { font-size: 32px; }
.file-name    { display: block; font-size: 15px; font-weight: 600; color: var(--gray-900); }
.file-size    { display: block; font-size: 13px; color: var(--gray-500); }
.file-details { flex: 1; text-align: left; }
.remove-file  { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--gray-400); }

/* Mapping */
.analyzing-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 48px 0; color: var(--gray-500); }
.mapping-table-wrapper { overflow-x: auto; }
.mapping-table { width: 100%; border-collapse: collapse; }
.mapping-table th { padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; border-bottom: 1px solid var(--gray-200); background: var(--gray-50); }
.mapping-table td { padding: 10px 14px; border-bottom: 1px solid var(--gray-100); }
.file-col-name { font-family: monospace; font-size: 13px; color: var(--gray-700); font-weight: 600; }
.mapping-select { padding: 6px 10px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 13px; background: white; width: 100%; }
.map-badge    { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.map-ok       { background: #d1fae5; color: #065f46; }
.map-skip     { background: #f3f4f6; color: #6b7280; }

/* Preview */
.stats-bar    { display: flex; gap: 12px; flex-wrap: wrap; }
.stat-pill    { flex: 1; min-width: 100px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 14px 16px; text-align: center; }
.stat-pill.total   { border-color: var(--gray-300); }
.stat-pill.valid   { border-color: #a7f3d0; background: #f0fdf4; }
.stat-pill.warning { border-color: #fde68a; background: #fffbeb; }
.stat-pill.error   { border-color: #fca5a5; background: #fef2f2; }
.stat-value   { display: block; font-size: 24px; font-weight: 800; color: var(--gray-900); }
.stat-label   { display: block; font-size: 12px; color: var(--gray-500); margin-top: 2px; }

.simulate-banner { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; }

.row-filters { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-btn  { padding: 5px 12px; border: 1px solid var(--gray-200); border-radius: 20px; background: white; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.filter-btn.active { background: var(--brand-primary, #0d9488); color: white; border-color: var(--brand-primary, #0d9488); }
.filter-count { background: rgba(255,255,255,0.3); border-radius: 10px; padding: 0 6px; font-size: 11px; font-weight: 700; }
.filter-btn:not(.active) .filter-count { background: var(--gray-100); color: var(--gray-600); }

.preview-table-wrapper { overflow-x: auto; max-height: 360px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: 10px; }
.preview-table { width: 100%; border-collapse: collapse; }
.preview-table th { padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; border-bottom: 1px solid var(--gray-200); background: var(--gray-50); position: sticky; top: 0; }
.preview-table td { padding: 8px 14px; border-bottom: 1px solid var(--gray-100); font-size: 13px; }
.row-error td   { background: #fff5f5; }
.row-warning td { background: #fffbeb; }
.row-num   { font-weight: 700; color: var(--gray-500); width: 40px; }
.row-entity { font-weight: 600; color: var(--gray-900); }
.empty-rows { text-align: center; padding: 32px; color: var(--gray-400); }

.action-badge   { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.action-create  { background: #d1fae5; color: #065f46; }
.action-update  { background: #dbeafe; color: #1d4ed8; }
.action-skip    { background: #f3f4f6; color: #6b7280; }

.status-badge   { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.status-valid   { background: #d1fae5; color: #065f46; }
.status-error   { background: #fee2e2; color: #991b1b; }
.status-warning { background: #fef3c7; color: #92400e; }
.status-imported { background: #d1fae5; color: #065f46; }
.status-skipped  { background: #f3f4f6; color: #6b7280; }

.row-issues  { max-width: 300px; }
.issue-list  { display: flex; flex-direction: column; gap: 3px; }
.issue-error { font-size: 12px; color: #991b1b; }
.issue-warn  { font-size: 12px; color: #92400e; }
.no-issues   { color: var(--gray-300); }

.approval-section { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 12px; padding: 24px; }
.approval-summary h3 { font-size: 16px; font-weight: 700; color: var(--gray-900); margin-bottom: 12px; }
.summary-list { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.summary-list li { font-size: 14px; color: var(--gray-700); }
.text-error  { color: #dc2626; }
.approval-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }

.result-section { display: flex; flex-direction: column; gap: 20px; }
.result-card    { display: flex; align-items: flex-start; gap: 16px; padding: 20px; border-radius: 12px; }
.result-completed, .result-partial { background: #f0fdf4; border: 1px solid #a7f3d0; }
.result-failed   { background: #fef2f2; border: 1px solid #fca5a5; }
.result-cancelled { background: var(--gray-50); border: 1px solid var(--gray-200); }
.result-icon     { font-size: 36px; }
.result-info h3  { font-size: 17px; font-weight: 700; color: var(--gray-900); margin-bottom: 6px; }
.result-info p   { font-size: 14px; color: var(--gray-600); }
.error-msg       { color: #dc2626 !important; }
.result-actions  { display: flex; gap: 12px; flex-wrap: wrap; }

/* Step actions */
.step-actions { display: flex; gap: 12px; flex-wrap: wrap; padding-top: 8px; border-top: 1px solid var(--gray-100); }

/* Error banner */
.error-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; }

/* Buttons */
.btn         { padding: 10px 20px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.15s; }
.btn-primary { background: var(--brand-primary, #0d9488); color: white; }
.btn-primary:hover:not(:disabled) { background: #0b8070; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost   { background: transparent; border: 1px solid var(--gray-200); color: var(--gray-700); }
.btn-ghost:hover { background: var(--gray-50); }
.btn-outline  { background: white; border: 1px solid var(--brand-primary, #0d9488); color: var(--brand-primary, #0d9488); }
.btn-outline:hover { background: #f0fdfa; }
.btn-danger  { background: #dc2626; color: white; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-sm      { padding: 7px 14px; font-size: 13px; }

.spinner { width: 24px; height: 24px; border: 3px solid var(--gray-200); border-top-color: var(--brand-primary, #0d9488); border-radius: 50%; animation: spin 0.7s linear infinite; }
.spinner-sm { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.text-muted { color: var(--gray-400); font-size: 12px; }

@media (max-width: 640px) {
  .wizard-body { padding: 20px 16px; }
  .stats-bar   { gap: 8px; }
  .stat-pill   { min-width: 70px; padding: 10px; }
  .stat-value  { font-size: 18px; }
}
</style>
