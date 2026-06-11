export type OrderStatus = 'draft' | 'confirmed' | 'fulfilled' | 'cancelled'

export interface OrderLine {
  id: string
  order_id: string
  product_id: string
  variant_id: string | null
  sku: string
  name: string
  quantity: number
  unit_price_cents: number
}

export interface Order {
  id: string
  tenant_id: string
  customer_id: string | null
  number: string
  status: OrderStatus
  total_amount: number
  currency: string
  note: string | null
  performed_by: string | null
  fulfilled_at: string | null
  cancelled_at: string | null
  created_at: string
  updated_at: string
  lines: OrderLine[]
}

// RC-5C — unité sérialisée (IMEI/VIN…) rattachée à une ligne de commande.
export interface OrderUnit {
  id: string
  product_id: string
  variant_id: string | null
  order_line_id: string | null
  customer_id: string | null
  serial_type: string
  serial_value: string
  condition: string
  status: 'in_stock' | 'reserved' | 'sold' | 'returned' | 'repair' | 'quarantine' | 'lost' | 'scrapped'
  sold_at: string | null
}

// RC-5D — contrat de garantie généré à la vente, rattaché à la commande (et à l'unité si sérialisé).
export interface OrderWarranty {
  id: string
  product_id: string
  inventory_unit_id: string | null
  order_line_id: string
  customer_id: string | null
  serial_value: string | null
  starts_at: string
  ends_at: string
  status: 'active' | 'expired' | 'void'
  product_name: string | null
  policy_name: string | null
}

// RC-5E — droit d'accès digital (téléchargement/licence) accordé pour la commande. Sans secret
// dans la liste scopée commande (le jeton/la clé ne s'obtiennent que via l'endpoint d'accès).
export interface OrderEntitlement {
  id: string
  product_id: string
  order_line_id: string
  customer_id: string | null
  fulfillment_type: 'download' | 'license'
  status: 'active' | 'revoked' | 'expired'
  granted_at: string | null
  expires_at: string | null
  product_name: string | null
}

// RC-5F — réclamation SAV rattachée à un contrat de garantie.
export interface OrderWarrantyClaim {
  id: string
  warranty_contract_id: string
  reason: 'defect' | 'breakage' | 'malfunction' | 'other'
  status: 'open' | 'in_repair' | 'resolved' | 'replaced' | 'rejected'
  out_of_warranty: boolean
}

export interface CreateOrderItem {
  product_id: string
  variant_id?: string | null
  quantity: number
  // NOTE: unit_price_cents is NOT sent — the backend always resolves price
  // server-side from the catalog (security: CreateOrderRequest excludes it).
}

export interface CreateOrderPayload {
  items: CreateOrderItem[]
  customer_id?: string | null
  note?: string | null
}
