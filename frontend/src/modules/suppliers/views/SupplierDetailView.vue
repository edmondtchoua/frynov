<template>
  <div class="page-container">
    <div class="page-header">
      <router-link to="/suppliers" class="btn btn-ghost">← Fournisseurs</router-link>
      <div>
        <h1 class="page-title">{{ supplier?.name ?? "..." }}</h1>
        <span v-if="supplier" class="badge" :class="supplier.status === 'active' ? 'badge-success' : 'badge-gray'">{{ supplier.status === 'active' ? 'Actif' : 'Inactif' }}</span>
      </div>
      <button v-if="!editing" class="btn btn-primary" @click="editing = true">Modifier</button>
      <div v-else class="header-actions">
        <button class="btn btn-secondary" @click="cancelEdit">Annuler</button>
        <button class="btn btn-primary" :disabled="saving" @click="save">{{ saving ? 'Enregistrement...' : 'Enregistrer' }}</button>
      </div>
    </div>

    <div v-if="loading" class="loading-center"><div class="spinner-sm"></div></div>
    <div v-else-if="!supplier" class="empty-state"><p>Fournisseur introuvable.</p></div>
    <div v-else class="supplier-grid">
      <div class="card">
        <h3 class="card-section-title">Informations générales</h3>
        <div v-if="!editing" class="info-grid">
          <div class="info-row"><span class="info-label">Code</span><code class="info-value">{{ supplier.code }}</code></div>
          <div class="info-row"><span class="info-label">Email</span><span class="info-value">{{ supplier.email ?? '—' }}</span></div>
          <div class="info-row"><span class="info-label">Téléphone</span><span class="info-value">{{ supplier.phone ?? '—' }}</span></div>
          <div class="info-row"><span class="info-label">Contact</span><span class="info-value">{{ supplier.contact ?? '—' }}</span></div>
          <div class="info-row"><span class="info-label">Conditions paiement</span><span class="info-value">{{ supplier.payment_terms ?? '—' }}</span></div>
          <div class="info-row"><span class="info-label">Notes</span><span class="info-value">{{ supplier.notes ?? '—' }}</span></div>
        </div>
        <div v-else class="edit-form">
          <div class="form-group"><label class="form-label">Nom *</label><input v-model="form.name" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Email</label><input v-model="form.email" type="email" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Téléphone</label><input v-model="form.phone" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Contact</label><input v-model="form.contact" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Conditions paiement</label><input v-model="form.payment_terms" class="form-input" /></div>
          <div class="form-group"><label class="form-label">Notes</label><textarea v-model="form.notes" class="form-input" rows="3"></textarea></div>
          <div class="form-group"><label class="form-label">Statut</label><select v-model="form.status" class="form-input"><option value="active">Actif</option><option value="inactive">Inactif</option></select></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue"
import { useRoute } from "vue-router"
import api from "@/services/api"

const route = useRoute()
const supplier = ref(null)
const loading = ref(false)
const editing = ref(false)
const saving = ref(false)
const form = reactive({ name: "", email: "", phone: "", contact: "", payment_terms: "", notes: "", status: "active" })

async function load() {
  loading.value = true
  try {
    const r = await api.get(`/api/suppliers/${route.params.id}`)
    supplier.value = r.data.data ?? r.data
    Object.assign(form, supplier.value)
  } catch { supplier.value = null } finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    const r = await api.put(`/api/suppliers/${route.params.id}`, form)
    supplier.value = r.data.data ?? r.data
    editing.value = false
  } finally { saving.value = false }
}

function cancelEdit() { Object.assign(form, supplier.value); editing.value = false }
onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 900px; margin: 0 auto; }
.page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.header-actions { display: flex; gap: 8px; margin-left: auto; }
.supplier-grid { display: grid; gap: 20px; }
.card-section-title { font-size: 1rem; font-weight: 600; margin: 0 0 16px; }
.info-grid { display: flex; flex-direction: column; gap: 12px; }
.info-row { display: grid; grid-template-columns: 140px 1fr; gap: 8px; align-items: start; }
.info-label { font-size: 0.875rem; color: var(--gray-500); font-weight: 500; padding-top: 2px; }
.info-value { font-size: 0.875rem; color: var(--gray-900); }
.edit-form { display: flex; flex-direction: column; gap: 12px; }
</style>
