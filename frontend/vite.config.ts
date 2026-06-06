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
      // VITE_PROXY_TARGET=http://nginx:80 (Docker) ou http://localhost:8000 (local)
      '/api': {
        target:      process.env.VITE_PROXY_TARGET ?? 'http://localhost:8000',
        changeOrigin: true,
      },
      '/webhooks': {
        target:      process.env.VITE_PROXY_TARGET ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },

  // primeicons@7 has empty main/module/exports — tell Vite to skip it
  optimizeDeps: {
    exclude: ['primeicons'],
  },

  build: {
    outDir:        'dist',
    sourcemap:     false,
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      // Treat primeicons as external (CSS is handled separately if needed)
      external: ['primeicons'],
      output: {
        manualChunks: {
          'vendor-vue':   ['vue', 'vue-router', 'pinia'],
          'vendor-query': ['@tanstack/vue-query'],
          'vendor-prime': ['primevue'],
        },
      },
    },
  },
})
