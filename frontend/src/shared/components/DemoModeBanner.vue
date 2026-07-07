<template>
  <div v-if="isDemo" class="demo-banner" role="status">
    <span class="demo-banner__badge">{{ $t('demoBanner.label') }}</span>
    <span class="demo-banner__text">
      {{ $t('demoBanner.note') }}
      <template v-if="expiresLabel"> · {{ expiresLabel }}</template>
    </span>
    <router-link to="/contact" class="demo-banner__cta">{{ $t('demoBanner.cta') }}</router-link>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'

const auth = useAuthStore()
const tenant = computed<any>(() => (auth.user as any)?.tenant ?? null)
const isDemo = computed(() => tenant.value?.is_demo === true)

const expiresLabel = computed(() => {
  const d = tenant.value?.demo_expires_at
  if (!d) return ''
  return t('demoBanner.expires', { date: new Date(d).toLocaleDateString() })
})
</script>

<style scoped>
.demo-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  background: linear-gradient(90deg, #fffbeb, #fef3c7);
  border: 1px solid #fde68a;
  color: #92400e;
  border-radius: 10px;
  padding: 0.6rem 0.9rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}
.demo-banner__badge {
  background: #f59e0b;
  color: #fff;
  font-weight: 700;
  font-size: 0.72rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}
.demo-banner__text { flex: 1 1 auto; }
.demo-banner__cta {
  font-weight: 600;
  color: #b45309;
  text-decoration: underline;
  white-space: nowrap;
}
</style>
