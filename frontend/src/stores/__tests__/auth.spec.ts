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

vi.mock('@/services/authService', () => ({
  authService: {
    login:  vi.fn(),
    logout: vi.fn(),
    me:     vi.fn(),
  },
}))

import api from '@/services/api'

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

  it('setToken stores token in localStorage', () => {
    const store = useAuthStore()
    store.setToken('tok123')
    expect(localStorage.getItem('auth_token')).toBe('tok123')
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

  it('logout clears user and token', () => {
    const store = useAuthStore()
    store.setToken('tok123')
    store.setUser(mockUser as any)
    store.logout()
    expect(store.user).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('token from localStorage initialises store', () => {
    localStorage.setItem('auth_token', 'persisted')
    const store = useAuthStore()
    expect(store.token).toBe('persisted')
  })
})
