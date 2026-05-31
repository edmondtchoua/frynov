export interface Tenant {
  id: string
  name: string
  slug: string
  plan: string
  status: string
  subscription_status: string
  settings?: Record<string, unknown>
}

export interface AuthUser {
  id: string
  name: string
  email: string
  is_super_admin: boolean
  tenant_id: string | null
  tenant?: Tenant
  roles: string[]
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterPayload {
  company_name: string
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthResponse {
  token: string
  user: AuthUser
}

// ── ERP Module types ──────────────────────────────────────────────────────────

export interface ErpModule {
  id: string
  code: string
  name: string
  category: 'core' | 'operations' | 'finance' | 'analytics' | 'advanced'
  description: string
  icon_svg: string
  status: 'active' | 'beta' | 'coming_soon' | 'maintenance' | 'disabled'
  is_core: boolean
  is_visible: boolean
  route_prefix: string
  color: string
  sort_order: number
  // Appended by ModuleRegistryService
  tenant_status: string | null
  tenant_active: boolean
}

export interface ModulesResponse {
  data: ErpModule[]
  active_codes: string[]
}
