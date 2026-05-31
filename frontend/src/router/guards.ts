import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function setupGuards(router: Router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // Restore user from token on first load
    if (auth.token && !auth.user) {
      await auth.fetchCurrentUser()
    }

    const isPublic       = to.meta.public === true
    const needsSuperAdmin = to.meta.requiresSuperAdmin === true

    // Not logged in → redirect to login
    if (!isPublic && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    // Admin area → must be super_admin
    if (needsSuperAdmin && auth.user && !auth.user.is_super_admin) {
      return { name: 'dashboard' }
    }

    // Already logged in → skip auth pages
    if (to.name === 'login' && auth.isAuthenticated) {
      return { name: 'dashboard' }
    }

    return true
  })

  // Listen for 401 events from the axios interceptor
  window.addEventListener('auth:expired', () => {
    const auth = useAuthStore()
    auth.$reset()
    router.push({ name: 'login' })
  })
}
