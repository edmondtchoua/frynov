/**
 * publicPricingService — fetches localized plan pricing from the backend, the
 * single source of truth for public prices (P3 endpoint `GET /api/public/pricing`).
 *
 * The landing page must NEVER hardcode contractual prices: it asks the backend for
 * the plans, currency and limits of the resolved market (by `market` code or by
 * `country`). Uses a raw fetch (like useGeoContent) because this is a PUBLIC page —
 * no auth token, no axios 401-redirect interceptor.
 */

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? ''
const TIMEOUT_MS = 4_000

export interface PublicPlanPrice {
  market_code: string
  currency: string
  interval: string
  /** Amount in integer centimes (× 100), uniform across currencies. e.g. 2500 → 25,00 CAD ; 990000 → 9 900 XOF. */
  base_amount_minor: number
  included_users: number | null
  extra_user_amount_minor: number | null
}

export interface PublicPlanLimits {
  max_products: number | null
  max_monthly_orders: number | null
  max_customers: number | null
  max_branches: number | null
  max_warehouses: number | null
  max_imports_per_month: number | null
  max_api_calls_per_month: number | null
  storage_mb: number | null
}

export interface PublicPlan {
  code: string
  name: string
  description: string | null
  trial_days: number | null
  features: string[]
  sort_order: number
  price: PublicPlanPrice | null
  limits: PublicPlanLimits | null
}

export interface PublicMarket {
  code: string
  label: string
  currency: string
  source: 'market' | 'country' | 'fallback' | string
  country: string | null
}

export interface SelectableMarket {
  code: string
  label: string
  currency: string
  countries: string[]
}

export interface PublicPricingResponse {
  market: PublicMarket
  selectable_markets: SelectableMarket[]
  data: PublicPlan[]
}

/** P6 — moyen de paiement disponible pour un marché. */
export interface PublicPaymentMethod {
  method: string
  /** `auto` = rail PSP réel ; `manual` = preuve + validation admin ; `quote` = sur devis. */
  mode: 'auto' | 'manual' | 'quote' | string
  currency: string
  label: string | null
}

export interface PublicPaymentMethodsResponse {
  market: { code: string; label: string; currency: string; source: string }
  /** Vrai dès qu'au moins un moyen est un rail automatique (faux tant qu'aucun PSP n'est branché). */
  has_auto: boolean
  data: PublicPaymentMethod[]
}

/**
 * Fetch the payment methods available for a market (or country, resolved server-side).
 * Public endpoint (P6-1). Throws on non-OK/timeout so callers can degrade gracefully.
 */
export async function fetchPublicPaymentMethods(
  params: { market?: string; country?: string } = {},
): Promise<PublicPaymentMethodsResponse> {
  const qs = new URLSearchParams()
  if (params.market) qs.set('market', params.market)
  if (params.country) qs.set('country', params.country)
  const query = qs.toString()
  const url = `${API_BASE}/api/public/payment-methods${query ? `?${query}` : ''}`

  const controller = new AbortController()
  const tid = setTimeout(() => controller.abort(), TIMEOUT_MS)
  try {
    const res = await fetch(url, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) throw new Error(`public payment methods request failed: ${res.status}`)
    return (await res.json()) as PublicPaymentMethodsResponse
  } finally {
    clearTimeout(tid)
  }
}

/**
 * Fetch public pricing for a market (preferred) or a country (fallback resolution
 * server-side). Throws on a non-OK response or timeout so callers can degrade.
 */
export async function fetchPublicPricing(
  params: { market?: string; country?: string } = {},
): Promise<PublicPricingResponse> {
  const qs = new URLSearchParams()
  if (params.market) qs.set('market', params.market)
  if (params.country) qs.set('country', params.country)
  const query = qs.toString()
  const url = `${API_BASE}/api/public/pricing${query ? `?${query}` : ''}`

  const controller = new AbortController()
  const tid = setTimeout(() => controller.abort(), TIMEOUT_MS)
  try {
    const res = await fetch(url, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) throw new Error(`public pricing request failed: ${res.status}`)
    return (await res.json()) as PublicPricingResponse
  } finally {
    clearTimeout(tid)
  }
}
