import client from '@/api/client'
import type {
  AuthResponse, AuthUser, LoginCredentials, RegisterPayload,
  ModulesResponse, Subscription, WorkspaceUser, WorkspaceSettings,
} from '../types'

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const { data } = await client.post<AuthResponse>('/api/auth/login', credentials)
    return data
  },

  async register(payload: RegisterPayload): Promise<AuthResponse> {
    const { data } = await client.post<AuthResponse>('/api/auth/register', payload)
    return data
  },

  async logout(): Promise<void> {
    await client.post('/api/auth/logout')
  },

  /** GET /api/auth/me — backend wraps in { user: ... } */
  async me(): Promise<AuthUser> {
    const { data } = await client.get<{ user: AuthUser }>('/api/auth/me')
    return data.user
  },

  /** GET /api/me/modules — list of ERP modules with tenant_active flags */
  async getModules(): Promise<ModulesResponse> {
    const { data } = await client.get<ModulesResponse>('/api/me/modules')
    return data
  },

  /** GET /api/me/subscription — full subscription details for billing tab */
  async getSubscription(): Promise<{ subscription: Subscription | null }> {
    const { data } = await client.get<{ subscription: Subscription | null }>('/api/me/subscription')
    return data
  },

  /** POST /api/me/promo/validate — check promo code validity without applying */
  async validatePromo(code: string, planCode?: string): Promise<{
    valid: boolean
    code?: string
    discount_type?: 'percent' | 'fixed_cents'
    discount_value?: number
    description?: string | null
    message?: string
  }> {
    try {
      const { data } = await client.post('/api/me/promo/validate', { code, plan_code: planCode })
      return data
    } catch (err: any) {
      return err?.response?.data ?? { valid: false, message: 'Code invalide.' }
    }
  },

  /** POST /api/me/promo/apply — record promo code usage */
  async applyPromo(code: string, planCode?: string): Promise<{ message: string; discount_type?: string; discount_value?: number }> {
    const { data } = await client.post('/api/me/promo/apply', { code, plan_code: planCode })
    return data
  },

  // ── Workspace: team management ─────────────────────────────────────────────

  /** GET /api/workspace/users */
  async getWorkspaceUsers(): Promise<WorkspaceUser[]> {
    const { data } = await client.get<{ data: WorkspaceUser[] }>('/api/workspace/users')
    return data.data
  },

  /** POST /api/workspace/users */
  async inviteUser(payload: { name: string; email: string; role: string }): Promise<{
    data: WorkspaceUser
    temp_password: string
    message: string
  }> {
    const { data } = await client.post('/api/workspace/users', payload)
    return data
  },

  /** PATCH /api/workspace/users/:id */
  async updateUser(userId: string, payload: { name?: string; role?: string }): Promise<WorkspaceUser> {
    const { data } = await client.patch<{ data: WorkspaceUser }>(`/api/workspace/users/${userId}`, payload)
    return data.data
  },

  /** DELETE /api/workspace/users/:id — toggles active/inactive */
  async toggleUser(userId: string): Promise<{ data: WorkspaceUser; message: string }> {
    const { data } = await client.delete(`/api/workspace/users/${userId}`)
    return data
  },

  /** PUT /api/workspace/users/:id/warehouses — set the sites a member's data access is scoped to */
  async setUserWarehouses(userId: string, warehouseIds: string[]): Promise<WorkspaceUser> {
    const { data } = await client.put<{ data: WorkspaceUser }>(`/api/workspace/users/${userId}/warehouses`, { warehouse_ids: warehouseIds })
    return data.data
  },

  /** POST /api/workspace/users/:id/temporary-access — grant a role until expires_at (auto-revoked) */
  async grantTemporaryAccess(userId: string, payload: { role: string; expires_at: string; note?: string }) {
    const { data } = await client.post(`/api/workspace/users/${userId}/temporary-access`, payload)
    return data
  },

  /** DELETE /api/workspace/temporary-access/:id — revoke a temporary grant early */
  async revokeTemporaryAccess(grantId: string) {
    const { data } = await client.delete(`/api/workspace/temporary-access/${grantId}`)
    return data
  },

  // ── Workspace: company settings ────────────────────────────────────────────

  /** GET /api/workspace/settings */
  async getWorkspaceSettings(): Promise<WorkspaceSettings> {
    const { data } = await client.get<{ data: WorkspaceSettings }>('/api/workspace/settings')
    return data.data
  },

  /** PATCH /api/workspace/settings */
  async updateWorkspaceSettings(payload: {
    name?: string
    domain?: string | null
    settings?: Record<string, unknown>
  }): Promise<{ data: WorkspaceSettings; message: string }> {
    const { data } = await client.patch('/api/workspace/settings', payload)
    return data
  },
}
