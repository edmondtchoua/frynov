import { vi, beforeEach } from 'vitest'
import { config }     from '@vue/test-utils'
import { createPinia } from 'pinia'
import { i18n, setLocale } from '@/i18n'
import { vFocusTrap } from '@/directives/focusTrap'

// jsdom's navigator.language is en-US, so i18n would default to English in tests.
// Force French before every test (the source-of-truth locale our assertions expect);
// specs that exercise the EN switch call setLocale('en') within the test and reset after.
beforeEach(() => setLocale('fr'))

// Global Vue test-utils configuration.
// NOTE: PrimeVue is intentionally excluded — primeicons@7 has broken package exports.
// i18n is registered globally so `$t` is defined in every mounted component
// (default locale = fr → French assertions keep passing).
config.global.plugins = [
  createPinia(),
  i18n,
]

// `v-focus-trap` (used by BaseModal) registered globally so modal-opening specs
// don't each need to register it (and no "failed to resolve directive" warning).
config.global.directives = { 'focus-trap': vFocusTrap }

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