export interface CustomerAddressFields {
  street?: string
  city?: string
  zip?: string
  country?: string
}

export type CustomerAddress = CustomerAddressFields | string

export interface Customer {
  id: string
  name: string
  email?: string | null
  phone?: string | null
  address?: CustomerAddress | null
  notes?: string | null
  orders_count?: number
  created_at: string
  updated_at: string
}

export interface CreateCustomerPayload {
  name: string
  email?: string
  phone?: string
  address?: CustomerAddressFields
  notes?: string
}
