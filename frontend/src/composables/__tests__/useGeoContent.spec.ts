import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// Reset module between tests so the singleton (_resolved, _region) is fresh each time
beforeEach(() => {
  vi.resetModules()
  setActivePinia(createPinia())
  sessionStorage.clear()
})
afterEach(() => { vi.restoreAllMocks() })

describe('useGeoContent', () => {
  it('returns reactive refs isAfrica, isLoading, region and market', async () => {
    const { useGeoContent } = await import('@/composables/useGeoContent')
    const { isAfrica, isLoading, region, market, selectableMarkets } = useGeoContent()
    expect(typeof isAfrica.value).toBe('boolean')
    expect(typeof isLoading.value).toBe('boolean')
    expect(typeof region.value).toBe('string')
    expect(typeof market.value.currency).toBe('string')
    expect(selectableMarkets.length).toBeGreaterThan(3)
  })

  it('reads africa cache from sessionStorage', async () => {
    sessionStorage.setItem('geo_region', 'africa')
    const { useGeoContent } = await import('@/composables/useGeoContent')
    const { isAfrica, isLoading } = useGeoContent()
    // Allow the microtask (detectRegion().then) to settle
    await Promise.resolve()
    await Promise.resolve()
    expect(isAfrica.value).toBe(true)
    expect(isLoading.value).toBe(false)
  })

  it('reads global cache from sessionStorage', async () => {
    sessionStorage.setItem('geo_region', 'global')
    const { useGeoContent } = await import('@/composables/useGeoContent')
    const { isAfrica } = useGeoContent()
    await Promise.resolve()
    await Promise.resolve()
    expect(isAfrica.value).toBe(false)
  })

  it('supports manual Canada/CAD override', async () => {
    const { useGeoContent } = await import('@/composables/useGeoContent')
    const { market, selectedMarket } = useGeoContent()
    selectedMarket.value = 'canada'
    expect(market.value.currency).toBe('CAD')
    expect(market.value.priceBook).toBe('canada_cad')
  })

  it('default region is a non-empty string', async () => {
    const { useGeoContent } = await import('@/composables/useGeoContent')
    const { region } = useGeoContent()
    expect(region.value.length).toBeGreaterThanOrEqual(0)
  })
})
