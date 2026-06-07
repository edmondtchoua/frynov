import { describe, it, expect, vi, beforeEach } from 'vitest'
import { roleService } from '@/modules/settings/services/roleService'

vi.mock('@/api/client', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))
import client from '@/api/client'

describe('roleService', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('list returns { data, grantable } from /api/workspace/roles', async () => {
    vi.mocked(client.get).mockResolvedValue({
      data: { data: [{ id: 1, name: 'Resp', is_custom: true, permissions: [] }], grantable: ['products.create'] },
    })
    const res = await roleService.list()
    expect(vi.mocked(client.get)).toHaveBeenCalledWith('/api/workspace/roles')
    expect(res.data).toHaveLength(1)
    expect(res.grantable).toContain('products.create')
  })

  it('list tolerates a response missing data/grantable', async () => {
    vi.mocked(client.get).mockResolvedValue({ data: {} })
    const res = await roleService.list()
    expect(res.data).toEqual([])
    expect(res.grantable).toEqual([])
  })

  it('create posts name + permissions and resolves the role', async () => {
    vi.mocked(client.post).mockResolvedValue({ data: { id: 2, name: 'R', is_custom: true, permissions: ['orders.manage'] } })
    const role = await roleService.create({ name: 'R', permissions: ['orders.manage'] })
    expect(vi.mocked(client.post)).toHaveBeenCalledWith('/api/workspace/roles', { name: 'R', permissions: ['orders.manage'] })
    expect(role.id).toBe(2)
  })

  it('update patches the role by id', async () => {
    vi.mocked(client.patch).mockResolvedValue({ data: { id: 5, name: 'New', is_custom: true, permissions: [] } })
    await roleService.update(5, { name: 'New' })
    expect(vi.mocked(client.patch)).toHaveBeenCalledWith('/api/workspace/roles/5', { name: 'New' })
  })

  it('remove deletes the role by id', async () => {
    vi.mocked(client.delete).mockResolvedValue({ data: {} })
    await roleService.remove(7)
    expect(vi.mocked(client.delete)).toHaveBeenCalledWith('/api/workspace/roles/7')
  })
})
