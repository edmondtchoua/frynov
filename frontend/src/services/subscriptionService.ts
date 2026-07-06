/**
 * subscriptionService — devis AUTORITATIF de changement de plan (P0).
 *
 * Le montant à payer n'est PAS calculé côté client : on interroge le backend
 * (`POST /api/me/subscription/calculate-upgrade`) qui renvoie le brut serveur, la remise d'une promo
 * validée, l'avoir de proration et le NET à payer. Le formulaire verrouille son champ sur
 * `net_payable_minor` — le client ne peut plus falsifier le total (critère d'acceptation #22).
 *
 * Tous les montants sont en unités MINEURES de `currency` (× 100 partout ; XOF/XAF exposant 0).
 */
import client from '@/api/client'

export interface UpgradeQuotePromo {
  code: string
  valid: boolean
  /** 'code' = saisi par l'utilisateur ; 'auto' = promotion en cours appliquée automatiquement. */
  source?: 'code' | 'auto' | string
  discount_type?: 'percent' | 'fixed_cents' | string
  discount_value?: number
  /** Nombre de périodes couvertes par la fenêtre de validité de la promo (P0.1). */
  covered_periods?: number
  discount_minor: number
  message?: string
}

export interface UpgradeQuote {
  plan_code: string
  plan_name: string
  interval: 'monthly' | 'yearly'
  quantity: number
  market: string
  currency: string
  exponent: number
  is_free: boolean
  unit_gross_minor: number
  base_gross_minor: number
  subtotal_minor: number
  promo: UpgradeQuotePromo | null
  gross_after_promo_minor: number
  tax_rate_bps: number
  tax_minor: number
  setup_fee_minor: number
  proration: {
    applied_credit_minor: number
    carry_credit_minor: number
    fraction_remaining: number
  }
  net_payable_minor: number
}

export interface CalculateUpgradeParams {
  plan_code: string
  interval: 'monthly' | 'yearly'
  quantity?: number
  promo_code?: string
  market_code?: string
}

export async function calculateUpgrade(params: CalculateUpgradeParams): Promise<UpgradeQuote> {
  const { data } = await client.post<UpgradeQuote>('/api/me/subscription/calculate-upgrade', params)
  return data
}

export interface ConsentText {
  version: string
  text: string
}

/** Texte + version du consentement (source de vérité serveur), affiché verbatim dans la case obligatoire. */
export async function fetchConsentText(): Promise<ConsentText> {
  const { data } = await client.get<ConsentText>('/api/me/subscription/consent-text')
  return data
}

export interface QuotaOverage {
  resource: 'users' | 'products' | 'customers' | 'warehouses' | 'orders' | string
  usage: number
  limit: number
  excess: number
}

export interface DowngradeImpact {
  is_downgrade: boolean
  from_plan: string | null
  to_plan: string
  modules_lost: string[]
  quota_overages: QuotaOverage[]
  has_impact: boolean
}

/** Aperçu d'impact d'un changement de plan (modules retirés + quotas dépassés) avant confirmation. */
export async function fetchDowngradeImpact(planCode: string): Promise<DowngradeImpact> {
  const { data } = await client.post<DowngradeImpact>('/api/me/subscription/downgrade-impact', { plan_code: planCode })
  return data
}

export interface UsageRow {
  resource: 'users' | 'products' | 'customers' | 'warehouses' | 'orders' | 'imports' | string
  usage: number
  /** null = illimité sur le plan. */
  limit: number | null
  remaining: number | null
  percent: number | null
}

export interface UsageReport {
  plan: string
  data: UsageRow[]
}

/** Consommation vs quota du plan par ressource (P5 outillage). */
export async function fetchUsage(): Promise<UsageReport> {
  const { data } = await client.get<UsageReport>('/api/me/subscription/usage')
  return data
}
