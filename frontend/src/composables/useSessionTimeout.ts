/**
 * useSessionTimeout — Inactivity-based session timeout.
 *
 * Tracks user activity (mouse, keyboard, touch, scroll).
 * When idle for longer than the configured timeout, logs the user out
 * and redirects to /login?reason=inactivity.
 *
 * Timeout duration: tenant.settings.session_timeout_minutes
 * Default: 1440 minutes (24 hours).
 * Configurable per tenant by the super-admin via workspace settings.
 *
 * Usage: call once in App.vue — no-op when user is not authenticated.
 */
import { onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const ACTIVITY_EVENTS = [
  'mousedown',
  'mousemove',
  'keydown',
  'touchstart',
  'scroll',
  'click',
] as const

const DEFAULT_TIMEOUT_MINUTES = 1440 // 24 hours

export function useSessionTimeout() {
  const auth   = useAuthStore()
  const router = useRouter()
  let timer: ReturnType<typeof setTimeout> | null = null

  function getTimeoutMs(): number {
    const settings = auth.user?.tenant?.settings as Record<string, unknown> | undefined
    const minutes  = settings?.session_timeout_minutes
    return Math.max(1, Number(minutes ?? DEFAULT_TIMEOUT_MINUTES)) * 60_000
  }

  function resetTimer() {
    if (!auth.isAuthenticated) return
    if (timer) clearTimeout(timer)
    timer = setTimeout(handleTimeout, getTimeoutMs())
  }

  async function handleTimeout() {
    if (!auth.isAuthenticated) return
    await auth.logout()
    router.push({ name: 'login', query: { reason: 'inactivity' } })
  }

  function startListeners() {
    ACTIVITY_EVENTS.forEach(e => window.addEventListener(e, resetTimer, { passive: true }))
    resetTimer()
  }

  function stopListeners() {
    ACTIVITY_EVENTS.forEach(e => window.removeEventListener(e, resetTimer))
    if (timer) { clearTimeout(timer); timer = null }
  }

  onMounted(() => { if (auth.isAuthenticated) startListeners() })
  onUnmounted(stopListeners)

  // Restart/stop when auth state changes (login → start, logout → stop)
  watch(() => auth.isAuthenticated, (isAuth) => {
    if (isAuth) startListeners()
    else stopListeners()
  })

  return {
    /** Manually start activity listeners and arm the idle timer. */
    start: startListeners,
    /** Reset (restart) the idle timer. */
    reset: resetTimer,
    /** @deprecated Use reset() */
    resetTimer,
  }
}
