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
    user.value  = response.user

    localStorage.setItem('auth_token',  response.token)
    localStorage.setItem('tenant_slug', response.user.tenant?.slug ?? '')
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

  function $reset() {
    user.value  = null
    token.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('tenant_slug')
  }

  return { user, token, isAuthenticated, login, logout, fetchCurrentUser, $reset }
})
