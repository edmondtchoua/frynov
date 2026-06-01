import { vi }         from 'vitest'
import { config }     from '@vue/test-utils'
import { createPinia } from 'pinia'

// Global Vue test-utils configuration.
// NOTE: PrimeVue is intentionally excluded — primeicons@7 has broken package exports.
config.global.plugins = [
  createPinia(),
]

// Mock global de l'API axios
vi.mock('@/api/client', () => ({
  default: {
    get:    vi.fn(),
    post:   vi.fn(),
    put:    vi.fn(),
    patch:  vi.fn(),
    delete: vi.fn(),
  },
}))