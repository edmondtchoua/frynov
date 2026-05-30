<template>
  <div>
    <div class="page-header">
      <h2>Tableau de bord</h2>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card" v-for="kpi in kpis" :key="kpi.label">
        <div class="kpi-icon">{{ kpi.icon }}</div>
        <div class="kpi-body">
          <div class="kpi-value">{{ kpi.value }}</div>
          <div class="kpi-label">{{ kpi.label }}</div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top: 1.5rem; text-align: center; padding: 3rem; color: #9ca3af;">
      <div style="font-size: 2rem; margin-bottom: 0.75rem;">📊</div>
      <p>Les graphiques et statistiques seront disponibles après connexion au backend.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
const kpis = [
  { icon: '💰', label: "CA du jour",     value: '—' },
  { icon: '🛒', label: 'Commandes',       value: '—' },
  { icon: '📦', label: 'Produits actifs', value: '—' },
  { icon: '⚠️',  label: 'Alertes stock',  value: '—' },
]
</script>

<style scoped>
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.kpi-card {
  background: white;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.kpi-icon { font-size: 2rem; }

.kpi-value {
  font-size: 1.6rem;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}

.kpi-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 0.25rem;
}
</style>
