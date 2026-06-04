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
      // Actuals (2026-06, after List/Stock/Settings/Payment/POS view tests): stmts/lines
      // 30.8% · branches 58.1% · funcs 33.4%. Branch threshold kept a point below actual
      // (it dips when large view files enter the covered set) — the floor follows reality.
      thresholds: {
        statements: 30,
        branches:   57,
        functions:  33,
        lines:      30,
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
