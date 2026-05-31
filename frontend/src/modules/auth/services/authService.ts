import client from '@/api/client'
import type { AuthResponse, LoginCredentials, RegisterPayload } from '../types'

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const { data } = await client.post<AuthResponse>('/api/auth/login', credentials)
    return data
  },

  async register(payload: RegisterPayload): Promise<AuthResponse> {
    const { data } = await client.post<AuthResponse>('/api/auth/register', payload)
    return data
  },

  async logout(): Promise<void> {
    await client.post('/api/auth/logout')
  },

  async me(): Promise<AuthResponse['user']> {
    const { data } = await client.get<AuthResponse['user']>('/api/auth/me')
    return data
  },
}
