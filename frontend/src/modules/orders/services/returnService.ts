import api from '@/services/api'

export interface OrderReturnLine {
  order_line_id: string
  quantity: number
  condition?: 'resalable' | 'damaged' | 'destroyed'
  reason?: string
}

export interface CreateReturnPayload {
  reason: 'defective' | 'wrong_item' | 'changed_mind' | 'damaged' | 'other'
  resolution: 'refund' | 'exchange' | 'store_credit'
  customer_note?: string
  lines: OrderReturnLine[]
}

export const returnService = {
  list: (params?: { status?: string; order_id?: string }) =>
    api.get('/orders/returns', { params }),

  get: (id: string) =>
    api.get(`/orders/returns/${id}`),

  create: (orderId: string, payload: CreateReturnPayload) =>
    api.post(`/orders/${orderId}/returns`, payload),

  approve: (id: string, internalNote?: string) =>
    api.post(`/orders/returns/${id}/approve`, { internal_note: internalNote }),

  restock: (id: string, warehouseId?: string) =>
    api.post(`/orders/returns/${id}/restock`, { warehouse_id: warehouseId }),

  reject: (id: string, reason: string) =>
    api.post(`/orders/returns/${id}/reject`, { reason }),
}
