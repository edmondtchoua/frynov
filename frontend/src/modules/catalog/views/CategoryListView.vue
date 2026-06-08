<template>
  <div>
    <CatalogTabNav />
    <div class="page-header">
      <div>
        <h2>{{ $t('catalog.categoriesTitle') }}</h2>
        <p class="page-subtitle">{{ categories.length }} {{ categories.length !== 1 ? $t('catalog.categoryPlural') : $t('catalog.categorySingular') }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ $t('catalog.newCategory') }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 200px;">
      <span class="spinner-sm" style="width: 24px; height: 24px; border-width: 3px;"></span>
    </div>

    <!-- Empty -->
    <div v-else-if="categories.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
        <path d="M10 14h20M10 20h14M10 26h10" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h3>{{ $t('catalog.emptyCategories') }}</h3>
      <p>{{ $t('catalog.emptyCategoriesHint') }}</p>
      <button class="btn btn-primary" @click="openCreate">{{ $t('catalog.createCategory') }}</button>
    </div>

    <!-- Table -->
    <div v-else class="card" style="padding: 0; overflow: hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th class="hide-mobile">{{ $t('catalog.colParent') }}</th>
            <th class="hide-mobile">{{ $t('catalog.colOrder') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th style="text-align: right;">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in sortedCategories" :key="cat.id">
            <td>
              <div class="cat-name-cell">
                <span v-if="cat.parent_id" class="child-indent">└</span>
                <span class="cat-name">{{ cat.name }}</span>
              </div>
            </td>
            <td class="hide-mobile">
              <span v-if="cat.parent_id" class="badge badge-gray">
                {{ parentName(cat.parent_id) }}
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="hide-mobile">
              <span class="text-muted">{{ cat.sort_order }}</span>
            </td>
            <td>
              <span :class="cat.is_active ? 'badge badge-success' : 'badge badge-gray'">
                {{ cat.is_active ? $t('catalog.statusActive') : $t('catalog.statusInactive') }}
              </span>
            </td>
            <td style="text-align: right;">
              <div class="row-actions">
                <button class="btn btn-ghost btn-sm" @click="openEdit(cat)">{{ $t('common.edit') }}</button>
                <button class="btn btn-ghost btn-sm text-danger" @click="confirmDelete(cat)">
                  {{ $t('common.delete') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="showModal"
      :title="editingId ? $t('catalog.editCategory') : $t('catalog.newCategory')"
      @update:model-value="(v: boolean) => { if (!v) closeModal() }"
    >
      <div class="form-group">
        <label class="form-label" for="cat-name">{{ $t('common.name') }} <span style="color: var(--color-error);">*</span></label>
        <input
          id="cat-name"
          v-model="catForm.name"
          type="text"
          class="form-input"
          :class="{ error: catErrors.name }"
          :placeholder="$t('catalog.categoryNamePlaceholder')"
          @input="delete catErrors.name"
        />
        <span v-if="catErrors.name" class="form-error">{{ catErrors.name }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="cat-parent">{{ $t('catalog.parentCategory') }}</label>
        <select id="cat-parent" v-model="catForm.parent_id" class="form-input">
          <option value="">{{ $t('catalog.noParent') }}</option>
          <option
            v-for="cat in rootCategories"
            :key="cat.id"
            :value="cat.id"
            :disabled="cat.id === editingId"
          >{{ cat.name }}</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="cat-description">{{ $t('common.description') }}</label>
        <input
          id="cat-description"
          v-model="catForm.description"
          type="text"
          class="form-input"
          :placeholder="$t('catalog.descriptionPlaceholder')"
        />
      </div>

      <div class="form-row">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" for="cat-order">{{ $t('catalog.displayOrder') }}</label>
          <input
            id="cat-order"
            v-model.number="catForm.sort_order"
            type="number"
            min="0"
            class="form-input"
          />
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">{{ $t('common.status') }}</label>
          <label class="toggle-wrap">
            <input v-model="catForm.is_active" type="checkbox" class="toggle-input" />
            <span class="toggle-track">
              <span class="toggle-thumb"></span>
            </span>
            <span class="toggle-label">{{ catForm.is_active ? $t('catalog.statusActive') : $t('catalog.statusInactive') }}</span>
          </label>
        </div>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveCategory">
          <span v-if="saving" class="spinner-sm spinner-white"></span>
          {{ saving ? $t('common.saving') : (editingId ? $t('common.update') : $t('common.create')) }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import CatalogTabNav from '../components/CatalogTabNav.vue'
import { productService } from '../services/productService'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { t } from '@/i18n'
import type { Category } from '../types'

const categories = ref<Category[]>([])
const loading    = ref(false)
const saving     = ref(false)
const showModal  = ref(false)
const editingId  = ref<string | null>(null)

const catForm   = reactive({ name: '', parent_id: '', description: '', sort_order: 0, is_active: true })
const catErrors = reactive<Record<string, string>>({})

const rootCategories = computed(() => categories.value.filter(c => !c.parent_id))

const sortedCategories = computed(() => {
  const roots    = categories.value.filter(c => !c.parent_id).sort((a, b) => a.sort_order - b.sort_order)
  const children = categories.value.filter(c => !!c.parent_id).sort((a, b) => a.sort_order - b.sort_order)
  const result: Category[] = []
  roots.forEach(r => {
    result.push(r)
    children.filter(c => c.parent_id === r.id).forEach(c => result.push(c))
  })
  return result
})

function parentName(parentId: string): string {
  return categories.value.find(c => c.id === parentId)?.name ?? parentId
}

async function load() {
  loading.value = true
  try {
    categories.value = await productService.categories.list()
  } catch {
    categories.value = []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value    = null
  catForm.name        = ''
  catForm.parent_id   = ''
  catForm.description = ''
  catForm.sort_order  = 0
  catForm.is_active   = true
  Object.keys(catErrors).forEach(k => delete catErrors[k])
  showModal.value = true
}

function openEdit(cat: Category) {
  editingId.value     = cat.id
  catForm.name        = cat.name
  catForm.parent_id   = cat.parent_id ?? ''
  catForm.description = cat.description ?? ''
  catForm.sort_order  = cat.sort_order
  catForm.is_active   = cat.is_active
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingId.value = null
}

async function saveCategory() {
  if (!catForm.name.trim()) { catErrors.name = t('catalog.nameRequired'); return }
  saving.value = true
  try {
    const data = {
      name:        catForm.name,
      parent_id:   catForm.parent_id || undefined,
      description: catForm.description || undefined,
      sort_order:  catForm.sort_order,
      is_active:   catForm.is_active,
    }
    if (editingId.value) {
      await productService.categories.update(editingId.value, data)
    } else {
      await productService.categories.create(data)
    }
    closeModal()
    load()
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

const { confirm } = useConfirm()

async function confirmDelete(cat: Category) {
  if (!(await confirm({
    title: t('common.delete'),
    message: t('catalog.confirmDeleteCategory', { name: cat.name }),
    confirmLabel: t('common.delete'),
    danger: true,
  }))) return
  try {
    await productService.categories.delete(cat.id)
    load()
  } catch { /* ignore */ }
}

onMounted(load)
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }
.text-muted    { color: var(--gray-400); font-size: var(--text-sm); }
.text-danger   { color: var(--color-error); }
.row-actions   { display: flex; gap: 0.5rem; justify-content: flex-end; }

.cat-name-cell { display: flex; align-items: center; gap: 0.5rem; }
.child-indent  { color: var(--gray-300); font-size: 1rem; }
.cat-name      { font-weight: 500; color: var(--gray-900); font-size: var(--text-sm); }

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

/* Modal chrome now provided by the shared <BaseModal> (UX-03). */

/* ── Toggle ──────────────────────────────────────────────────────────────── */
.toggle-wrap {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  cursor: pointer;
  margin-top: 0.5rem;
}
.toggle-input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
.toggle-track {
  width: 36px;
  height: 20px;
  border-radius: 10px;
  background: var(--gray-300);
  position: relative;
  transition: background 0.2s;
  flex-shrink: 0;
}
.toggle-input:checked + .toggle-track { background: var(--brand-primary); }
.toggle-thumb {
  position: absolute;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: white;
  top: 2px;
  left: 2px;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-input:checked + .toggle-track .toggle-thumb { transform: translateX(16px); }
.toggle-label { font-size: var(--text-sm); color: var(--gray-700); }
</style>
