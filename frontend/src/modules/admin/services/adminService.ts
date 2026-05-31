import client from '@/api/client'

export interface AdminOverview {
  tenants: number
  active_tenants: number
  suspended_tenants: number
  total_users: number
  total_modules: number
  total_plans: number
}

export interface AdminTenant {
  id: string
  name: string
  slug: string
  status: string
  plan: string
  subscription_status: string
  created_at: string
  users?: Array<{ id: string; name: string; email: string }>
}

export interface AdminModule {
  id: string
  code: string
  name: string
  category: string
  status: string
  is_core: boolean
  is_visible: boolean
  sort_order: number
  total_activations: number
}

export interface AdminPlan {
  id: string
  code: string
  name: string
  description: string
  price_monthly_cents: number
  price_yearly_cents: number
  currency: string
  max_users: number
  max_products: number
  max_monthly_orders: number
  trial_days: number
  features: string[]
  is_active: boolean
  is_public: boolean
}

export interface AuditLogEntry {
  id: string
  user_id: string | null
  tenant_id: string | null
  action: string
  subject_type: string | null
  subject_id: string | null
  ip_address: string | null
  created_at: string
  user?: { id: string; name: string; email: string }
}

export const adminService = {
  async getDashboard() {
    const { data } = await client.get<{
      overview: AdminOverview
      subscriptions: Record<string, number>
      by_plan: Array<{ code: string; name: string; total: number }>
      recent_tenants: AdminTenant[]
      recent_logs: AuditLogEntry[]
    }>('/api/admin/dashboard')
    return data
  },

  async getTenants(params?: { search?: string; status?: string; plan?: string; page?: number }) {
    const { data } = await client.get('/api/admin/tenants', { params })
    return data
  },

  async getTenant(id: string) {
    const { data } = await client.get<{ tenant: AdminTenant; subscription: any }>(`/api/admin/tenants/${id}`)
    return data
  },

  async suspendTenant(id: string, reason?: string) {
    const { data } = await client.post(`/api/admin/tenants/${id}/suspend`, { reason })
    return data
  },

  async reactivateTenant(id: string) {
    const { data } = await client.post(`/api/admin/tenants/${id}/reactivate`)
    return data
  },

  async changeTenantPlan(id: string, planCode: string) {
    const { data } = await client.post(`/api/admin/tenants/${id}/change-plan`, { plan_code: planCode })
    return data
  },

  async getModules() {
    const { data } = await client.get<AdminModule[]>('/api/admin/modules')
    return data
  },

  async updateModule(id: string, payload: Partial<AdminModule>) {
    const { data } = await client.patch(`/api/admin/modules/${id}`, payload)
    return data
  },

  async getPlans() {
    const { data } = await client.get<AdminPlan[]>('/api/admin/plans')
    return data
  },

  async getAuditLogs(page = 1) {
    const { data } = await client.get('/api/admin/audit-logs', { params: { page } })
    return data
  },

  async activateModuleForTenant(tenantId: string, moduleCode: string) {
    const { data } = await client.post(`/api/admin/tenants/${tenantId}/modules/${moduleCode}/activate`)
    return data
  },

  async deactivateModuleForTenant(tenantId: string, moduleCode: string) {
    const { data } = await client.post(`/api/admin/tenants/${tenantId}/modules/${moduleCode}/deactivate`)
    return data
  },
}
