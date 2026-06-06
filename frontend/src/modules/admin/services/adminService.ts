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
  domain?: string | null
  status: string
  plan: string
  subscription_status: string
  created_at: string
  updated_at?: string
  deleted_at?: string | null
  users?: Array<{ id: string; name: string; email: string; is_super_admin?: boolean }>
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

export interface AdminPlanLimits {
  max_products: number | null
  max_monthly_orders: number | null
  max_customers: number | null
  max_branches: number | null
  max_warehouses: number | null
  max_imports_per_month: number | null
  max_api_calls_per_month: number | null
  storage_mb: number | null
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
  limits?: AdminPlanLimits | null
}

export interface AdminManualPayment {
  id: string
  tenant_id: string
  tenant_name?: string
  plan_code?: string
  plan_name?: string
  amount_cents: number
  currency: string
  payment_method: string
  proof_url?: string | null
  proof_original_filename?: string | null
  notes?: string | null
  promo_code_used?: string | null
  status: 'pending' | 'approved' | 'rejected'
  rejection_reason?: string | null
  reviewed_at?: string | null
  created_at: string
}

export interface AdminPromotion {
  id: string
  code: string
  description?: string | null
  discount_type: 'percent' | 'fixed_cents'
  discount_value: number
  applicable_plans?: string[] | null
  valid_from?: string | null
  valid_until?: string | null
  max_uses?: number | null
  current_uses: number
  is_active: boolean
  created_at: string
}

export interface AdminCountryRule {
  id: string
  country_code: string
  is_active: boolean
  requires_approval: boolean
  is_blocked: boolean
  allowed_plans?: string[] | null
  default_currency?: string | null
  default_timezone?: string | null
  metadata?: Record<string, unknown> | null
  created_at?: string
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

  async updatePlan(id: string, payload: Partial<AdminPlan> & { limits?: Partial<AdminPlanLimits> }) {
    const { data } = await client.patch<AdminPlan>(`/api/admin/plans/${id}`, payload)
    return data
  },

  async getAuditLogs(page = 1) {
    const { data } = await client.get('/api/admin/audit-logs', { params: { page } })
    return data
  },

  // ── Manual payments ─────────────────────────────────────────────────────────

  async getManualPayments(params?: { status?: string; page?: number }) {
    const { data } = await client.get<{ data: AdminManualPayment[]; meta: any }>('/api/admin/manual-payments', { params })
    return data
  },

  async approveManualPayment(id: string) {
    const { data } = await client.post(`/api/admin/manual-payments/${id}/approve`)
    return data
  },

  async rejectManualPayment(id: string, reason: string) {
    const { data } = await client.post(`/api/admin/manual-payments/${id}/reject`, { reason })
    return data
  },

  // ── Promotions ──────────────────────────────────────────────────────────────

  async getPromotions(page = 1) {
    const { data } = await client.get<{ data: AdminPromotion[]; meta: any }>('/api/admin/promotions', { params: { page } })
    return data
  },

  async createPromotion(payload: Partial<AdminPromotion>) {
    const { data } = await client.post<AdminPromotion>('/api/admin/promotions', payload)
    return data
  },

  async updatePromotion(id: string, payload: Partial<AdminPromotion>) {
    const { data } = await client.patch<AdminPromotion>(`/api/admin/promotions/${id}`, payload)
    return data
  },

  async deletePromotion(id: string) {
    const { data } = await client.delete(`/api/admin/promotions/${id}`)
    return data
  },

  // ── Country rules ─────────────────────────────────────────────────────────────

  async getCountryRules(page = 1) {
    const { data } = await client.get<{ data: AdminCountryRule[]; meta: any }>('/api/admin/country-rules', { params: { page } })
    return data
  },

  async createCountryRule(payload: Partial<AdminCountryRule>) {
    const { data } = await client.post<AdminCountryRule>('/api/admin/country-rules', payload)
    return data
  },

  async updateCountryRule(id: string, payload: Partial<AdminCountryRule>) {
    const { data } = await client.patch<AdminCountryRule>(`/api/admin/country-rules/${id}`, payload)
    return data
  },

  async deleteCountryRule(id: string) {
    const { data } = await client.delete(`/api/admin/country-rules/${id}`)
    return data
  },

  /** GET /api/admin/tenants/:id/modules — modules with tenant_active flag */
  async getTenantModules(tenantId: string) {
    const { data } = await client.get<import('@/modules/auth/types').ErpModule[]>(
      `/api/admin/tenants/${tenantId}/modules`
    )
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
