import { describe, it, expect, afterEach, vi } from 'vitest'
import { fetchPublicPricing, fetchPublicPaymentMethods } from '@/services/publicPricingService'

// The landing is a PUBLIC page: pricing is fetched via a raw fetch (no auth token,
// no axios 401-redirect interceptor) from the backend source of truth.
describe('publicPricingService.fetchPublicPricing', () => {
  afterEach(() => vi.restoreAllMocks())

  it('requests /api/public/pricing with the selected market and parses the response', async () => {
    const payload = {
      market: { code: 'canada', label: 'Canada', currency: 'CAD', source: 'market', country: null },
      selectable_markets: [],
      data: [{ code: 'essential', price: { currency: 'CAD', base_amount_minor: 2500 } }],
    }
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => payload })
    vi.stubGlobal('fetch', fetchMock)

    const res = await fetchPublicPricing({ market: 'canada' })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const url = String(fetchMock.mock.calls[0][0])
    expect(url).toContain('/api/public/pricing')
    expect(url).toContain('market=canada')
    expect(res.market.currency).toBe('CAD')
    expect(res.data[0].price?.base_amount_minor).toBe(2500)
  })

  it('passes the country param when given (server resolves the market)', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ market: {}, selectable_markets: [], data: [] }) })
    vi.stubGlobal('fetch', fetchMock)

    await fetchPublicPricing({ country: 'FR' })

    expect(String(fetchMock.mock.calls[0][0])).toContain('country=FR')
  })

  it('throws on a non-OK response so the caller can fall back', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 503 }))
    await expect(fetchPublicPricing({ market: 'waemu' })).rejects.toThrow()
  })
})

describe('publicPricingService.fetchPublicPaymentMethods (P6)', () => {
  afterEach(() => vi.restoreAllMocks())

  it('requests /api/public/payment-methods for the market and parses the methods', async () => {
    const payload = {
      market: { code: 'waemu', label: 'UEMOA', currency: 'XOF', source: 'market' },
      has_auto: false,
      data: [{ method: 'wave', mode: 'manual', currency: 'XOF', label: null }],
    }
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => payload })
    vi.stubGlobal('fetch', fetchMock)

    const res = await fetchPublicPaymentMethods({ market: 'waemu' })

    const url = String(fetchMock.mock.calls[0][0])
    expect(url).toContain('/api/public/payment-methods')
    expect(url).toContain('market=waemu')
    expect(res.has_auto).toBe(false)
    expect(res.data[0].method).toBe('wave')
    expect(res.data[0].mode).toBe('manual')
  })

  it('throws on a non-OK response so the caller can degrade', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }))
    await expect(fetchPublicPaymentMethods({ market: 'europe' })).rejects.toThrow()
  })
})
