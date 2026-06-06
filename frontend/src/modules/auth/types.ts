export interface Tenant {
  id: string
  name: string
  slug: string
  domain?: string | null
  plan: string
  status: string
  subscription_status: string
  onboarded?: boolean
  settings?: Record<string, unknown>
}

export interface Subscription {
  id: string
  plan_code: string
  plan_name: string
  plan_price_monthly?: number | null
  plan_price_yearly?: number | null
  currency?: string
  max_users?: number | null
  max_products?: number | null
  max_monthly_orders?: number | null
  features?: string[]
  status: 'trialing' | 'active' | 'suspended' | 'cancelled' | 'pending_approval'
  trial_ends_at: string | null
  current_period_end: string | null
}

export interface AuthUser {
  id: string
  name: string
  email: string
  is_super_admin: boolean
  tenant_id: string | null
  tenant?: Tenant
  subscription?: Subscription | null
  active_modules?: string[]
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

// ── Workspace management types ────────────────────────────────────────────────

export interface WorkspaceUser {
  id: string
  name: string
  email: string
  roles: string[]
  is_active: boolean
  created_at: string | null
}

export interface WorkspaceSettings {
  id: string
  name: string
  slug: string
  domain: string | null
  settings: {
    country?: string
    currency?: string
    timezone?: string
    locale?: string
    phone?: string | null
    address?: string | null
    website?: string | null
    order_prefix?: string
    [key: string]: unknown
  }
}
