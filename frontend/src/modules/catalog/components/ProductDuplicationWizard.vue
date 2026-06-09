<template>
  <BaseModal
    :model-value="modelValue"
    :title="entity === 'category' ? $t('catalog.duplicate.titleCategory') : $t('catalog.duplicate.titleProduct')"
    @update:model-value="(v: boolean) => { if (!v) emit('close') }"
  >
    <div v-if="loading" class="dup-loading">
      <span class="spinner-sm"></span>
    </div>

    <div v-else-if="preview" class="dup-body">
      <p class="dup-intro">{{ $t('catalog.duplicate.intro') }}</p>

      <div class="dup-name">
        <span class="dup-name-label">{{ $t('common.name') }}</span>
        <strong class="dup-name-value">{{ preview.result.name }}</strong>
      </div>

      <div v-if="entity === 'product'" class="dup-counts">
        <span class="dup-chip">{{ $t('catalog.duplicate.variantsCount', { count: preview.result.variants_count ?? 0 }) }}</span>
        <span class="dup-chip">{{ $t('catalog.duplicate.attributesCount', { count: preview.result.attributes_count ?? 0 }) }}</span>
      </div>

      <div v-for="grp in groups" :key="grp.key" class="dup-group">
        <span class="dup-group-label" :class="`dup-group-label--${grp.key}`">{{ grp.label }}</span>
        <span
          v-for="it in grp.items"
          :key="it"
          class="dup-tag"
          :class="`dup-tag--${grp.key}`"
        >{{ $t('catalog.duplicate.field.' + it) }}</span>
      </div>

      <p v-if="error" class="dup-error">{{ error }}</p>
    </div>

    <div v-else-if="error" class="dup-error">{{ error }}</div>

    <template #footer>
      <button class="btn btn-ghost" @click="emit('close')">{{ $t('common.cancel') }}</button>
      <button class="btn btn-primary" :disabled="submitting || loading || !preview" @click="confirm">
        <span v-if="submitting" class="spinner-sm spinner-white"></span>
        {{ submitting ? $t('catalog.duplicate.creating') : $t('catalog.duplicate.confirm') }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { productService, type DuplicationPreview } from '../services/productService'
import { t } from '@/i18n'

const props = defineProps<{
  modelValue: boolean
  entity: 'product' | 'category'
  sourceId: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'duplicated', payload: unknown): void
}>()

const loading    = ref(false)
const submitting = ref(false)
const preview    = ref<DuplicationPreview | null>(null)
const error      = ref('')

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    preview.value = null
    error.value   = ''
    loading.value = true
    try {
      preview.value = props.entity === 'category'
        ? await productService.categories.duplicatePreview(props.sourceId)
        : await productService.duplicatePreview(props.sourceId)
    } catch {
      error.value = t('catalog.duplicate.error')
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

const groups = computed(() => {
  const p = preview.value
  if (!p) return []
  return [
    { key: 'regen',    label: t('catalog.duplicate.regenerated'), items: p.regenerated ?? [] },
    { key: 'cleared',  label: t('catalog.duplicate.cleared'),     items: p.cleared ?? [] },
    { key: 'excluded', label: t('catalog.duplicate.excluded'),    items: p.excluded ?? [] },
  ].filter(g => g.items.length > 0)
})

async function confirm() {
  if (!preview.value) return
  submitting.value = true
  error.value      = ''
  try {
    const result = props.entity === 'category'
      ? await productService.categories.duplicate(props.sourceId)
      : await productService.duplicate(props.sourceId)
    emit('duplicated', result)
  } catch {
    error.value = t('catalog.duplicate.error')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.dup-loading { display: flex; justify-content: center; padding: 2rem 0; }
.dup-body { display: flex; flex-direction: column; gap: 1rem; }
.dup-intro { font-size: var(--text-sm); color: var(--gray-600); line-height: 1.55; margin: 0; }

.dup-name {
  display: flex; flex-direction: column; gap: 2px;
  padding: 0.75rem 1rem; background: var(--gray-50);
  border: 1px solid var(--gray-200); border-radius: var(--radius-md);
}
.dup-name-label { font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--gray-400); font-weight: 600; }
.dup-name-value { font-size: var(--text-base); color: var(--gray-900); }

.dup-counts { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.dup-chip {
  font-size: var(--text-xs); font-weight: 600;
  background: var(--brand-primary-bg); color: var(--brand-primary-dark);
  padding: 0.2rem 0.6rem; border-radius: 999px;
}

.dup-group { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.dup-group-label {
  font-size: var(--text-xs); font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.04em; padding: 0.15rem 0.5rem; border-radius: 4px;
}
.dup-group-label--regen    { background: #dbeafe; color: #1e40af; }
.dup-group-label--cleared  { background: #fef9c3; color: #854d0e; }
.dup-group-label--excluded { background: #f1f5f9; color: #475569; }

.dup-tag {
  font-size: var(--text-xs); padding: 0.15rem 0.5rem; border-radius: 4px;
  border: 1px solid var(--gray-200); color: var(--gray-600); background: white;
}
.dup-tag--excluded { text-decoration: line-through; color: var(--gray-400); }

.dup-error { font-size: var(--text-sm); color: var(--color-error); margin: 0; }
</style>
