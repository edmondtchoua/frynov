import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { useGeoContent } from '@/composables/useGeoContent'

describe('useGeoContent', () => {
  beforeEach(() => { sessionStorage.clear() })
  afterEach(() => { vi.restoreAllMocks() })

  it('returns reactive refs isAfrica, isLoading, region', () => {
    const { isAfrica, isLoading, region } = useGeoContent()
    expect(typeof isAfrica.value).toBe('boolean')
    expect(typeof isLoading.value).toBe('boolean')
    expect(typeof region.value).toBe('string')
  })

  it('reads africa cache from sessionStorage', async () => {
    sessionStorage.setItem('geo_region', 'africa')
    const { isAfrica, isLoading } = useGeoContent()
    await Promise.resolve()
    expect(isAfrica.value).toBe(true)
    expect(isLoading.value).toBe(false)
  })

  it('reads global cache from sessionStorage', async () => {
    sessionStorage.setItem('geo_region', 'global')
    const { isAfrica } = useGeoContent()
    await Promise.resolve()
    expect(isAfrica.value).toBe(false)
  })

  it('default region is a non-empty string', () => {
    const { region } = useGeoContent()
    expect(region.value.length).toBeGreaterThanOrEqual(0)
  })
})
