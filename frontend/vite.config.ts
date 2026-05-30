import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    vue(),
  ],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  server: {
    port: 5173,
    host: '0.0.0.0',
    proxy: {
      // Proxifier les appels API vers le backend Laravel
      '/api': {
        target:      'http://nginx:80',
        changeOrigin: true,
      },
      '/webhooks': {
        target:      'http://nginx:80',
        changeOrigin: true,
      },
    },
  },

  build: {
    outDir:        'dist',
    sourcemap:     false,
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      output: {
        manualChunks: {
          // Séparer les grosses dépendances en chunks distincts
          'vendor-vue':     ['vue', 'vue-router', 'pinia'],
          'vendor-query':   ['@tanstack/vue-query'],
          'vendor-prime':   ['primevue', 'primeicons'],
        },
      },
    },
  },
})
