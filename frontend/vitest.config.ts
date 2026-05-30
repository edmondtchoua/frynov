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
      thresholds: {
        statements: 75,
        branches:   70,
        functions:  75,
        lines:      75,
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
