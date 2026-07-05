import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/modules/auth/services/authService'
import { setAuthToken } from '@/api/authToken'
import type { AuthUser, LoginCredentials } from '@/modules/auth/types'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<AuthUser | null>(null)
  // Security: the bearer token lives in memory only — never localStorage/sessionStorage.
  // Any legacy token from a previous build is ignored (and cleared in $reset).
  const token = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function login(credentials: LoginCredentials): Promise<{ twoFactorRequired: boolean; email?: string }> {
    const response = await authService.login(credentials)

    // RC-13 F-4 — 2FA activée : pas de token, un second facteur est requis.
    if ((response as { two_factor_required?: boolean }).two_factor_required) {
      return { twoFactorRequired: true, email: (response as { email?: string }).email }
    }

    await applySession(response.token, response.user)
    return { twoFactorRequired: false }
  }

  /** RC-13 F-4 — délivre la session après vérification du second facteur. */
  async function completeTwoFactor(payload: { email: string; code: string }) {
    const response = await authService.verifyTwoFactor(payload)
    await applySession(response.token, response.user)
  }

  async function applySession(t: string, u: AuthUser) {
    token.value = t
    user.value  = u   // provisional

    setAuthToken(t)   // in-memory only (API client reads it)
    localStorage.setItem('tenant_slug', u.tenant?.slug ?? '')

    // The /login endpoint is public (no tenant middleware), so its UserResource
    // carries EMPTY team-scoped roles and no subscription. Refresh from /me (behind
    // the tenant middleware) to load the complete user — roles drive the RBAC tab
    // menus (Catégories/Déclinaisons/Attributs…), subscription drives the billing screen.
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
    setAuthToken(t)   // in-memory only — not persisted
  }

  function setUser(u: AuthUser) {
    user.value = u
    localStorage.setItem('tenant_slug', u.tenant?.slug ?? '')
  }

  function $reset() {
    user.value  = null
    token.value = null
    setAuthToken(null)
    localStorage.removeItem('auth_token')   // purge any legacy persisted token
    localStorage.removeItem('tenant_slug')
  }

  const isSuperAdmin      = computed(() => user.value?.is_super_admin === true)
  const userRoles         = computed(() => user.value?.roles ?? [])
  const isAdmin           = computed(() => userRoles.value.includes('admin') || isSuperAdmin.value)
  const isManagerOrAbove  = computed(() => userRoles.value.includes('admin') || userRoles.value.includes('manager') || isSuperAdmin.value)

  return { user, token, isAuthenticated, login, completeTwoFactor, logout, fetchCurrentUser, setToken, setUser, $reset, isSuperAdmin, userRoles, isAdmin, isManagerOrAbove }
})
