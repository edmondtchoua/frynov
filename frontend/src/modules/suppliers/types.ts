export interface Supplier {
  id: string
  code: string | null
  name: string
  email: string | null
  phone: string | null
  contact_name: string | null
  address: Record<string, string> | null
  payment_terms: string | null
  notes: string | null
  status: 'active' | 'inactive'
  products_count?: number
  created_at: string
  updated_at: string
}

export interface CreateSupplierPayload {
  name: string
  email?: string
  phone?: string
  contact_name?: string
  payment_terms?: string
  notes?: string
  status?: 'active' | 'inactive'
}

export interface UpdateSupplierPayload extends Partial<CreateSupplierPayload> {}
