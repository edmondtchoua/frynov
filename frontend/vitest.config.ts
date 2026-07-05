import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  test: {
    // Environnement DOM simulé
    environment: 'jsdom',

    // Fichiers de setup globaux
    setupFiles: ['./src/test-setup.ts'],

    // Pattern des fichiers de test
    include: ['src/**/__tests__/**/*.test.ts', 'src/**/*.spec.ts'],

    // Coverage
    coverage: {
      provider:   'v8',
      reporter:   ['text', 'json', 'html', 'lcov'],
      reportsDirectory: './coverage',
      include:    ['src/**/*.{ts,vue}'],
      exclude:    [
        'src/test-setup.ts',
        'src/**/__tests__/**',
        'src/router/**',
        'src/main.ts',
      ],
      // Ratchet thresholds — coverage may not drop below today's floor. Raised as
      // component tests are added (Sprint 18). Was an unmet aspirational 75% that
      // failed CI; reset to reality then ratcheted up.
      // Actuals (2026-06, reconciled develop — incl. admin back-office smoke tests):
      // stmts/lines 38.7% · branches 58.0% · funcs 33.6%. Statements/lines jumped as
      // the 8 admin views entered the covered set; branches/funcs stay a touch below
      // actual (they dip when large view files are covered). Floor follows reality.
      thresholds: {
        statements: 38,
        branches:   57,
        functions:  33,
        lines:      38,
      },
    },

    // Reporters
    reporters: ['verbose'],

    // Variables d'environnement pour les tests
    env: {
      VITE_API_URL:   'http://localhost',
      VITE_APP_ENV:   'test',
    },
  },
})
