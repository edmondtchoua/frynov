import client from '@/api/client'

/** A tenant role as returned by GET /api/workspace/roles. */
export interface TenantRole {
  id: number | string
  name: string
  /** true = tenant-owned custom role (editable/deletable); false = shared base role (read-only). */
  is_custom: boolean
  permissions: string[]
}

/** Shape of GET /api/workspace/roles. */
export interface RolesResponse {
  /** Base (read-only) + this tenant's custom roles, sorted by name. */
  data: TenantRole[]
  /** Permission names this tenant may grant to a custom role (plan/module-bounded). */
  grantable: string[]
}

export interface RolePayload {
  name?: string
  permissions?: string[]
}

/**
 * RBAC Phase B2 — tenant-admin management of custom roles.
 * All endpoints live under /api/workspace/roles and are admin-tenant only.
 */
export const roleService = {
  /** GET /api/workspace/roles → { data: roles, grantable: [perms] } */
  async list(): Promise<RolesResponse> {
    const { data } = await client.get('/api/workspace/roles')
    return {
      data: Array.isArray(data?.data) ? data.data : [],
      grantable: Array.isArray(data?.grantable) ? data.grantable : [],
    }
  },

  /** POST /api/workspace/roles */
  create(payload: { name: string; permissions: string[] }): Promise<TenantRole> {
    return client.post('/api/workspace/roles', payload).then(r => r.data)
  },

  /** PATCH /api/workspace/roles/{id} */
  update(id: number | string, payload: RolePayload): Promise<TenantRole> {
    return client.patch(`/api/workspace/roles/${id}`, payload).then(r => r.data)
  },

  /** DELETE /api/workspace/roles/{id} */
  remove(id: number | string): Promise<void> {
    return client.delete(`/api/workspace/roles/${id}`).then(() => undefined)
  },
}
