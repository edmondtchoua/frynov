<template>
  <StateBlock variant="empty" :title="$t('notFound.title')" :message="$t('notFound.message')">
    <template #action>
      <BaseButton variant="primary" @click="router.push('/dashboard')">{{ $t('notFound.back') }}</BaseButton>
    </template>
  </StateBlock>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseButton from '@/shared/ui/BaseButton.vue'

const router = useRouter()
</script>
