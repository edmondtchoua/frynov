<template>
  <div class="audit-log">
    <div class="table-wrap" v-if="!loading && logs.length">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Action</th>
            <th>Utilisateur</th>
            <th>Tenant</th>
            <th>IP</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td><code class="log-action">{{ log.action }}</code></td>
            <td>{{ log.user?.name ?? log.user_id ?? '—' }}</td>
            <td class="log-id">{{ log.tenant_id ? log.tenant_id.slice(0, 8) + '…' : '—' }}</td>
            <td class="log-id">{{ log.ip_address ?? '—' }}</td>
            <td>{{ formatDate(log.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="loading" class="state-msg">Chargement…</div>
    <div v-else class="state-msg">Aucune entrée dans le journal.</div>

    <div class="pagination" v-if="meta && meta.last_page > 1">
      <button :disabled="page === 1" @click="page--; load()">← Précédent</button>
      <span>Page {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button :disabled="page === meta.last_page" @click="page++; load()">Suivant →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatDateTime } from '@/shared/utils/date'
import { adminService, type AuditLogEntry } from '../services/adminService'

const logs    = ref<AuditLogEntry[]>([])
const meta    = ref<any>(null)
const loading = ref(true)
const page    = ref(1)

async function load() {
  loading.value = true
  try {
    const result = await adminService.getAuditLogs(page.value)
    logs.value = result.data
    meta.value = result.meta
  } finally {
    loading.value = false
  }
}

const formatDate = formatDateTime

onMounted(load)
</script>

<style scoped>
.audit-log { display: flex; flex-direction: column; gap: 1rem; }

.table-wrap {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: auto;
}

.admin-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.admin-table th {
  text-align: left; font-weight: 500; color: #64748b;
  font-size: 0.75rem; padding: 0.625rem 1rem;
  border-bottom: 1px solid #e2e8f0; white-space: nowrap;
}
.admin-table td {
  padding: 0.625rem 1rem; border-bottom: 1px solid #f8fafc;
  color: #334155; vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }

.log-action { font-family: monospace; font-size: 0.8rem; color: #6366f1; background: #eef2ff; padding: 2px 6px; border-radius: 4px; }
.log-id     { font-family: monospace; font-size: 0.75rem; color: #94a3b8; }

.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }

.pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 1rem; font-size: 0.875rem; color: #475569;
}
.pagination button {
  border: 1px solid #e2e8f0; background: white; border-radius: 7px;
  padding: 0.375rem 0.75rem; font-size: 0.8125rem; cursor: pointer;
}
.pagination button:hover:not(:disabled) { background: #f8fafc; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
