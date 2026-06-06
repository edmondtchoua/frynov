import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/modules/auth/services/authService'
import type { AuthUser, LoginCredentials } from '@/modules/auth/types'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<AuthUser | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function login(credentials: LoginCredentials) {
    const response = await authService.login(credentials)

    token.value = response.token
    user.value  = response.user   // provisional

    localStorage.setItem('auth_token',  response.token)
    localStorage.setItem('tenant_slug', response.user.tenant?.slug ?? '')

    // The /login endpoint is public (no tenant middleware), so its UserResource
    // carries EMPTY team-scoped roles and no subscription. Refresh from /me (behind
    // the tenant middleware) to load the complete user — roles drive the RBAC tab
    // menus (Catégories/Déclinaisons/Attributs…), subscription drives the billing
    // screen. Without this, both appear missing right after login.
    await fetchCurrentUser()
  }

  async function logout() {
    try { await authService.logout() } catch { /* ignore network errors on logout */ }
    $reset()
  }

  async function fetchCurrentUser() {
    if (!token.value) return
    try {
      user.value = await authService.me()
    } catch {
      $reset()
    }
  }

  /** Used after registration (or any flow that already has a token + user). */
  function setToken(t: string) {
    token.value = t
    localStorage.setItem('auth_token', t)
  }

  function setUser(u: AuthUser) {
    user.value = u
    localStorage.setItem('tenant_slug', u.tenant?.slug ?? '')
  }

  function $reset() {
    user.value  = null
    token.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('tenant_slug')
  }

  const isSuperAdmin      = computed(() => user.value?.is_super_admin === true)
  const userRoles         = computed(() => user.value?.roles ?? [])
  const isAdmin           = computed(() => userRoles.value.includes('admin') || isSuperAdmin.value)
  const isManagerOrAbove  = computed(() => userRoles.value.includes('admin') || userRoles.value.includes('manager') || isSuperAdmin.value)

  return { user, token, isAuthenticated, login, logout, fetchCurrentUser, setToken, setUser, $reset, isSuperAdmin, userRoles, isAdmin, isManagerOrAbove }
})
