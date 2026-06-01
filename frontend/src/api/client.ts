import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosError } from 'axios'
import { progressStart, progressDone, progressFail } from '@/composables/useProgress'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? ''

const client: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  timeout: 15_000,
})

// ── Request interceptor — inject Bearer token + tenant slug + start progress ──
client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const slug = localStorage.getItem('tenant_slug')
  if (slug) config.headers['X-Tenant-Slug'] = slug

  progressStart()
  return config
})

// ── Response interceptor — handle 401 globally + stop progress ────────────────
client.interceptors.response.use(
  (response) => {
    progressDone()
    return response
  },
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      progressFail()
      localStorage.removeItem('auth_token')
      localStorage.removeItem('tenant_slug')
      // Let the router guard redirect — avoid circular import with router here
      window.dispatchEvent(new CustomEvent('auth:expired'))
    } else if (error.code === 'ERR_CANCELED') {
      // Aborted requests don't count as errors for the progress bar
      progressDone()
    } else {
      progressFail()
    }
    return Promise.reject(error)
  },
)

export default client
