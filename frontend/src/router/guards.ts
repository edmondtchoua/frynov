import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { progressStart, progressDone, progressReset } from '@/composables/useProgress'

export function setupGuards(router: Router) {
  router.beforeEach(async (to) => {
    progressStart()
    const auth = useAuthStore()

    // Restore user from token on first load
    if (auth.token && !auth.user) {
      await auth.fetchCurrentUser()
    }

    const isPublic        = to.meta.public === true
    const needsSuperAdmin = to.meta.requiresSuperAdmin === true
    const isSuperAdmin    = auth.user?.is_super_admin === true
    const isAdminRoute    = needsSuperAdmin ||
                            to.matched.some(r => r.meta.layout === 'admin')

    // ── Not authenticated ────────────────────────────────────────────────────
    if (!isPublic && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    // ── Super admin: must stay inside /admin (or shared routes like /profile)──
    const isSharedRoute = ['profile'].includes(String(to.name))
    if (auth.isAuthenticated && isSuperAdmin && !isPublic && !isAdminRoute && !isSharedRoute) {
      // Prevent super admin from reaching any tenant-app route
      return { name: 'admin.dashboard' }
    }

    // ── Admin area: must be super admin ──────────────────────────────────────
    if (needsSuperAdmin && auth.user && !isSuperAdmin) {
      return { name: 'dashboard' }
    }

    // ── Already authenticated: skip auth pages ───────────────────────────────
    if (to.name === 'login' && auth.isAuthenticated) {
      return isSuperAdmin ? { name: 'admin.dashboard' } : { name: 'dashboard' }
    }

    return true
  })

  router.afterEach(()  => progressDone())
  router.onError(()    => progressReset())

  // Listen for 401 events from the axios interceptor
  window.addEventListener('auth:expired', () => {
    const auth = useAuthStore()
    auth.$reset()
    router.push({ name: 'login' })
  })
}
