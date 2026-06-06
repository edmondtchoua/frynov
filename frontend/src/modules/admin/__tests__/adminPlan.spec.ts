import { describe, it, expect, vi, afterEach } from 'vitest'
import client from '@/api/client'
import { adminService } from '@/modules/admin/services/adminService'

describe('adminService.updatePlan', () => {
  afterEach(() => vi.clearAllMocks())

  it('PATCHes plan limits (null = unlimited)', async () => {
    vi.mocked(client.patch).mockResolvedValue({ data: { id: 'p1' } } as any)

    await adminService.updatePlan('p1', {
      name: 'Pro',
      max_users: 5,
      limits: { max_products: 500, max_warehouses: null },
    })

    expect(client.patch).toHaveBeenCalledWith('/api/admin/plans/p1', {
      name: 'Pro',
      max_users: 5,
      limits: { max_products: 500, max_warehouses: null },
    })
  })
})
