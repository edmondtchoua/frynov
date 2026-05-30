import { config } from '@vue/test-utils'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'

// Configuration globale pour tous les tests Vue
config.global.plugins = [
  createPinia(),
  PrimeVue,
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
