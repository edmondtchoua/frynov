import client from '@/api/client'
import type { PaginatedResponse } from '@/api/types'
import type { CreateOrderPayload, Order, OrderUnit, OrderWarranty } from '../types'

export const orderService = {
  list(params?: { status?: string; search?: string; from_date?: string; to_date?: string; warehouse_id?: string; page?: number; per_page?: number }) {
    return client.get<PaginatedResponse<Order>>('/api/orders', { params }).then(r => r.data)
  },

  get(id: string) {
    return client.get<Order>(`/api/orders/${id}`).then(r => r.data)
  },

  // RC-5C — unités sérialisées (IMEI/VIN) rattachées à la commande (traçabilité).
  units(id: string) {
    return client.get<{ data: OrderUnit[]; count: number }>(`/api/orders/${id}/units`).then(r => r.data)
  },

  // RC-5D — contrats de garantie générés pour la commande.
  warranties(id: string) {
    return client.get<{ data: OrderWarranty[]; count: number }>(`/api/warranties/orders/${id}`).then(r => r.data)
  },

  create(payload: CreateOrderPayload) {
    return client.post<Order>('/api/orders', payload).then(r => r.data)
  },

  confirm(id: string) {
    return client.post<Order>(`/api/orders/${id}/confirm`).then(r => r.data)
  },

  fulfill(id: string) {
    return client.post<Order>(`/api/orders/${id}/fulfill`).then(r => r.data)
  },

  cancel(id: string) {
    return client.post<Order>(`/api/orders/${id}/cancel`).then(r => r.data)
  },
}
