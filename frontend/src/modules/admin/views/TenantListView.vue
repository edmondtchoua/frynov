<template>
  <div class="tenant-list">

    <!-- Filters -->
    <div class="filters">
      <input
        v-model="search"
        type="search"
        class="filter-input"
        placeholder="Rechercher un tenant…"
        @input="debouncedLoad"
      />
      <select v-model="statusFilter" class="filter-select" @change="load">
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="suspended">Suspendu</option>
        <option value="cancelled">Annulé</option>
      </select>
      <select v-model="planFilter" class="filter-select" @change="load">
        <option value="">Tous les plans</option>
        <option value="starter">Starter</option>
        <option value="pro">Pro</option>
        <option value="enterprise">Enterprise</option>
      </select>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table class="admin-table" v-if="!loading && tenants.length">
        <thead>
          <tr>
            <th>Tenant</th>
            <th>Plan</th>
            <th>Abonnement</th>
            <th>Statut</th>
            <th>Créé le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in tenants" :key="t.id">
            <td>
              <div class="tenant-name">{{ t.name }}</div>
              <div class="tenant-slug">{{ t.slug }}</div>
            </td>
            <td><span class="plan-badge plan-badge--{{ t.plan }}">{{ t.plan }}</span></td>
            <td>
              <span class="sub-badge" :class="`sub-badge--${t.subscription_status}`">
                {{ t.subscription_status }}
              </span>
            </td>
            <td>
              <span class="status-dot" :class="`status-dot--${t.status}`"></span>
              {{ t.status }}
            </td>
            <td>{{ formatDate(t.created_at) }}</td>
            <td>
              <div class="action-group">
                <RouterLink :to="`/admin/tenants/${t.id}`" class="btn-sm">Détails</RouterLink>
                <button
                  v-if="t.status === 'active'"
                  class="btn-sm btn-sm--warn"
                  @click="suspend(t)"
                >Suspendre</button>
                <button
                  v-else-if="t.status === 'suspended'"
                  class="btn-sm btn-sm--ok"
                  @click="reactivate(t)"
                >Réactiver</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else-if="loading" class="state-msg">Chargement…</div>
      <div v-else class="state-msg">Aucun tenant trouvé.</div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="meta && meta.last_page > 1">
      <button :disabled="page === 1" @click="page--; load()">← Précédent</button>
      <span>Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button :disabled="page === meta.last_page" @click="page++; load()">Suivant →</button>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import { RouterLink } from 'vue-router'
import { adminService, type AdminTenant } from '../services/adminService'

const tenants      = ref<AdminTenant[]>([])
const meta         = ref<any>(null)
const loading      = ref(true)
const search       = ref('')
const statusFilter = ref('')
const planFilter   = ref('')
const page         = ref(1)

let debounceTimer: ReturnType<typeof setTimeout>

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 350)
}

async function load() {
  loading.value = true
  try {
    const result = await adminService.getTenants({
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      plan:   planFilter.value || undefined,
      page:   page.value,
    })
    tenants.value = result.data
    meta.value    = result.meta
  } finally {
    loading.value = false
  }
}

async function suspend(tenant: AdminTenant) {
  if (!confirm(`Suspendre "${tenant.name}" ?`)) return
  await adminService.suspendTenant(tenant.id)
  load()
}

async function reactivate(tenant: AdminTenant) {
  await adminService.reactivateTenant(tenant.id)
  load()
}


onMounted(load)
</script>

<style scoped>
.tenant-list { display: flex; flex-direction: column; gap: 1rem; }

.filters {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.filter-input, .filter-select {
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: white;
  color: #0f172a;
  outline: none;
  transition: border-color 0.15s;
}
.filter-input { min-width: 240px; flex: 1; }
.filter-input:focus, .filter-select:focus { border-color: #94a3b8; }

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

.tenant-name { font-weight: 500; color: #0f172a; }
.tenant-slug { font-size: 0.6875rem; color: #94a3b8; margin-top: 1px; }

.plan-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  background: #f1f5f9;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.sub-badge {
  font-size: 0.6875rem;
  font-weight: 500;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
}
.sub-badge--trialing  { background: #dbeafe; color: #1d4ed8; }
.sub-badge--active    { background: #dcfce7; color: #166534; }
.sub-badge--suspended { background: #fef9c3; color: #854d0e; }
.sub-badge--cancelled { background: #f1f5f9; color: #94a3b8; }

.status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
.status-dot--active    { background: #22c55e; }
.status-dot--suspended { background: #f59e0b; }
.status-dot--cancelled { background: #e2e8f0; }

.action-group { display: flex; gap: 0.375rem; }

.btn-sm {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 3px 10px;
  border-radius: 5px;
  border: 1px solid #e2e8f0;
  background: white;
  cursor: pointer;
  text-decoration: none;
  color: #334155;
  transition: background 0.15s;
}
.btn-sm:hover         { background: #f8fafc; }
.btn-sm--warn         { border-color: #fecaca; color: #ef4444; }
.btn-sm--warn:hover   { background: #fff5f5; }
.btn-sm--ok           { border-color: #bbf7d0; color: #16a34a; }
.btn-sm--ok:hover     { background: #f0fdf4; }

.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  font-size: 0.875rem;
  color: #475569;
}
.pagination button {
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 7px;
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
  cursor: pointer;
  transition: background 0.15s;
}
.pagination button:hover:not(:disabled) { background: #f8fafc; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
