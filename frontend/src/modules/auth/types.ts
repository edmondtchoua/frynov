export interface Tenant {
  id: string
  name: string
  slug: string
  plan: string
  status: string
  settings?: Record<string, unknown>
}

export interface AuthUser {
  id: string
  name: string
  email: string
  tenant_id: string | null
  tenant?: Tenant
  roles: string[]
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface AuthResponse {
  token: string
  user: AuthUser
}
