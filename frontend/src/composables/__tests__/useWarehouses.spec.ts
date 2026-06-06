import { describe, it, expect, vi, afterEach } from 'vitest'
import client from '@/api/client'
import { useWarehouses } from '@/composables/useWarehouses'

describe('useWarehouses', () => {
  afterEach(() => vi.clearAllMocks())

  it('loads the tenant warehouses from the inventory endpoint', async () => {
    const list = [{ id: 'w1', name: 'Dépôt A', code: 'WH-A', type: 'main', is_default: true }]
    vi.mocked(client.get).mockResolvedValue({ data: { data: list } } as any)

    const { warehouses, loadWarehouses } = useWarehouses()
    expect(warehouses.value).toEqual([]) // empty until loaded

    await loadWarehouses()

    expect(client.get).toHaveBeenCalledWith('/api/inventory/warehouses')
    expect(warehouses.value).toEqual(list)
  })

  it('fails soft to an empty list on error (filter just offers "all sites")', async () => {
    vi.mocked(client.get).mockRejectedValueOnce(new Error('network'))

    const { warehouses, loadWarehouses } = useWarehouses()
    await loadWarehouses()

    expect(warehouses.value).toEqual([])
  })
})
