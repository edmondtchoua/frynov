import client from '@/api/client'
import type { CreateDeliveryPayload, Delivery } from '../types'

interface PaginatedDeliveries {
  data: Delivery[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const deliveryService = {
  // ── List ───────────────────────────────────────────────────────────────────
  list(params?: { page?: number; per_page?: number; status?: string; order_id?: string }): Promise<PaginatedDeliveries> {
    return client.get('/api/deliveries', { params }).then(r => r.data)
  },

  // ── Single ─────────────────────────────────────────────────────────────────
  get(id: string): Promise<{ data: Delivery }> {
    return client.get(`/api/deliveries/${id}`).then(r => r.data)
  },

  // ── Create ─────────────────────────────────────────────────────────────────
  create(data: CreateDeliveryPayload): Promise<{ data: Delivery }> {
    return client.post('/api/deliveries', data).then(r => r.data)
  },

  // ── Status transitions ─────────────────────────────────────────────────────
  dispatch(id: string): Promise<{ data: Delivery }> {
    return client.post(`/api/deliveries/${id}/dispatch`).then(r => r.data)
  },

  deliver(id: string): Promise<{ data: Delivery }> {
    return client.post(`/api/deliveries/${id}/deliver`).then(r => r.data)
  },

  fail(id: string, reason: string): Promise<{ data: Delivery }> {
    return client.post(`/api/deliveries/${id}/fail`, { reason }).then(r => r.data)
  },

  // ── For an order ───────────────────────────────────────────────────────────
  forOrder(orderId: string): Promise<{ data: Delivery[] }> {
    return client.get(`/api/orders/${orderId}/deliveries`).then(r => r.data)
  },
}
