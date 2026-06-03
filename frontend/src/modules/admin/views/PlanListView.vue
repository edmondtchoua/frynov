<template>
  <div class="plan-list">
    <div class="plan-grid" v-if="plans.length">
      <div v-for="plan in plans" :key="plan.id" class="plan-card" :class="{ 'plan-card--inactive': !plan.is_active }">
        <div class="plan-card__header">
          <div>
            <div class="plan-name">{{ plan.name }}</div>
            <div class="plan-code">{{ plan.code }}</div>
          </div>
          <span class="plan-vis-badge" :class="plan.is_public ? 'plan-vis-badge--public' : 'plan-vis-badge--private'">
            {{ plan.is_public ? 'Public' : 'Privé' }}
          </span>
        </div>

        <div class="plan-price">
          <template v-if="plan.price_monthly_cents > 0">
            <span class="plan-price__amount">{{ formatPrice(plan.price_monthly_cents) }}</span>
            <span class="plan-price__period">/ mois</span>
          </template>
          <template v-else>
            <span class="plan-price__free">Gratuit</span>
          </template>
        </div>

        <div class="plan-limits">
          <span>{{ plan.max_users || '∞' }} utilisateurs</span>
          <span>{{ plan.max_products || '∞' }} produits</span>
          <span>{{ plan.max_monthly_orders || '∞' }} commandes/mois</span>
          <span>{{ plan.trial_days }}j d'essai</span>
        </div>

        <ul class="plan-features">
          <li v-for="f in plan.features" :key="f">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M2 6l3 3 5-5" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ f }}
          </li>
        </ul>
      </div>
    </div>
    <div v-else-if="loading" class="state-msg">Chargement…</div>
    <div v-else class="state-msg">Aucun plan trouvé.</div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatMoney } from '@/shared/utils/money'
import { adminService, type AdminPlan } from '../services/adminService'

const plans   = ref<AdminPlan[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    plans.value = await adminService.getPlans()
  } finally {
    loading.value = false
  }
}

const formatPrice = (cents: number) => formatMoney(cents)

onMounted(load)
</script>

<style scoped>
.plan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.plan-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.plan-card--inactive { opacity: 0.6; }

.plan-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.plan-name { font-size: 1.0625rem; font-weight: 700; color: #0f172a; }
.plan-code { font-size: 0.6875rem; color: #94a3b8; font-family: monospace; margin-top: 2px; text-transform: uppercase; }

.plan-vis-badge {
  font-size: 0.625rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
}
.plan-vis-badge--public  { background: #dcfce7; color: #166534; }
.plan-vis-badge--private { background: #f1f5f9; color: #64748b; }

.plan-price { display: flex; align-items: baseline; gap: 0.25rem; }
.plan-price__amount { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
.plan-price__period { font-size: 0.8125rem; color: #64748b; }
.plan-price__free   { font-size: 1.25rem; font-weight: 700; color: #16a34a; }

.plan-limits {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}
.plan-limits span {
  font-size: 0.6875rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 2px 7px;
  color: #475569;
}

.plan-features { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.375rem; }
.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: #475569;
}

.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }
</style>
