import { ref, computed } from 'vue'
import { toCents } from '@/shared/utils/money'
import { posService } from '../services/posService'
import type {
  CashMovement,
  CashRegisterSession,
  PosCartItem,
  PosPaymentLeg,
  PosPaymentMethod,
  PosRefundLine,
} from '../types'

/**
 * Shared point-of-sale session logic for the Desktop and mobile tills.
 *
 * Owns the open session, the cart, and the RC-16 drawer primitives (split payment
 * checkout, cash movements, refunds, close/reconciliation) so both views stay thin
 * and behave identically. All amounts are integer centimes.
 */
export function usePosSession() {
  const loading = ref(true)
  const session = ref<CashRegisterSession | null>(null)
  const cart = ref<PosCartItem[]>([])
  const busy = ref(false)
  const error = ref('')

  const cartTotalCents = computed(() =>
    cart.value.reduce((s, l) => s + l.unit_price_cents * l.quantity, 0),
  )
  const cartCount = computed(() => cart.value.reduce((s, l) => s + l.quantity, 0))
  const isOpen = computed(() => session.value?.status === 'open')

  // ── Session lifecycle ──────────────────────────────────────────────────────

  async function load() {
    loading.value = true
    try {
      session.value = await posService.current()
    } catch {
      session.value = null
    } finally {
      loading.value = false
    }
  }

  async function open(floatMajor: number, label?: string) {
    error.value = ''
    busy.value = true
    try {
      session.value = await posService.open({ opening_float_cents: toCents(floatMajor), label })
      return true
    } catch (e: any) {
      error.value = e?.response?.data?.message ?? 'error'
      return false
    } finally {
      busy.value = false
    }
  }

  async function close(countedMajor: number, notes?: string) {
    if (!session.value) return null
    busy.value = true
    try {
      const closed = await posService.close(session.value.id, {
        counted_cash_cents: toCents(countedMajor),
        notes,
      })
      session.value = null
      cart.value = []
      return closed
    } finally {
      busy.value = false
    }
  }

  // ── Cart ───────────────────────────────────────────────────────────────────

  function addLine(productId: string, variantId: string | null, name: string, sku: string, priceCents: number) {
    const existing = cart.value.find(l => l.product_id === productId && (l.variant_id ?? null) === variantId)
    if (existing) {
      existing.quantity += 1
      return
    }
    cart.value.push({ product_id: productId, variant_id: variantId, name, sku, unit_price_cents: priceCents, quantity: 1 })
  }

  function incQty(i: number) {
    cart.value[i].quantity += 1
  }

  function decQty(i: number) {
    cart.value[i].quantity -= 1
    if (cart.value[i].quantity <= 0) cart.value.splice(i, 1)
  }

  function clearCart() {
    cart.value = []
  }

  // ── Checkout (single or split payment) ─────────────────────────────────────

  /**
   * Ring up the current cart. Pass a single method for a one-tender sale, or a
   * list of legs for a split payment (their amounts must sum to the total).
   */
  async function checkout(payment: PosPaymentMethod | PosPaymentLeg[], reference?: string, customerId?: string | null) {
    if (!session.value || cart.value.length === 0) return null
    error.value = ''
    busy.value = true
    const items = cart.value.map(l => ({
      product_id: l.product_id,
      variant_id: l.variant_id ?? undefined,
      quantity: l.quantity,
    }))
    try {
      const res = await posService.checkout(
        session.value.id,
        Array.isArray(payment)
          ? { items, payments: payment, customer_id: customerId ?? undefined }
          : { items, method: payment, reference, customer_id: customerId ?? undefined },
      )
      session.value = res.session
      cart.value = []
      return res
    } catch (e: any) {
      error.value =
        e?.response?.data?.message ??
        (e?.response?.data?.errors?.payments?.[0] as string) ??
        'error'
      throw e
    } finally {
      busy.value = false
    }
  }

  // ── Drawer movements & refunds ─────────────────────────────────────────────

  async function cashMovement(direction: 'in' | 'out', amountMajor: number, reason: string, note?: string): Promise<CashMovement> {
    if (!session.value) throw new Error('no-session')
    busy.value = true
    try {
      const res = await posService.cashMovement(session.value.id, {
        direction,
        amount_cents: toCents(amountMajor),
        reason,
        note,
      })
      session.value = res.session
      return res.movement
    } finally {
      busy.value = false
    }
  }

  async function refund(orderId: string, lines: PosRefundLine[], reason: string, method: PosPaymentMethod = 'cash') {
    if (!session.value) throw new Error('no-session')
    busy.value = true
    try {
      const res = await posService.refund(session.value.id, { order_id: orderId, lines, reason, refund_method: method })
      session.value = res.session
      return res
    } finally {
      busy.value = false
    }
  }

  return {
    // state
    loading, session, cart, busy, error,
    // derived
    cartTotalCents, cartCount, isOpen,
    // actions
    load, open, close,
    addLine, incQty, decQty, clearCart,
    checkout, cashMovement, refund,
  }
}
