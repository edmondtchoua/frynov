import client from '@/api/client'

export interface DemoRequest {
  id: string
  first_name?: string | null
  last_name?: string | null
  email: string
  phone?: string | null
  company?: string | null
  country?: string | null
  primary_need?: string | null
  modules?: string[] | null
  message?: string | null
  status: string
  internal_notes?: string | null
  rejection_reason?: string | null
  demo_tenant_id?: string | null
  demo_access_expires_at?: string | null
  access_sent_at?: string | null
  access_email_status?: string | null
  last_demo_login_at?: string | null
  converted_at?: string | null
  created_at: string
}

export interface DemoStats {
  total: number
  new: number
  sent: number
  expired: number
  rejected: number
  converted: number
  active_demo: number
}

export interface DemoListResponse {
  data: { data: DemoRequest[]; current_page: number; last_page: number; total: number }
  stats: DemoStats
}

const base = '/api/admin/demo-requests'

export const demoAdminService = {
  async list(params: { status?: string; q?: string; page?: number } = {}): Promise<DemoListResponse> {
    const { data } = await client.get(base, { params })
    return data
  },
  async approve(id: string): Promise<DemoRequest> {
    return (await client.post(`${base}/${id}/approve`)).data.data
  },
  async resend(id: string): Promise<DemoRequest> {
    return (await client.post(`${base}/${id}/resend`)).data.data
  },
  async reject(id: string, reason?: string): Promise<DemoRequest> {
    return (await client.post(`${base}/${id}/reject`, { reason })).data.data
  },
  async expire(id: string): Promise<DemoRequest> {
    return (await client.post(`${base}/${id}/expire`)).data.data
  },
  async convert(id: string): Promise<DemoRequest> {
    return (await client.post(`${base}/${id}/convert`)).data.data
  },
  async saveNotes(id: string, internal_notes: string): Promise<DemoRequest> {
    return (await client.patch(`${base}/${id}/notes`, { internal_notes })).data.data
  },
}
