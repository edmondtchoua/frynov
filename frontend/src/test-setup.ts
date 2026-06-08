import { vi }         from 'vitest'
import { config }     from '@vue/test-utils'
import { createPinia } from 'pinia'
import { i18n }       from '@/i18n'

// Global Vue test-utils configuration.
// NOTE: PrimeVue is intentionally excluded — primeicons@7 has broken package exports.
// i18n is registered globally so `$t` is defined in every mounted component
// (default locale = fr → French assertions keep passing).
config.global.plugins = [
  createPinia(),
  i18n,
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