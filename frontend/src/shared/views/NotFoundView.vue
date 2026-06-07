<template>
  <StateBlock variant="empty" title="Page introuvable" message="La page que vous cherchez n'existe pas ou a été déplacée.">
    <template #action>
      <BaseButton variant="primary" @click="router.push('/dashboard')">Retour au tableau de bord</BaseButton>
    </template>
  </StateBlock>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseButton from '@/shared/ui/BaseButton.vue'

const router = useRouter()
</script>
