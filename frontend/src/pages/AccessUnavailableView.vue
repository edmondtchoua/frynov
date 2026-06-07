<template>
  <div class="access-unavailable">
    <StateBlock variant="forbidden" :title="title" :message="message">
      <template #action>
        <BaseButton variant="primary" @click="goSettings">{{ ctaLabel }}</BaseButton>
        <BaseButton variant="ghost" @click="goBack">Retour</BaseButton>
      </template>
    </StateBlock>
  </div>
</template>

<script setup lang="ts">
/**
 * Contextual "access unavailable" page (audit UX-01/UX-05): module disabled,
 * permission missing, or plan quota reached — never a blank page.
 * Query: ?reason=module|permission|quota&module=<label>
 */
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseButton from '@/shared/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()

const reason = computed(() => String(route.query.reason ?? 'permission'))
const moduleLabel = computed(() => (route.query.module ? String(route.query.module) : null))

const title = computed(() => ({
  module:     'Module non activé',
  permission: 'Accès non autorisé',
  quota:      'Limite de votre plan atteinte',
} as Record<string, string>)[reason.value] ?? 'Accès indisponible')

const message = computed(() => ({
  module:     moduleLabel.value
    ? `Le module « ${moduleLabel.value} » n'est pas activé pour votre espace de travail.`
    : "Ce module n'est pas activé pour votre espace de travail.",
  permission: "Vous n'avez pas la permission d'accéder à cette page. Contactez un administrateur de votre espace.",
  quota:      'Cette action dépasse les limites de votre plan actuel. Mettez à niveau pour continuer.',
} as Record<string, string>)[reason.value] ?? "Cette ressource n'est pas disponible.")

const ctaLabel = computed(() => (reason.value === 'permission' ? 'Aller aux paramètres' : "Voir l'abonnement"))

function goSettings() { router.push('/settings') }
function goBack() { router.back() }
</script>

<style scoped>
.access-unavailable { display: flex; align-items: center; justify-content: center; min-height: 50vh; }
.access-unavailable :deep(.state-block__action) { display: flex; gap: 0.6rem; flex-wrap: wrap; justify-content: center; }
</style>
