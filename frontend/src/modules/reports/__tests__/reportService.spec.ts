import { describe, it, expect, vi, afterEach } from 'vitest'
import client from '@/api/client'
import { reportService } from '@/modules/reports/services/reportService'

// Sprint 20 multi-sites: report views can scope sales/stock by warehouse.
describe('reportService — warehouse scoping', () => {
  afterEach(() => { vi.clearAllMocks() })

  it('forwards warehouse_id to the sales report', async () => {
    vi.mocked(client.get).mockResolvedValue({ data: {} } as any)
    await reportService.sales('30d', 'wh-1')
    expect(client.get).toHaveBeenCalledWith('/api/reports/sales', { params: { period: '30d', warehouse_id: 'wh-1' } })
  })

  it('forwards warehouse_id to the stock report', async () => {
    vi.mocked(client.get).mockResolvedValue({ data: {} } as any)
    await reportService.stock('wh-1')
    expect(client.get).toHaveBeenCalledWith('/api/reports/stock', { params: { warehouse_id: 'wh-1' } })
  })

  it('sends warehouse_id undefined when no site is selected (all sites)', async () => {
    vi.mocked(client.get).mockResolvedValue({ data: {} } as any)
    await reportService.sales('7d')
    expect(client.get).toHaveBeenCalledWith('/api/reports/sales', { params: { period: '7d', warehouse_id: undefined } })
  })

  // RC-5G — special products report
  it('fetches the special-products report scoped by warehouse', async () => {
    vi.mocked(client.get).mockResolvedValue({ data: { total_inventory_value: 1005000 } } as any)
    const res = await reportService.specialProducts('wh-1')
    expect(client.get).toHaveBeenCalledWith('/api/reports/special-products', { params: { warehouse_id: 'wh-1' } })
    expect(res.total_inventory_value).toBe(1005000)
  })
})
