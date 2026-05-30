<template>
  <div class="auth-layout">
    <div class="auth-card">
      <div class="auth-logo">
        <span class="logo-icon">🌍</span>
        <span class="logo-text">ETech <strong>ERP</strong></span>
      </div>
      <slot />
    </div>
    <div class="auth-footer">Africa · Commerce · Innovation</div>
  </div>
</template>

<style scoped>
.auth-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
  padding: 1rem;
  gap: 1.5rem;
}

.auth-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 420px;
}

.auth-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 2rem;
  font-size: 1.5rem;
  color: #064e3b;
}

.logo-icon { font-size: 2rem; }

.logo-text {
  font-size: 1.4rem;
  color: #374151;
  letter-spacing: -0.5px;
}

.logo-text strong { color: #059669; }

.auth-footer {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.8rem;
  letter-spacing: 1px;
  text-transform: uppercase;
}
</style>
