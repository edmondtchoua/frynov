<template>
  <div class="auth-layout">
    <div class="auth-bg-decoration" aria-hidden="true"></div>

    <div class="auth-panel">
      <!-- Logo -->
      <RouterLink to="/" class="auth-logo-link">
        <FrynovLogo variant="light" />
      </RouterLink>

      <!-- Card -->
      <div class="auth-card">
        <slot />
      </div>

      <!-- Footer -->
      <p class="auth-footer">
        &copy; {{ year }} Frynov ERP &mdash; Smart business. Real growth.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import FrynovLogo from '@/shared/components/FrynovLogo.vue'

const year = new Date().getFullYear()
</script>

<style scoped>
.auth-layout {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--gray-50);
  padding: 1.5rem 1rem;
  position: relative;
  overflow: hidden;
}

/* Decorative background element */
.auth-bg-decoration {
  position: fixed;
  top: -40%;
  right: -20%;
  width: 800px;
  height: 800px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(16,185,129,0.08) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

.auth-panel {
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  position: relative;
  z-index: 1;
}

.auth-logo-link {
  text-decoration: none;
}

.auth-card {
  background: white;
  border-radius: var(--radius-xl);
  border: 1px solid var(--gray-200);
  box-shadow: var(--shadow-lg);
  padding: 2.5rem 2rem;
  width: 100%;
}

.auth-footer {
  font-size: var(--text-xs);
  color: var(--gray-400);
  text-align: center;
  margin: 0;
}

/* Mobile */
@media (max-width: 480px) {
  .auth-card {
    padding: 2rem 1.25rem;
    border-radius: var(--radius-lg);
  }
}
</style>
