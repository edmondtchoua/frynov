<template>
  <!-- ── Toast stack (fixed, bottom-right) ─────────────────────────────────── -->
  <Teleport to="body">
    <div class="toast-stack" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="toast"
          :class="`toast--${t.severity}`"
          role="alert"
        >
          <span class="toast-icon">{{ emoji(t.severity) }}</span>
          <div class="toast-body">
            <div class="toast-platform">{{ ctxPlatform(t) }}</div>
            <div class="toast-msg">{{ cut(t.message, 90) }}</div>
          </div>
          <button class="toast-x" @click="dismissToast(t.id)" aria-label="Fermer">✕</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>

  <!-- ── Bell button + panel ───────────────────────────────────────────────── -->
  <div class="nc-root" ref="root">
    <button
      class="nc-bell"
      :class="{ 'nc-bell--active': panelOpen }"
      @click="panelOpen = !panelOpen"
      :aria-label="`Notifications${unreadCount > 0 ? ` (${unreadCount})` : ''}`"
    >
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
        <path d="M9 2a5.5 5.5 0 015.5 5.5v3l1 1.5h-13l1-1.5V7.5A5.5 5.5 0 019 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M7 15a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <span v-if="unreadCount > 0" class="nc-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
    </button>

    <!-- Panel -->
    <Transition name="nc-panel">
      <div v-if="panelOpen" class="nc-panel" @keydown.esc="panelOpen = false">

        <div class="nc-panel-header">
          <span class="nc-panel-title">Notifications</span>
          <div class="nc-panel-acts">
            <button v-if="unreadCount > 0" class="nc-act" @click="markAllRead">Tout lire</button>
            <button class="nc-act" @click="refresh" title="Actualiser">↻</button>
          </div>
        </div>

        <div v-if="notifications.length === 0" class="nc-empty">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
            <circle cx="14" cy="14" r="12" stroke="var(--gray-200)" stroke-width="1.5"/>
            <path d="M14 9v6M14 18v.5" stroke="var(--gray-300)" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <p>Tout est à jour</p>
        </div>

        <ul v-else class="nc-list" role="list">
          <li
            v-for="n in notifications"
            :key="n.id"
            class="nc-item"
            :class="{ 'nc-item--unread': !n.is_read }"
            @click="onItemClick(n)"
          >
            <span class="nc-item-icon" :class="`nc-sev--${n.severity}`">{{ emoji(n.severity) }}</span>
            <div class="nc-item-body">
              <div class="nc-item-source">{{ ctxPlatform(n) }}</div>
              <div class="nc-item-msg">{{ cut(n.message, 100) }}</div>
              <div class="nc-item-time">{{ ago(n.created_at) }}</div>
            </div>
            <span v-if="!n.is_read" class="nc-dot"></span>
          </li>
        </ul>

        <div class="nc-panel-footer">
          <RouterLink to="/marketplace" class="nc-more-link" @click="panelOpen = false">
            Voir toutes les alertes marketplace →
          </RouterLink>
        </div>

      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'

const { notifications, unreadCount, toasts, panelOpen, markRead, markAllRead, dismissToast, refresh } = useNotifications()

// Close panel on outside click
const root = ref<HTMLElement | null>(null)

function outsideClick(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) {
    panelOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', outsideClick, true))
onUnmounted(() => document.removeEventListener('mousedown', outsideClick, true))

function onItemClick(n: any) {
  if (!n.is_read) markRead(n.id)
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function emoji(s: string): string {
  return ({ info: 'ℹ️', warning: '⚠️', error: '🔴', critical: '🚨' } as Record<string, string>)[s] ?? '🔔'
}

function ctxPlatform(n: any): string {
  const p = (n.context?.platform ?? '') as string
  const map: Record<string, string> = {
    facebook: 'Facebook Marketplace',
    whatsapp_catalog: 'WhatsApp Business',
    shopify: 'Shopify',
    woocommerce: 'WooCommerce',
    jumia: 'Jumia',
  }
  return map[p] ?? (p ? p.charAt(0).toUpperCase() + p.slice(1) : 'Marketplace')
}

function cut(s: string, max: number): string {
  return s.length > max ? s.slice(0, max) + '…' : s
}

function ago(iso: string): string {
  const secs = (new Date().getTime() - new Date(iso).getTime()) / 1000
  if (secs < 60)    return 'À l\'instant'
  if (secs < 3600)  return `Il y a ${Math.floor(secs / 60)} min`
  if (secs < 86400) return `Il y a ${Math.floor(secs / 3600)} h`
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}
</script>

<style scoped>
/* ── Toast stack ──────────────────────────────────────────────────────────── */
.toast-stack {
  position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9000;
  display: flex; flex-direction: column; gap: .625rem;
  max-width: 380px; pointer-events: none;
}
.toast {
  display: flex; align-items: flex-start; gap: .75rem;
  background: white; border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,.14), 0 2px 8px rgba(0,0,0,.06);
  border: 1px solid var(--gray-200);
  padding: .875rem 1rem; pointer-events: all;
}
.toast--warning  { border-left: 4px solid #f59e0b; }
.toast--error,
.toast--critical { border-left: 4px solid #ef4444; }
.toast--info     { border-left: 4px solid #3b82f6; }

.toast-icon     { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
.toast-body     { flex: 1; min-width: 0; }
.toast-platform { font-size: .6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin-bottom: 2px; }
.toast-msg      { font-size: .8125rem; color: var(--gray-800); line-height: 1.45; }
.toast-x        { background: none; border: none; color: var(--gray-400); cursor: pointer; font-size: 1rem; padding: 0 2px; flex-shrink: 0; transition: color .12s; }
.toast-x:hover  { color: var(--gray-700); }

.toast-enter-active { transition: all .3s cubic-bezier(.22,1,.36,1); }
.toast-leave-active { transition: all .22s ease; position: relative; }
.toast-enter-from   { opacity: 0; transform: translateX(120px); }
.toast-leave-to     { opacity: 0; transform: translateX(120px); }
.toast-move         { transition: transform .3s ease; }

/* ── Bell ─────────────────────────────────────────────────────────────────── */
.nc-root { position: relative; }

.nc-bell {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: var(--gray-500); transition: background .12s, color .12s;
  position: relative; flex-shrink: 0;
}
.nc-bell:hover,
.nc-bell--active { background: var(--gray-100); color: var(--gray-900); }

.nc-badge {
  position: absolute; top: 1px; right: 1px;
  min-width: 16px; height: 16px; padding: 0 3px;
  background: #ef4444; color: white; border-radius: 8px;
  font-size: .575rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid white; line-height: 1;
}

/* ── Panel ────────────────────────────────────────────────────────────────── */
.nc-panel {
  position: absolute; top: calc(100% + 10px); right: 0;
  width: 340px; background: white; border-radius: 14px;
  border: 1px solid var(--gray-200);
  box-shadow: 0 16px 48px rgba(0,0,0,.12), 0 4px 12px rgba(0,0,0,.06);
  z-index: 300; overflow: hidden;
}

.nc-panel-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: .875rem 1rem; border-bottom: 1px solid var(--gray-100);
}
.nc-panel-title { font-size: .875rem; font-weight: 700; color: var(--gray-900); }
.nc-panel-acts  { display: flex; gap: .5rem; }
.nc-act         { font-size: .75rem; font-weight: 500; color: var(--brand-primary); background: none; border: none; cursor: pointer; }
.nc-act:hover   { text-decoration: underline; }

.nc-empty {
  padding: 2.5rem 1rem; text-align: center; display: flex;
  flex-direction: column; align-items: center; gap: .75rem;
}
.nc-empty p { font-size: .875rem; color: var(--gray-400); margin: 0; }

.nc-list { list-style: none; padding: 0; margin: 0; max-height: 320px; overflow-y: auto; }

.nc-item {
  display: flex; align-items: flex-start; gap: .75rem;
  padding: .875rem 1rem; cursor: pointer;
  border-bottom: 1px solid var(--gray-50); transition: background .1s;
}
.nc-item:last-child { border-bottom: none; }
.nc-item:hover { background: var(--gray-50); }
.nc-item--unread { background: #fffbeb; }
.nc-item--unread:hover { background: #fef3c7; }

.nc-item-icon    { font-size: 1.125rem; flex-shrink: 0; margin-top: 1px; }
.nc-sev--warning { color: #f59e0b; }
.nc-sev--error,
.nc-sev--critical{ color: #ef4444; }
.nc-sev--info    { color: #3b82f6; }

.nc-item-body   { flex: 1; min-width: 0; }
.nc-item-source { font-size: .6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: 2px; }
.nc-item-msg    { font-size: .8125rem; color: var(--gray-800); line-height: 1.4; }
.nc-item-time   { font-size: .6875rem; color: var(--gray-400); margin-top: 3px; }

.nc-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand-primary); flex-shrink: 0; margin-top: 5px; }

.nc-panel-footer { padding: .75rem 1rem; border-top: 1px solid var(--gray-100); text-align: center; }
.nc-more-link { font-size: .8125rem; font-weight: 500; color: var(--brand-primary); text-decoration: none; }
.nc-more-link:hover { text-decoration: underline; }

/* Panel slide transition */
.nc-panel-enter-active { transition: opacity .15s ease, transform .15s cubic-bezier(.22,1,.36,1); }
.nc-panel-leave-active { transition: opacity .1s ease; }
.nc-panel-enter-from   { opacity: 0; transform: translateY(-8px) scale(.97); }
.nc-panel-leave-to     { opacity: 0; }
</style>
