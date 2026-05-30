import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function setupGuards(router: Router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // Restore user from token on first load
    if (auth.token && !auth.user) {
      await auth.fetchCurrentUser()
    }

    const isPublic = to.meta.public === true

    if (!isPublic && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

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
