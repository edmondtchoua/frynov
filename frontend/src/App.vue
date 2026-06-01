<template>
  <!-- Global progress bar — visible on every HTTP request and navigation -->
  <AppProgressBar />

  <!-- Landing / public pages — self-contained layout -->
  <template v-if="!layoutComponent">
    <RouterView v-slot="{ Component, route }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </template>

  <!-- Auth layout (login, register) -->
  <AuthLayout v-else-if="layoutComponent === 'auth'">
    <RouterView v-slot="{ Component, route }">
      <Transition name="page-auth" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </AuthLayout>

  <!-- Admin back-office shell -->
  <AdminLayout v-else-if="layoutComponent === 'admin'">
    <RouterView v-slot="{ Component, route }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </AdminLayout>

  <!-- App shell (authenticated tenant users) -->
  <AppLayout v-else>
    <RouterView v-slot="{ Component, route }">
      <Transition name="page" mode="out-in">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout           from '@/layouts/AppLayout.vue'
import AuthLayout          from '@/layouts/AuthLayout.vue'
import AdminLayout         from '@/layouts/AdminLayout.vue'
import AppProgressBar      from '@/shared/components/AppProgressBar.vue'
import { useSessionTimeout } from '@/composables/useSessionTimeout'

// Start the inactivity-based session timeout tracker
// No-op when not authenticated; auto-restarts on login
useSessionTimeout()

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
