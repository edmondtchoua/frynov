<template>
  <BaseModal :model-value="modelValue" :title="$t('catalog.wizard.title')" @update:model-value="$emit('update:modelValue', $event)">
    <div class="wiz">
      <!-- Étape courante -->
      <div class="wiz-step-label">{{ $t('catalog.wizard.stepOf', { n: step + 1, total: steps.length }) }}</div>
      <h4 class="wiz-question">{{ $t('catalog.wizard.' + steps[step] + '.q') }}</h4>

      <div class="wiz-options">
        <label v-for="opt in optionsFor(steps[step])" :key="opt"
               class="wiz-opt" :class="{ active: answers[steps[step]] === opt }">
          <input type="radio" class="sr-only" :name="steps[step]" :value="opt" v-model="answers[steps[step]]" />
          <span class="wiz-opt-title">{{ $t('catalog.wizard.' + steps[step] + '.' + opt) }}</span>
          <span class="wiz-opt-hint">{{ $t('catalog.wizard.' + steps[step] + '.' + opt + 'Hint') }}</span>
        </label>
      </div>

      <!-- Résumé final -->
      <div v-if="step === steps.length - 1 && answers.delivery" class="wiz-summary">
        {{ $t('catalog.wizard.summary') }}
        <strong>{{ $t('catalog.productShow.type.' + result.product_type) }}</strong> ·
        {{ $t('catalog.wizard.stock.' + answers.stock) }} ·
        {{ $t('catalog.wizard.delivery.' + answers.delivery) }}
      </div>
    </div>

    <template #footer>
      <button v-if="step > 0" class="btn btn-ghost" @click="step--">{{ $t('common.previous') }}</button>
      <button class="btn btn-ghost" @click="$emit('update:modelValue', false)">{{ $t('common.cancel') }}</button>
      <button v-if="step < steps.length - 1" class="btn btn-primary" :disabled="!answers[steps[step]]" @click="next">
        {{ $t('common.next') }}
      </button>
      <button v-else class="btn btn-primary" :disabled="!answers.delivery" @click="apply">
        {{ $t('catalog.wizard.apply') }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import BaseModal from '@/shared/ui/BaseModal.vue'

/**
 * RC-6J — assistant OPTIONNEL de création produit (arbitrage C) : 4 questions métier qui pré-remplissent
 * la carte « Type & politique » du formulaire — sans jamais le remplacer.
 */
const props = defineProps<{ modelValue: boolean }>()
const emit  = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'apply', v: { product_type: string; stock_tracking: string; fulfillment_type: string; wantsWarranty: boolean; hasVariants: boolean }): void
}>()

const steps = ['nature', 'stock', 'warranty', 'delivery'] as const
const step  = ref(0)

const answers = reactive<Record<string, string>>({ nature: '', stock: '', warranty: '', delivery: '' })

function optionsFor(s: string): string[] {
  switch (s) {
    case 'nature':   return ['physical', 'variants', 'service', 'digital', 'bundle']
    case 'stock':    return answers.nature === 'service' || answers.nature === 'digital'
      ? ['none']
      : ['aggregate', 'serialized', 'batch']
    case 'warranty': return ['yes', 'no']
    case 'delivery': return answers.nature === 'digital'
      ? ['download', 'license']
      : answers.nature === 'service' ? ['manual', 'appointment'] : ['delivery', 'manual']
    default: return []
  }
}

function next() {
  // Les non-stockables sautent la question stock (réponse forcée « none »).
  if (steps[step.value] === 'nature' && (answers.nature === 'service' || answers.nature === 'digital')) {
    answers.stock = 'none'
    step.value += 2
    return
  }
  step.value++
}

const result = computed(() => ({
  product_type: answers.nature === 'variants' ? 'simple'
    : answers.nature === 'physical' ? 'simple'
    : answers.nature === 'bundle' ? 'kit'
    : answers.nature,
  stock_tracking: answers.stock === 'none' || answers.stock === 'aggregate' ? '' : answers.stock, // '' = auto
  fulfillment_type: answers.delivery === 'delivery' ? '' : answers.delivery,
  wantsWarranty: answers.warranty === 'yes',
  hasVariants: answers.nature === 'variants',
}))

function apply() {
  emit('apply', result.value)
  emit('update:modelValue', false)
}
</script>

<style scoped>
.wiz-step-label { font-size: 0.72rem; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
.wiz-question { margin: 0 0 12px; font-size: 1rem; }
.wiz-options { display: flex; flex-direction: column; gap: 8px; }
.wiz-opt {
  display: flex; flex-direction: column; gap: 2px; padding: 10px 12px;
  border: 1px solid var(--gray-200); border-radius: 8px; cursor: pointer;
}
.wiz-opt.active { border-color: var(--brand-primary); background: var(--brand-primary-bg, #f0fdf4); }
.wiz-opt-title { font-weight: 600; font-size: 0.9rem; }
.wiz-opt-hint { font-size: 0.78rem; color: var(--gray-500); }
.wiz-summary { margin-top: 14px; padding: 10px 12px; background: var(--gray-50); border-radius: 8px; font-size: 0.85rem; }
</style>
