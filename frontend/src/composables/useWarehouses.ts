/**
 * useWarehouses — loads the tenant's warehouses for site/branch filter dropdowns
 * (Sprint 20 multi-sites). Shared by the Orders and Payments list views so the
 * `GET /api/inventory/warehouses` fetch isn't duplicated per view.
 *
 * Fails soft: on error the list stays empty (the filter simply offers "all sites").
 */
import { ref } from 'vue'
import client from '@/api/client'

export interface WarehouseOption {
  id: string
  name: string
  code: string
  type: string
  is_default: boolean
}

export function useWarehouses() {
  const warehouses = ref<WarehouseOption[]>([])

  async function loadWarehouses(): Promise<void> {
    try {
      warehouses.value = (await client.get('/api/inventory/warehouses')).data.data ?? []
    } catch {
      warehouses.value = []
    }
  }

  return { warehouses, loadWarehouses }
}
