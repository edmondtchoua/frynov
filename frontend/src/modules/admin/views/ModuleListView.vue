<template>
  <div class="module-list">
    <div class="module-grid" v-if="modules.length">
      <div v-for="mod in modules" :key="mod.id" class="module-card">
        <div class="module-card__head">
          <div class="module-icon" :style="{ background: lighten(mod.code) }" v-html="mod.icon_svg ?? ''"></div>
          <div>
            <div class="module-name">{{ mod.name }}</div>
            <div class="module-cat">{{ mod.category }}</div>
          </div>
          <span class="module-status" :class="`module-status--${mod.status}`">{{ mod.status }}</span>
        </div>
        <div class="module-stats">
          <span>{{ mod.total_activations }} activation{{ mod.total_activations !== 1 ? 's' : '' }}</span>
          <span v-if="mod.is_core" class="core-badge">Core</span>
        </div>
        <div class="module-actions">
          <button
            class="btn-sm"
            :class="mod.is_visible ? 'btn-sm--warn' : 'btn-sm--ok'"
            @click="toggleVisibility(mod)"
          >
            {{ mod.is_visible ? 'Masquer' : 'Afficher' }}
          </button>
          <select
            class="status-select"
            :value="mod.status"
            @change="updateStatus(mod, ($event.target as HTMLSelectElement).value)"
          >
            <option value="active">active</option>
            <option value="beta">beta</option>
            <option value="coming_soon">coming_soon</option>
            <option value="maintenance">maintenance</option>
            <option value="disabled">disabled</option>
          </select>
        </div>
      </div>
    </div>
    <div v-else-if="loading" class="state-msg">Chargement…</div>
    <div v-else class="state-msg">Aucun module trouvé.</div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminService, type AdminModule } from '../services/adminService'

const modules = ref<AdminModule[]>([])
const loading = ref(true)

const colorMap: Record<string, string> = {
  dashboard:     '#eef2ff',
  catalog:       '#f0fdf4',
  inventory:     '#fffbeb',
  orders:        '#eff6ff',
  customers:     '#f5f3ff',
  payments:      '#fef2f2',
  delivery:      '#ecfeff',
  suppliers:     '#fff7ed',
  import_export: '#f0fdfa',
  reports:       '#eef2ff',
}

function lighten(code: string) {
  return colorMap[code] ?? '#f8fafc'
}

async function load() {
  loading.value = true
  try {
    modules.value = await adminService.getModules()
  } finally {
    loading.value = false
  }
}

async function toggleVisibility(mod: AdminModule) {
  await adminService.updateModule(mod.id, { is_visible: !mod.is_visible })
  mod.is_visible = !mod.is_visible
}

async function updateStatus(mod: AdminModule, status: string) {
  await adminService.updateModule(mod.id, { status })
  mod.status = status as AdminModule['status']
}

onMounted(load)
</script>

<style scoped>
.module-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.module-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.module-card__head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.module-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.module-icon :deep(svg) { width: 18px; height: 18px; color: #475569; }

.module-name { font-size: 0.875rem; font-weight: 600; color: #0f172a; }
.module-cat  { font-size: 0.6875rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1px; }

.module-status {
  margin-left: auto;
  font-size: 0.625rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
}
.module-status--active       { background: #dcfce7; color: #166534; }
.module-status--beta         { background: #dbeafe; color: #1d4ed8; }
.module-status--coming_soon  { background: #fef9c3; color: #854d0e; }
.module-status--maintenance  { background: #fef3c7; color: #92400e; }
.module-status--disabled     { background: #f1f5f9; color: #94a3b8; }

.module-stats {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: #64748b;
}

.core-badge {
  background: #f0f9ff;
  color: #0284c7;
  font-size: 0.625rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.module-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-sm {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 5px;
  border: 1px solid #e2e8f0;
  background: white;
  cursor: pointer;
  color: #334155;
  transition: background 0.15s;
  white-space: nowrap;
}
.btn-sm:hover       { background: #f8fafc; }
.btn-sm--warn       { border-color: #fecaca; color: #ef4444; }
.btn-sm--warn:hover { background: #fff5f5; }
.btn-sm--ok         { border-color: #bbf7d0; color: #16a34a; }
.btn-sm--ok:hover   { background: #f0fdf4; }

.status-select {
  margin-left: auto;
  font-size: 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 5px;
  padding: 3px 6px;
  background: white;
  color: #334155;
  cursor: pointer;
}

.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }
</style>
