<template>
  <div style="max-width: 760px;">
    <div class="page-header">
      <h2>Nouvelle commande</h2>
      <RouterLink to="/orders" class="btn btn-secondary">← Retour</RouterLink>
    </div>

    <form @submit.prevent="submit">
      <!-- Line items -->
      <div class="card" style="margin-bottom: 1rem;">
        <h3 style="font-size: 0.95rem; margin-bottom: 1rem;">Articles</h3>

        <div v-for="(item, i) in items" :key="i" class="line-row">
          <div class="form-group" style="flex: 1; margin: 0;">
            <input
              v-model="item.product_id"
              class="form-input"
              placeholder="ID produit"
              required
            />
          </div>
          <div class="form-group" style="width: 90px; margin: 0;">
            <input
              v-model.number="item.quantity"
              type="number"
              min="1"
              class="form-input"
              placeholder="Qté"
              required
            />
          </div>
          <div class="form-group" style="width: 130px; margin: 0;">
            <input
              v-model.number="item.unit_price_cents"
              type="number"
              min="0"
              class="form-input"
              placeholder="Prix (FCFA)"
            />
          </div>
          <button
            type="button"
            class="btn btn-danger"
            style="padding: 0.55rem 0.7rem;"
            :disabled="items.length === 1"
            @click="removeItem(i)"
          >✕</button>
        </div>

        <button type="button" class="btn btn-secondary" style="margin-top: 0.75rem;" @click="addItem">
          + Ajouter un article
        </button>
      </div>

      <!-- Note -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div class="form-group" style="margin: 0;">
          <label class="form-label">Note (optionnel)</label>
          <textarea v-model="note" class="form-input" rows="3" placeholder="Commentaire sur la commande…"></textarea>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="form-error" style="margin-bottom: 1rem;">{{ error }}</div>

      <!-- Actions -->
      <div style="display: flex; gap: 0.75rem;">
        <button type="submit" class="btn btn-primary" :disabled="loading">
          <span v-if="loading" class="spinner-sm"></span>
          Créer la commande
        </button>
        <RouterLink to="/orders" class="btn btn-secondary">Annuler</RouterLink>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { orderService } from '../services/orderService'

const router = useRouter()

interface LineItem { product_id: string; quantity: number; unit_price_cents: number | null }

const items   = ref<LineItem[]>([{ product_id: '', quantity: 1, unit_price_cents: null }])
const note    = ref('')
const loading = ref(false)
const error   = ref<string | null>(null)

function addItem() {
  items.value.push({ product_id: '', quantity: 1, unit_price_cents: null })
}

function removeItem(i: number) {
  items.value.splice(i, 1)
}

async function submit() {
  loading.value = true
  error.value   = null
  try {
    const payload = {
      items: items.value.map(i => ({
        product_id:       i.product_id,
        quantity:         i.quantity,
        unit_price_cents: i.unit_price_cents ?? undefined,
      })),
      note: note.value || undefined,
    }
    const order = await orderService.create(payload)
    router.push(`/orders/${order.id}`)
  } catch (e: any) {
    const msg = e?.response?.data?.message
    error.value = msg ?? 'Erreur lors de la création de la commande.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.line-row {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
  margin-bottom: 0.5rem;
}
</style>
