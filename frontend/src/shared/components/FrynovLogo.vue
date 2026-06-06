<template>
  <div class="frynov-logo" :class="`frynov-logo--${variant}`">
    <div class="logo-mark">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="20" height="20" rx="5" fill="url(#frynov-logo-grad)"/>
        <path d="M5 14.5V5.5L9.5 11.5V5.5H15V14.5L10.5 8.5V14.5H5Z" fill="white"/>
        <defs>
          <linearGradient id="frynov-logo-grad" x1="0" y1="0" x2="20" y2="20" gradientUnits="userSpaceOnUse">
            <stop stop-color="#10b981"/>
            <stop offset="1" stop-color="#3b82f6"/>
          </linearGradient>
        </defs>
      </svg>
    </div>
    <div v-if="showText" class="logo-text">
      <span class="logo-name">Frynov</span>
      <span v-if="showErp" class="logo-erp">ERP</span>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'light' | 'dark' | 'color'
  showText?: boolean
  showErp?: boolean
}>(), {
  variant: 'dark',
  showText: true,
  showErp: true,
})
</script>

<style scoped>
.frynov-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  flex-shrink: 0;
}

.logo-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.logo-text {
  display: flex;
  align-items: baseline;
  gap: 0.3rem;
}

.logo-name {
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: -0.5px;
  white-space: nowrap;
}

.logo-erp {
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  opacity: 0.6;
}

/* Variant: dark (white text — for dark backgrounds) */
.frynov-logo--dark .logo-name { color: white; }
.frynov-logo--dark .logo-erp  { color: rgba(255,255,255,0.7); }

/* Variant: light (dark text — for light backgrounds) */
.frynov-logo--light .logo-name { color: var(--gray-900, #0f172a); }
.frynov-logo--light .logo-erp  { color: var(--gray-500, #64748b); }

/* Variant: color (green text) */
.frynov-logo--color .logo-name { color: var(--brand-primary, #10b981); }
.frynov-logo--color .logo-erp  { color: var(--brand-primary, #10b981); opacity: 0.7; }
</style>
