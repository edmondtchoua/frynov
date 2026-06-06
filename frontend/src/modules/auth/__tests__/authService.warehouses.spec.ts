import { describe, it, expect, vi, afterEach } from 'vitest'
import client from '@/api/client'
import { authService } from '@/modules/auth/services/authService'

// Sprint 20 multi-sites: managers assign the warehouses a member's data access is scoped to.
describe('authService.setUserWarehouses', () => {
  afterEach(() => vi.clearAllMocks())

  it('PUTs the selected warehouse ids for the member', async () => {
    vi.mocked(client.put).mockResolvedValue({ data: { data: { id: 'u1', warehouse_ids: ['w1', 'w2'] } } } as any)

    const res = await authService.setUserWarehouses('u1', ['w1', 'w2'])

    expect(client.put).toHaveBeenCalledWith('/api/workspace/users/u1/warehouses', { warehouse_ids: ['w1', 'w2'] })
    expect(res.warehouse_ids).toEqual(['w1', 'w2'])
  })

  it('sends an empty array to clear a restriction (back to all sites)', async () => {
    vi.mocked(client.put).mockResolvedValue({ data: { data: { id: 'u1', warehouse_ids: [] } } } as any)

    await authService.setUserWarehouses('u1', [])

    expect(client.put).toHaveBeenCalledWith('/api/workspace/users/u1/warehouses', { warehouse_ids: [] })
  })
})
