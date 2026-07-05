import client from '@/api/client'
import type { PaginatedResponse } from '@/api/types'
import type { CreateOrderPayload, Order, OrderUnit, OrderWarranty, OrderEntitlement, OrderWarrantyClaim } from '../types'

export const orderService = {
  list(params?: { status?: string; search?: string; from_date?: string; to_date?: string; warehouse_id?: string; page?: number; per_page?: number }) {
    // RC-18 (C-3) — le back renvoie un paginator Laravel À PLAT (current_page/last_page au niveau
    // racine, pas de bloc `meta`) : on normalise ici pour que la vue lise toujours {data, meta}.
    // Sans cela, `meta` était undefined → pagination invisible, pages 2+ inaccessibles.
    return client.get<any>('/api/orders', { params }).then(r => {
      const d = r.data
      return {
        data: d.data ?? [],
        meta: d.meta ?? {
          current_page: d.current_page ?? 1,
          last_page:    d.last_page ?? 1,
          per_page:     d.per_page ?? 20,
          total:        d.total ?? (d.data?.length ?? 0),
        },
      } as PaginatedResponse<Order>
    })
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

  // RC-5E — droits d'accès digitaux (download/license) générés pour la commande.
  entitlements(id: string) {
    return client.get<{ data: OrderEntitlement[]; count: number }>(`/api/digital/orders/${id}/entitlements`).then(r => r.data)
  },

  // RC-5F — réclamations SAV rattachées aux contrats de garantie de la commande.
  warrantyClaims(id: string) {
    return client.get<{ data: OrderWarrantyClaim[]; count: number }>(`/api/warranties/orders/${id}/claims`).then(r => r.data)
  },

  // RC-5F — ouvre une réclamation SAV sur un contrat de garantie.
  openWarrantyClaim(contractId: string, payload: { reason: string; description?: string; override?: boolean }) {
    return client.post<{ data: OrderWarrantyClaim }>(`/api/warranties/contracts/${contractId}/claims`, payload).then(r => r.data)
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
