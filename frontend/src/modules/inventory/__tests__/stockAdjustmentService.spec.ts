import { describe, it, expect, vi, afterEach } from 'vitest'
import client from '@/api/client'
import { stockAdjustmentService } from '@/modules/inventory/services/stockAdjustmentService'

describe('stockAdjustmentService', () => {
  afterEach(() => vi.clearAllMocks())

  it('lists the pending review queue', async () => {
    vi.mocked(client.get).mockResolvedValue({ data: { data: [{ id: 'a1' }], current_page: 1 } } as any)
    const res = await stockAdjustmentService.pending()
    expect(client.get).toHaveBeenCalledWith('/api/inventory/adjustments')
    expect(res.data[0].id).toBe('a1')
  })

  it('requests an adjustment with the new absolute quantity + reason', async () => {
    vi.mocked(client.post).mockResolvedValue({ data: { message: 'ok' } } as any)
    await stockAdjustmentService.request({ stock_id: 's1', new_quantity: 5, reason: 'count' })
    expect(client.post).toHaveBeenCalledWith('/api/inventory/adjustments', { stock_id: 's1', new_quantity: 5, reason: 'count' })
  })

  it('approves and rejects by id', async () => {
    vi.mocked(client.post).mockResolvedValue({ data: {} } as any)
    await stockAdjustmentService.approve('a1')
    expect(client.post).toHaveBeenCalledWith('/api/inventory/adjustments/a1/approve')
    await stockAdjustmentService.reject('a1', 'wrong count')
    expect(client.post).toHaveBeenCalledWith('/api/inventory/adjustments/a1/reject', { reason: 'wrong count' })
  })
})
