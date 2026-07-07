<template>
  <div>
    <div ref="ticket" class="pos-receipt" data-test="receipt">
      <div class="r-center">
        <div class="r-name">{{ receipt.business.name }}</div>
        <div v-if="receipt.business.address" class="r-muted">{{ receipt.business.address }}</div>
        <div v-if="receipt.business.phone" class="r-muted">{{ receipt.business.phone }}</div>
      </div>

      <hr class="r-sep" />

      <div class="r-row"><span>{{ $t('posReceipt.number') }}</span><span>{{ receipt.order.number }}</span></div>
      <div class="r-row"><span>{{ $t('posReceipt.date') }}</span><span>{{ dateLabel }}</span></div>
      <div v-if="receipt.cashier" class="r-row"><span>{{ $t('posReceipt.cashier') }}</span><span>{{ receipt.cashier }}</span></div>
      <div v-if="receipt.session?.label" class="r-row"><span>{{ $t('posReceipt.register') }}</span><span>{{ receipt.session.label }}</span></div>

      <hr class="r-sep" />

      <div v-for="(line, i) in receipt.lines" :key="i">
        <div class="r-line-name">{{ line.name }}</div>
        <div class="r-row">
          <span class="r-qty">{{ line.quantity }} × {{ fmt(line.unit_price_cents) }}</span>
          <span class="r-amount">{{ fmt(line.total_cents) }}</span>
        </div>
      </div>

      <hr class="r-sep" />

      <div class="r-row r-total">
        <span>{{ $t('common.total') }}</span>
        <span data-test="receipt-total">{{ fmt(receipt.totals.total_cents) }}</span>
      </div>

      <div v-for="(p, i) in receipt.payments" :key="'p' + i" class="r-row">
        <span>{{ $t(`payments.method.${p.method}`) }}<template v-if="p.reference"> · {{ p.reference }}</template></span>
        <span class="r-amount">{{ fmt(p.amount_cents) }}</span>
      </div>

      <hr class="r-sep" />

      <div class="r-center r-footer">{{ $t('posReceipt.thanks') }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { formatMoney } from '@/shared/utils/money'
import { formatDate } from '@/shared/utils/date'
import { RECEIPT_CSS, printHtml } from '../composables/receiptPrint'
import type { PosReceipt } from '../types'

const props = defineProps<{ receipt: PosReceipt }>()

const ticket = ref<HTMLElement | null>(null)

const fmt = (cents: number) => formatMoney(cents ?? 0, props.receipt.business.currency)
const dateLabel = computed(() => (props.receipt.order.date ? formatDate(props.receipt.order.date) : '—'))

/** Imprime le ticket tel qu'affiché (iframe cachée, CSS partagé avec l'aperçu). */
function print() {
  if (ticket.value) printHtml(ticket.value.outerHTML)
}

defineExpose({ print })

// Le style ticket vit dans RECEIPT_CSS (source unique aperçu + impression) : injecté une fois.
onMounted(() => {
  if (!document.getElementById('pos-receipt-css')) {
    const style = document.createElement('style')
    style.id = 'pos-receipt-css'
    style.textContent = RECEIPT_CSS
    document.head.appendChild(style)
  }
})
</script>
