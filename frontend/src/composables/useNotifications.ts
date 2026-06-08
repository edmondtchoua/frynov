/**
 * useNotifications — Real-time in-app alert system.
 *
 * Polls /api/marketplace/alerts every 30 seconds.
 * Shows toast notifications for NEW unread alerts.
 * Manages a reactive notification panel state (open/close).
 *
 * Usage: call once in App.vue or in AppLayout — singleton via module state.
 */
import { ref, readonly, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import client from '@/api/client'

export interface AppNotification {
  id: string
  type: string
  severity: 'info' | 'warning' | 'error' | 'critical'
  message: string
  context: Record<string, unknown> | null
  is_read: boolean
  requires_action: boolean
  created_at: string
}

// ── Module-level singleton (shared across all callers) ────────────────────────
const _notifications = ref<AppNotification[]>([])
const _unreadCount   = ref(0)
const _panelOpen     = ref(false)
const _toasts        = ref<AppNotification[]>([])
const _seenIds       = new Set<string>()
let   _timer: ReturnType<typeof setInterval> | null = null
let   _ready = false

const POLL_MS      = 30_000  // 30 seconds
const TOAST_TTL_MS = 7_000   // auto-dismiss toasts

let _toastSeq = 0

/**
 * Push a transient client-side toast (auto-dismissed). Used for immediate feedback
 * on actions — e.g. a 403 from the API — so failures are never silent (audit UX-10).
 */
export function pushToast(message: string, severity: AppNotification['severity'] = 'error'): void {
  const toast: AppNotification = {
    id: `local-${++_toastSeq}`,
    type: 'client',
    severity,
    message,
    context: null,
    is_read: true,
    requires_action: false,
    created_at: new Date().toISOString(),
  }
  _toasts.value = [..._toasts.value, toast]
  setTimeout(() => { _toasts.value = _toasts.value.filter(t => t.id !== toast.id) }, TOAST_TTL_MS)
}

async function poll(): Promise<void> {
  try {
    const auth = useAuthStore()
    if (!auth.isAuthenticated) return

    const { data } = await client.get('/api/marketplace/alerts', { params: { per_page: 20 } })
    const incoming: AppNotification[] = data.data ?? []

    // Detect NEW unread alerts → push toast
    incoming.filter(n => !_seenIds.has(n.id) && !n.is_read).forEach(n => {
      _toasts.value = [..._toasts.value, n]
      setTimeout(() => {
        _toasts.value = _toasts.value.filter(t => t.id !== n.id)
      }, TOAST_TTL_MS)
    })

    incoming.forEach(n => _seenIds.add(n.id))
    _notifications.value = incoming
    _unreadCount.value    = incoming.filter(n => !n.is_read).length
  } catch { /* silent — network errors must not crash the app */ }
}

function startPolling(): void {
  poll()
  if (!_timer) _timer = setInterval(poll, POLL_MS)
}

function stopPolling(): void {
  if (_timer) { clearInterval(_timer); _timer = null }
  _notifications.value = []
  _unreadCount.value   = 0
  _seenIds.clear()
}

// ── Public composable ────────────────────────────────────────────────────────

export function useNotifications() {
  if (!_ready) {
    _ready = true
    const auth = useAuthStore()
    if (auth.isAuthenticated) startPolling()
    watch(() => auth.isAuthenticated, (isAuth) => {
      if (isAuth) startPolling()
      else stopPolling()
    })

    // Surface API 403s (the client dispatches `api:forbidden`) so a blocked action
    // gives immediate feedback instead of failing silently (UX-10).
    window.addEventListener('api:forbidden', (e: Event) => {
      const message = (e as CustomEvent<{ message?: string }>).detail?.message
      pushToast(message ?? 'Action non autorisée.', 'error')
    })
  }

  async function markRead(id: string): Promise<void> {
    try {
      await client.patch(`/api/marketplace/alerts/${id}/read`)
      const n = _notifications.value.find(n => n.id === id)
      if (n) n.is_read = true
      _unreadCount.value = _notifications.value.filter(n => !n.is_read).length
    } catch { /* ignore */ }
  }

  async function markAllRead(): Promise<void> {
    await Promise.allSettled(
      _notifications.value.filter(n => !n.is_read).map(n => markRead(n.id))
    )
  }

  function dismissToast(id: string): void {
    _toasts.value = _toasts.value.filter(t => t.id !== id)
  }

  function refresh(): void { poll() }

  return {
    notifications: readonly(_notifications),
    unreadCount:   readonly(_unreadCount),
    toasts:        readonly(_toasts),
    panelOpen:     _panelOpen,   // writable — components toggle it
    markRead,
    markAllRead,
    dismissToast,
    pushToast,
    refresh,
  }
}
