import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import NotificationSettingsPanel from '@/modules/settings/components/NotificationSettingsPanel.vue'

vi.mock('@/api/client', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))
import client from '@/api/client'
import { vFocusTrap } from '@/directives/focusTrap'

// RC-7E — onglet « Crédits » du panneau Notifications : soldes par canal + recharge par pack.
const CREDITS = {
  enabled: true,
  balances: { email: 0, sms: 42, whatsapp: 0 },
  metered: ['email', 'sms', 'whatsapp'],
  packs: [
    { code: 'sms_1k', channel: 'sms', credits: 1000, price_cents: 1500000, currency: 'XOF' },
    { code: 'wa_1k', channel: 'whatsapp', credits: 1000, price_cents: 2000000, currency: 'XOF' },
  ],
}

// RC-7F — commandes de recharge Mobile Money.
const ORDERS: any[] = []

function mockGet(orders: any[] = ORDERS) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.endsWith('/credits'))            return Promise.resolve({ data: { data: CREDITS } }) as any
    if (url.endsWith('/credits/movements'))  return Promise.resolve({ data: { data: [] } }) as any
    if (url.endsWith('/credits/orders'))     return Promise.resolve({ data: { data: orders } }) as any
    return Promise.resolve({ data: { data: [] } }) as any // channels, etc.
  })
}

async function mountPanel(orders: any[] = ORDERS) {
  mockGet(orders)
  const w = mount(NotificationSettingsPanel, {
    global: { directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
  await flushPromises()
  return w
}

function buttonByText(w: any, text: string) {
  return w.findAll('button').find((b: any) => b.text().includes(text))
}

describe('NotificationSettingsPanel — credits (RC-7E)', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('shows per-channel balances after switching to the credits tab', async () => {
    const w = await mountPanel()
    await w.findAll('.ntf-tab')[3].trigger('click') // channels, templates, outbox, credits
    await flushPromises()

    expect(vi.mocked(client.get)).toHaveBeenCalledWith('/api/notifications/credits')
    expect(w.text()).toContain('42')          // SMS balance
    expect(w.text()).toContain('Facturé')     // metered badge
  })

  it('recharges a pack via the manual rail', async () => {
    vi.mocked(client.post).mockResolvedValue({ data: { data: { channel: 'sms', balance: 1042 } } } as any)
    const w = await mountPanel()
    await w.findAll('.ntf-tab')[3].trigger('click')
    await flushPromises()

    // Ouvre la recharge du canal SMS (2ᵉ carte : email, sms, whatsapp).
    await w.findAll('.ntf-credit-card')[1].find('.btn-primary').trigger('click')
    await flushPromises()

    // RC-7F — le mode par défaut est Mobile Money : bascule sur l'encaissement manuel.
    await w.find('.modal select').setValue('manual')
    await flushPromises()

    await buttonByText(w, 'Valider la recharge').trigger('click')
    await flushPromises()

    expect(vi.mocked(client.post)).toHaveBeenCalledWith(
      '/api/notifications/credits/recharge',
      expect.objectContaining({ pack_code: 'sms_1k' }),
    )
  })

  // RC-7F — mode Mobile Money : génère une commande et affiche la référence payable.
  it('creates a mobile money order and shows the payable reference', async () => {
    vi.mocked(client.post).mockResolvedValue({ data: { data: {
      id: 'o1', reference: 'RCH-ABCD1234', pack_code: 'sms_1k', channel: 'sms',
      credits: 1000, price_cents: 1500000, currency: 'XOF', status: 'pending', created_at: '2026-06-29T10:00:00Z',
    } } } as any)
    const w = await mountPanel()
    await w.findAll('.ntf-tab')[3].trigger('click')
    await flushPromises()

    await w.findAll('.ntf-credit-card')[1].find('.btn-primary').trigger('click')
    await flushPromises()

    await buttonByText(w, 'Générer la référence de paiement').trigger('click')
    await flushPromises()

    expect(vi.mocked(client.post)).toHaveBeenCalledWith(
      '/api/notifications/credits/orders',
      { pack_code: 'sms_1k' },
    )
    expect(w.text()).toContain('RCH-ABCD1234')
    // 1 500 000 centimes → 15 000 XOF (séparateur de milliers dépendant de la locale du runner).
    expect(w.text().replace(/[\s,  ]/g, '')).toContain('15000XOF')
  })

  it('lists pending mobile money orders with their status', async () => {
    const w = await mountPanel([{
      id: 'o1', reference: 'RCH-WAIT0001', pack_code: 'sms_1k', channel: 'sms',
      credits: 1000, price_cents: 1500000, currency: 'XOF', status: 'pending', created_at: '2026-06-29T10:00:00Z',
    }])
    await w.findAll('.ntf-tab')[3].trigger('click')
    await flushPromises()

    expect(w.text()).toContain('RCH-WAIT0001')
    expect(w.text()).toContain('En attente de paiement')
  })
})
