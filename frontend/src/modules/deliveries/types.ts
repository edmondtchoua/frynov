export type DeliveryStatus = 'pending' | 'dispatched' | 'in_transit' | 'delivered' | 'failed'

export interface DeliveryAddress {
  street?: string
  city?: string
  zip?: string
  country?: string
}

export interface Delivery {
  id: string
  tenant_id: string
  order_id: string | null
  order_number?: string | null
  status: DeliveryStatus
  address?: DeliveryAddress | null
  carrier?: string | null
  tracking_number?: string | null
  notes?: string | null
  dispatched_at?: string | null
  delivered_at?: string | null
  failed_at?: string | null
  failed_reason?: string | null
  created_at: string
  updated_at: string
}

export interface CreateDeliveryPayload {
  order_id?: string
  address?: DeliveryAddress
  carrier?: string
  tracking_number?: string
  notes?: string
}
