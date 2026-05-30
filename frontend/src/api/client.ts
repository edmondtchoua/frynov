import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosError } from 'axios'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? ''

const client: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  timeout: 15_000,
})

// ── Request interceptor — inject Bearer token + tenant slug ────────────────
client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const slug = localStorage.getItem('tenant_slug')
  if (slug) config.headers['X-Tenant-Slug'] = slug

  return config
})

// ── Response interceptor — handle 401 globally ────────────────────────────
client.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('tenant_slug')
      // Let the router guard redirect — avoid circular import with router here
      window.dispatchEvent(new CustomEvent('auth:expired'))
    }
    return Promise.reject(error)
  },
)

export default client
