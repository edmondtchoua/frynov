/**
 * useGeoContent — Lightweight IP-based geographic personalization.
 *
 * Detects the visitor's approximate region (no browser permission required).
 * Returns a reactive `region` ref:
 *   'africa'  — visitor is in a supported African country
 *   'global'  — visitor is elsewhere or detection failed / timed out
 *
 * Uses ipapi.co (free tier: 1 000 req/day) with a 3-second timeout fallback.
 * Result is cached in sessionStorage so it is only fetched once per tab.
 *
 * The landing page reads `region` to swap between generalised and
 * Africa-tailored content without hard-coding any region in HTML.
 */

import { ref, readonly } from 'vue'

export type GeoRegion = 'africa' | 'global'

const CACHE_KEY = 'frynov_geo_region'
const TIMEOUT_MS = 3_000

/**
 * ISO-3166-1 alpha-2 codes for countries where Frynov targets African SMEs.
 * Extend as Frynov expands to new markets.
 */
const AFRICAN_COUNTRY_CODES = new Set([
  'CI', 'SN', 'CM', 'GH', 'ML', 'BF', 'GN', 'TG', 'BJ', 'NE',    // ECOWAS
  'NG', 'MA', 'DZ', 'TN', 'LY', 'EG',                               // North/West Africa
  'CD', 'CG', 'GA', 'CF',                                            // Central Africa
  'KE', 'TZ', 'UG', 'RW', 'ET', 'MZ', 'MG', 'ZA',                  // East/South Africa
])

async function detectRegion(): Promise<GeoRegion> {
  // 1. Check session cache
  const cached = sessionStorage.getItem(CACHE_KEY) as GeoRegion | null
  if (cached === 'africa' || cached === 'global') return cached

  try {
    // 2. Fetch with timeout — ipapi.co is fast and CORS-enabled
    const controller = new AbortController()
    const tid = setTimeout(() => controller.abort(), TIMEOUT_MS)

    const res = await fetch('https://ipapi.co/json/', {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
    clearTimeout(tid)

    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    const countryCode: string = (data?.country_code ?? '').toUpperCase()

    const region: GeoRegion = AFRICAN_COUNTRY_CODES.has(countryCode) ? 'africa' : 'global'
    sessionStorage.setItem(CACHE_KEY, region)
    return region
  } catch {
    // Network error, timeout, or blocked by ad-blocker → safe default
    return 'global'
  }
}

// Module-level singleton — shared across all callers
const _region = ref<GeoRegion>('global')
let _resolved = false

export function useGeoContent() {
  if (!_resolved) {
    _resolved = true
    detectRegion().then(r => { _region.value = r })
  }

  return {
    /** 'africa' | 'global'  — updates reactively once detection completes */
    region:   readonly(_region),
    isAfrica: readonly(_region),   // alias for clarity in templates: isAfrica.value === 'africa'
  }
}
