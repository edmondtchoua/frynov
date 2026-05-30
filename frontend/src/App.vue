<template>
  <!-- Landing / public pages — self-contained layout -->
  <RouterView v-if="!layoutComponent" />

  <!-- Auth layout (login, register) -->
  <AuthLayout v-else-if="layoutComponent === 'auth'">
    <RouterView />
  </AuthLayout>

  <!-- App shell (authenticated) -->
  <AppLayout v-else>
    <RouterView />
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout  from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()

// 'app' | 'auth' | undefined (landing/public pages manage their own layout)
const layoutComponent = computed(() => {
  const layout = route.meta.layout
  if (layout === 'auth') return 'auth'
  if (layout === 'app')  return 'app'
  return null
})
</script>
