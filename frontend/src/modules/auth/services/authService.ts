import client from '@/api/client'
import type { AuthResponse, LoginCredentials } from '../types'

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const { data } = await client.post<AuthResponse>('/api/auth/login', credentials)
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
