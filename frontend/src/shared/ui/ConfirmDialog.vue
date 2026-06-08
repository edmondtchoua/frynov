<template>
  <BaseModal
    :model-value="confirmState.open"
    variant="center"
    size="sm"
    :title="confirmState.title"
    @update:model-value="(v: boolean) => { if (!v) settleConfirm(false) }"
  >
    <p class="confirm-dialog__message">{{ confirmState.message }}</p>
    <template #footer>
      <button class="btn btn-ghost" type="button" @click="settleConfirm(false)">
        {{ confirmState.cancelLabel }}
      </button>
      <button
        :class="['btn', confirmState.danger ? 'btn-danger' : 'btn-primary']"
        type="button"
        data-test="confirm-accept"
        @click="settleConfirm(true)"
      >
        {{ confirmState.confirmLabel }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
/** Host unique du système de confirmation centrée (cf. composable `useConfirm`).
 *  À monter UNE SEULE FOIS (dans `App.vue`). Piloté par l'état partagé `confirmState` ;
 *  toute fermeture (croix / Échap / clic-extérieur) vaut annulation. */
import BaseModal from '@/shared/ui/BaseModal.vue'
import { confirmState, settleConfirm } from '@/composables/useConfirm'
</script>

<style scoped>
.confirm-dialog__message {
  margin: 0;
  color: var(--gray-600, #475569);
  font-size: 0.95rem;
  line-height: 1.55;
}
</style>
