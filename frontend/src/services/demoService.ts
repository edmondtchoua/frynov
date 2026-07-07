import client from '@/api/client'

export interface DemoRequestPayload {
  first_name?: string
  last_name?: string
  email: string
  phone?: string
  company?: string
  country?: string
  primary_need?: string
  modules?: string[]
  message?: string
  consent_contact: boolean
  consent_demo_email?: boolean
  source?: string
  locale?: string
  /** Honeypot anti-bot — laissé vide par les humains. */
  website?: string
}

export const demoService = {
  /** Soumet une demande de démo depuis le formulaire public. */
  async submitRequest(payload: DemoRequestPayload): Promise<{ message: string; id: string }> {
    const { data } = await client.post('/api/demo-requests', payload)
    return data
  },
}
