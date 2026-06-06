/**
 * useGeoContent — Lightweight IP-based geographic personalization.
 *
 * Detects the visitor's approximate country (no browser permission required) and
 * resolves it to a commercial market used by the landing page for local copy,
 * pricing currency and payment-method messaging.
 *
 * The legacy `region` ref is intentionally kept for existing callers/tests:
 *   - 'africa' is still accepted from the old session cache.
 *   - new market codes include 'waemu', 'cemac', 'europe', 'canada', 'usa', etc.
 */

import { ref, readonly, computed } from 'vue'

export type GeoRegion =
  | 'africa'
  | 'waemu'
  | 'cemac'
  | 'nigeria'
  | 'ghana'
  | 'kenya'
  | 'south_africa'
  | 'europe'
  | 'canada'
  | 'usa'
  | 'global'

export interface GeoMarket {
  code: GeoRegion
  label: string
  countryCode: string | null
  currency: 'XOF' | 'XAF' | 'NGN' | 'GHS' | 'KES' | 'ZAR' | 'EUR' | 'CAD' | 'USD'
  locale: string
  priceBook: string
  headline: string
  subheadline: string
  paymentCopy: string
  pricingNote: string
}

// Short key used by tests + new; legacy key still read for backward compat
const CACHE_KEY = 'geo_region'
const CACHE_KEY_LEGACY = 'frynov_geo_region'
const MARKET_CACHE_KEY = 'geo_market'
const TIMEOUT_MS = 3_000
const API_BASE = import.meta.env.VITE_API_BASE_URL ?? ''

const MARKET_BY_CODE: Record<GeoRegion, GeoMarket> = {
  africa: {
    code: 'africa', label: 'Afrique francophone', countryCode: null, currency: 'XOF', locale: 'fr-SN', priceBook: 'waemu_xof',
    headline: 'L’ERP simple pour gérer votre commerce, vos stocks et vos ventes en Afrique.',
    subheadline: 'Catalogue, caisse, inventaire, commandes, paiements, livraisons et rapports — avec des tarifs en FCFA et une expérience pensée pour les équipes terrain.',
    paymentCopy: 'Paiement Mobile Money, virement ou carte bancaire selon votre pays.',
    pricingNote: 'Paiement par Mobile Money, virement bancaire ou carte selon disponibilité locale.',
  },
  waemu: {
    code: 'waemu', label: 'UEMOA · XOF', countryCode: null, currency: 'XOF', locale: 'fr-SN', priceBook: 'waemu_xof',
    headline: 'L’ERP pensé pour les commerces en Afrique de l’Ouest.',
    subheadline: 'Stock, caisse, commandes, clients et rapports — en XOF, en français, et adapté aux équipes terrain.',
    paymentCopy: 'Wave, Orange Money, MTN MoMo, virement ou carte selon le pays.',
    pricingNote: 'Tarifs en XOF. Paiement Mobile Money, virement bancaire ou carte selon disponibilité locale.',
  },
  cemac: {
    code: 'cemac', label: 'CEMAC · XAF', countryCode: null, currency: 'XAF', locale: 'fr-CM', priceBook: 'cemac_xaf',
    headline: 'Une gestion simple pour les commerces en Afrique centrale.',
    subheadline: 'Catalogue, inventaire, ventes, paiements et multi-boutiques — avec des tarifs en XAF.',
    paymentCopy: 'MTN MoMo, Orange Money, virement ou carte selon le pays.',
    pricingNote: 'Tarifs en XAF. Paiement Mobile Money, virement bancaire ou carte selon disponibilité locale.',
  },
  nigeria: {
    code: 'nigeria', label: 'Nigeria · NGN', countryCode: 'NG', currency: 'NGN', locale: 'en-NG', priceBook: 'nigeria_ngn',
    headline: 'Retail operations, stock and sales control for Nigerian teams.',
    subheadline: 'Manage products, POS, orders, inventory and reports with local pricing in NGN.',
    paymentCopy: 'Cards, bank transfer and local payment rails depending on availability.',
    pricingNote: 'Pricing in NGN. Local payment options depend on payment-provider availability.',
  },
  ghana: {
    code: 'ghana', label: 'Ghana · GHS', countryCode: 'GH', currency: 'GHS', locale: 'en-GH', priceBook: 'ghana_ghs',
    headline: 'Simple ERP for Ghanaian retail and distribution teams.',
    subheadline: 'Control stock, sales, customers, payments and reporting with GHS pricing.',
    paymentCopy: 'Mobile Money, card or bank transfer depending on availability.',
    pricingNote: 'Pricing in GHS. Mobile Money and card availability may vary by provider.',
  },
  kenya: {
    code: 'kenya', label: 'Kenya · KES', countryCode: 'KE', currency: 'KES', locale: 'en-KE', priceBook: 'kenya_kes',
    headline: 'Inventory, POS and orders for growing Kenyan businesses.',
    subheadline: 'Run stock, sales, teams and reporting with KES pricing.',
    paymentCopy: 'M-Pesa, card or bank transfer depending on provider availability.',
    pricingNote: 'Pricing in KES. Local payment methods depend on provider availability.',
  },
  south_africa: {
    code: 'south_africa', label: 'South Africa · ZAR', countryCode: 'ZA', currency: 'ZAR', locale: 'en-ZA', priceBook: 'south_africa_zar',
    headline: 'Modern ERP for South African retail operations.',
    subheadline: 'Products, orders, stock, payments and reporting in one platform with ZAR pricing.',
    paymentCopy: 'Cards, EFT and local options depending on provider availability.',
    pricingNote: 'Pricing in ZAR. Card and bank payment availability depends on provider setup.',
  },
  europe: {
    code: 'europe', label: 'Europe · EUR', countryCode: null, currency: 'EUR', locale: 'fr-FR', priceBook: 'europe_eur',
    headline: 'Un ERP commerce moderne pour centraliser vos ventes, stocks et opérations.',
    subheadline: 'Une plateforme SaaS claire pour gérer catalogue, commandes, inventaire, paiements et reporting, avec facturation en euros.',
    paymentCopy: 'Carte bancaire, virement et facture selon votre configuration.',
    pricingNote: 'Tarifs en EUR. Paiement par carte, virement ou facture selon votre pays.',
  },
  canada: {
    code: 'canada', label: 'Canada · CAD', countryCode: 'CA', currency: 'CAD', locale: 'fr-CA', priceBook: 'canada_cad',
    headline: 'Gérez vos ventes, stocks et équipes avec un ERP simple et flexible.',
    subheadline: 'Prix en dollars canadiens, gestion multi-utilisateurs, rapports et opérations multi-sites.',
    paymentCopy: 'Cartes, virements et facturation en CAD.',
    pricingNote: 'Tarifs en CAD. Paiement par carte ou facture selon votre organisation.',
  },
  usa: {
    code: 'usa', label: 'USA · USD', countryCode: 'US', currency: 'USD', locale: 'en-US', priceBook: 'usa_usd',
    headline: 'Commerce operations, inventory and reporting in one SaaS platform.',
    subheadline: 'Built for growing retail and distribution teams that need clarity across products, orders, payments and stock.',
    paymentCopy: 'USD pricing with card and invoice payment options.',
    pricingNote: 'Pricing in USD. Card and invoice payment options are available by plan.',
  },
  global: {
    code: 'global', label: 'International · USD', countryCode: null, currency: 'USD', locale: 'en', priceBook: 'global_usd',
    headline: 'Commerce operations, inventory and reporting in one SaaS platform.',
    subheadline: 'Frynov centralises products, orders, customers, payments and stock in your language and your market currency where available.',
    paymentCopy: 'Card, bank transfer and local methods depending on country availability.',
    pricingNote: 'International fallback pricing in USD. You can change country/currency manually.',
  },
}

export const selectableMarkets: GeoMarket[] = [
  MARKET_BY_CODE.waemu,
  MARKET_BY_CODE.cemac,
  MARKET_BY_CODE.nigeria,
  MARKET_BY_CODE.ghana,
  MARKET_BY_CODE.kenya,
  MARKET_BY_CODE.south_africa,
  MARKET_BY_CODE.europe,
  MARKET_BY_CODE.canada,
  MARKET_BY_CODE.usa,
  MARKET_BY_CODE.global,
]

const WAEMU_COUNTRIES = new Set(['SN', 'CI', 'ML', 'BF', 'BJ', 'TG', 'NE', 'GW'])
const CEMAC_COUNTRIES = new Set(['CM', 'GA', 'CG', 'TD', 'CF', 'GQ'])
const EUROPE_EUR_COUNTRIES = new Set([
  'FR', 'BE', 'DE', 'ES', 'IT', 'NL', 'PT', 'IE', 'AT', 'FI', 'GR', 'LU', 'MT', 'CY', 'EE', 'LV', 'LT', 'SI', 'SK',
])

function resolveMarket(countryCode: string | null): GeoMarket {
  const code = (countryCode ?? '').toUpperCase()
  if (WAEMU_COUNTRIES.has(code)) return { ...MARKET_BY_CODE.waemu, countryCode: code }
  if (CEMAC_COUNTRIES.has(code)) return { ...MARKET_BY_CODE.cemac, countryCode: code }
  if (code === 'NG') return MARKET_BY_CODE.nigeria
  if (code === 'GH') return MARKET_BY_CODE.ghana
  if (code === 'KE') return MARKET_BY_CODE.kenya
  if (code === 'ZA') return MARKET_BY_CODE.south_africa
  if (code === 'CA') return MARKET_BY_CODE.canada
  if (code === 'US') return MARKET_BY_CODE.usa
  if (EUROPE_EUR_COUNTRIES.has(code)) return { ...MARKET_BY_CODE.europe, countryCode: code }
  return MARKET_BY_CODE.global
}

function readCachedMarket(): GeoMarket | null {
  const marketCode = sessionStorage.getItem(MARKET_CACHE_KEY) as GeoRegion | null
  if (marketCode && MARKET_BY_CODE[marketCode]) return MARKET_BY_CODE[marketCode]

  const legacy = (sessionStorage.getItem(CACHE_KEY) ?? sessionStorage.getItem(CACHE_KEY_LEGACY)) as GeoRegion | null
  if (legacy === 'africa') return MARKET_BY_CODE.africa
  if (legacy === 'global') return MARKET_BY_CODE.global
  if (legacy && MARKET_BY_CODE[legacy]) return MARKET_BY_CODE[legacy]
  return null
}

/** Infer an ISO country from the browser locale (e.g. "fr-SN" → "SN"). Client-only, no IP. */
function localeCountry(): string | null {
  try {
    const langs = [navigator.language, ...(navigator.languages ?? [])]
    for (const lang of langs) {
      const m = /[-_]([A-Za-z]{2})$/.exec(lang ?? '')
      if (m) return m[1].toUpperCase()
    }
  } catch { /* navigator unavailable */ }
  return null
}

async function detectMarket(): Promise<GeoMarket> {
  const cached = readCachedMarket()
  if (cached) return cached

  // Privacy-first: ask OUR backend (country from the CDN/edge layer) — the visitor's
  // IP never reaches a third party. If the edge can't resolve a country, infer from
  // the browser locale. No external geolocation provider is ever contacted.
  let country: string | null = null
  try {
    const controller = new AbortController()
    const tid = setTimeout(() => controller.abort(), TIMEOUT_MS)

    const res = await fetch(`${API_BASE}/api/public/geo`, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
    clearTimeout(tid)
    if (res.ok) country = (await res.json())?.country_code ?? null
  } catch {
    /* network/timeout — fall through to locale-based detection */
  }

  const market = resolveMarket(country ?? localeCountry())
  try {
    sessionStorage.setItem(CACHE_KEY, market.code === 'waemu' || market.code === 'cemac' ? 'africa' : market.code)
    sessionStorage.setItem(MARKET_CACHE_KEY, market.code)
  } catch { /* sessionStorage unavailable (private mode) */ }
  return market
}

// Module-level singleton — shared across all callers in the same page session
const _market = ref<GeoMarket>(MARKET_BY_CODE.global)
const _region = computed<GeoRegion>(() => _market.value.code)
const _isLoading = ref(true)
let _resolved = false

export function setMarketOverride(code: GeoRegion): void {
  const next = MARKET_BY_CODE[code] ?? MARKET_BY_CODE.global
  _market.value = next
  sessionStorage.setItem(MARKET_CACHE_KEY, next.code)
  sessionStorage.setItem(CACHE_KEY, next.code === 'waemu' || next.code === 'cemac' || next.code === 'africa' ? 'africa' : next.code)
  _isLoading.value = false
}

export function useGeoContent() {
  if (!_resolved) {
    _resolved = true
    _isLoading.value = true
    detectMarket().then(m => {
      _market.value = m
      _isLoading.value = false
    })
  }

  const isAfrica = computed(() => ['africa', 'waemu', 'cemac', 'nigeria', 'ghana', 'kenya', 'south_africa'].includes(_market.value.code))
  const selectedMarket = computed({
    get: () => _market.value.code,
    set: (code: GeoRegion) => setMarketOverride(code),
  })

  return {
    /** New market object for local currency/content selection. */
    market: readonly(_market),
    /** 'africa' | 'global' | market codes — kept for backward compatibility. */
    region: readonly(_region),
    /** true while geo-IP fetch is in-flight */
    isLoading: readonly(_isLoading),
    /** Shorthand boolean: true when the current market is an African target market. */
    isAfrica,
    /** Select options exposed so visitors can override VPN/proxy geolocation. */
    selectableMarkets,
    selectedMarket,
    setMarketOverride,
  }
}
