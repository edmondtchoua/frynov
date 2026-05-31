<template>
  <!-- Landing / public pages — self-contained layout -->
  <RouterView v-if="!layoutComponent" />

  <!-- Auth layout (login, register) -->
  <AuthLayout v-else-if="layoutComponent === 'auth'">
    <RouterView />
  </AuthLayout>

  <!-- Admin back-office shell -->
  <AdminLayout v-else-if="layoutComponent === 'admin'">
    <RouterView />
  </AdminLayout>

  <!-- App shell (authenticated tenant users) -->
  <AppLayout v-else>
    <RouterView />
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout   from '@/layouts/AppLayout.vue'
import AuthLayout  from '@/layouts/AuthLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

const route = useRoute()

// 'app' | 'auth' | 'admin' | null (landing/public pages manage their own layout)
const layoutComponent = computed(() => {
  const layout = route.meta.layout
  if (layout === 'auth')  return 'auth'
  if (layout === 'app')   return 'app'
  if (layout === 'admin') return 'admin'
  // Admin routes inherit from parent meta
  if (route.matched.some(r => r.meta.layout === 'admin')) return 'admin'
  return null
})
</script>
