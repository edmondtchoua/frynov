import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  },
}))

vi.mock('@/modules/auth/services/authService', () => ({
  authService: {
    login:  vi.fn(),
    logout: vi.fn(),
    me:     vi.fn(),
  },
}))

import { authService } from '@/modules/auth/services/authService'

const mockUser = {
  id:              'usr-1',
  name:            'Alice',
  email:           'alice@test.sn',
  tenant_id:       'ten-1',
  is_super_admin:  false,
  roles:           ['admin'],
}

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('initially has no user', () => {
    const store = useAuthStore()
    expect(store.user).toBeNull()
  })

  it('isAuthenticated is false when no token', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
  })

  it('setToken keeps the token in reactive memory only', () => {
    const store = useAuthStore()
    store.setToken('tok123')
    expect(store.token).toBe('tok123')
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('setUser makes isAuthenticated true', () => {
    const store = useAuthStore()
    store.setToken('tok123')
    store.setUser(mockUser as any)
    expect(store.isAuthenticated).toBe(true)
    expect(store.user?.name).toBe('Alice')
  })

  it('isSuperAdmin is false for regular user', () => {
    const store = useAuthStore()
    store.setUser(mockUser as any)
    expect(store.isSuperAdmin).toBe(false)
  })

  it('isSuperAdmin is true for super-admin', () => {
    const store = useAuthStore()
    store.setUser({ ...mockUser, is_super_admin: true } as any)
    expect(store.isSuperAdmin).toBe(true)
  })

  it('logout clears user and token', async () => {
    const store = useAuthStore()
    store.setToken('tok123')
    store.setUser(mockUser as any)
    await store.logout()
    expect(store.user).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('ignores any legacy token that may still exist in localStorage', () => {
    localStorage.setItem('auth_token', 'persisted')
    const store = useAuthStore()
    expect(store.token).toBeNull()
  })

  // Regression: /login is public (no tenant middleware) → empty team-scoped roles
  // and no subscription. login() must refresh from /me so the RBAC tab menus and
  // the billing screen work right after sign-in.
  it('login refreshes the full user from /me (team roles + subscription)', async () => {
    vi.mocked(authService.login).mockResolvedValue({
      token: 'tok', user: { ...mockUser, roles: [] },   // login: roles empty
    } as any)
    vi.mocked(authService.me).mockResolvedValue({
      ...mockUser, roles: ['admin'],
      subscription: { plan_name: 'Croissance', status: 'active' },
    } as any)

    const store = useAuthStore()
    await store.login({ email: 'alice@test.sn', password: 'x' } as any)

    expect(authService.me).toHaveBeenCalled()
    expect(store.user?.roles).toContain('admin')                 // not the empty login roles
    expect((store.user as any)?.subscription?.plan_name).toBe('Croissance')
  })
})
