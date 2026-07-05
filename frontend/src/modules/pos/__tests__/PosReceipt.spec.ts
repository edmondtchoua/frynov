import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import PosReceipt from '@/modules/pos/components/PosReceipt.vue'
import type { PosReceipt as PosReceiptData } from '@/modules/pos/types'

const { printHtml } = vi.hoisted(() => ({ printHtml: vi.fn() }))
vi.mock('@/modules/pos/composables/receiptPrint', async (importOriginal) => {
  const orig = await importOriginal<any>()
  return { ...orig, printHtml }
})

const RECEIPT: PosReceiptData = {
  business: { name: 'Boutique Ticket', address: 'Marché Sandaga, Dakar', phone: '+221 77 000 00 00', currency: 'XOF' },
  order: { id: 'o1', number: 'CMD-000042', date: '2026-07-05T12:00:00Z', status: 'fulfilled' },
  session: { id: 's1', label: 'Caisse 1' },
  cashier: 'Awa Caissière',
  lines: [
    { name: 'Café Touba', sku: 'TIC-1', quantity: 2, unit_price_cents: 25000, total_cents: 50000 },
  ],
  payments: [
    { method: 'cash', amount_cents: 30000, reference: null },
    { method: 'mobile_money', amount_cents: 20000, reference: 'OM-777' },
  ],
  totals: { total_cents: 50000, paid_cents: 50000 },
}

function mountReceipt() {
  return mount(PosReceipt, { props: { receipt: RECEIPT }, global: { plugins: [createPinia()] } })
}

describe('PosReceipt', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders the business header, lines, split payments and total in major units', () => {
    const w = mountReceipt()
    const text = w.find('[data-test="receipt"]').text()

    expect(text).toContain('Boutique Ticket')
    expect(text).toContain('Marché Sandaga, Dakar')
    expect(text).toContain('CMD-000042')
    expect(text).toContain('Awa Caissière')
    expect(text).toContain('Café Touba')
    expect(text).toContain('OM-777')                     // référence du leg Mobile Money

    // 50 000 centimes → « 500 » (unités majeures), jamais les centimes bruts.
    const total = w.find('[data-test="receipt-total"]').text().replace(/\s| | /g, '')
    expect(total).toContain('500')
    expect(total).not.toContain('50000')
  })

  it('prints the rendered ticket through the hidden-iframe printer', async () => {
    const w = mountReceipt()

    ;(w.vm as any).print()

    expect(printHtml).toHaveBeenCalledTimes(1)
    const html = printHtml.mock.calls[0][0] as string
    expect(html).toContain('pos-receipt')
    expect(html).toContain('CMD-000042')
  })
})
