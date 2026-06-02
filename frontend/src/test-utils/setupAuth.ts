/**
 * Test helper: set up Pinia with a manager user so RBAC composables
 * (usePermission) return the expected values in unit tests.
 *
 * Usage:
 *   import { createPinia, setActivePinia } from 'pinia'
 *   import { mountWithManager } from '@/test-utils/setupAuth'
 *
 *   beforeEach(() => { mountWithManager() })
 */
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

export const MANAGER_USER = {
  id: 'test-user-id',
  name: 'Test Manager',
  email: 'manager@test.com',
  roles: ['manager'],
  tenant_id: 'test-tenant-id',
  is_super_admin: false,
  tenant: { id: 'test-tenant-id', name: 'Test Tenant' },
}

/**
 * Initialize a fresh Pinia instance with a manager user.
 * Call in beforeEach() — returns the pinia instance to add as a plugin.
 */
export function setupManagerAuth() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = MANAGER_USER as any
  return pinia
}
