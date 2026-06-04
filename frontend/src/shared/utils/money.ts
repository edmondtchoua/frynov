/**
 * Centralized money helpers — the single source of truth for the centimes convention.
 *
 * CONVENTION (used DB-wide): monetary amounts are stored as integer centimes (× 100).
 *   - A 4 200 XOF price is stored as 420000.
 *   - To DISPLAY: divide by 100 → formatMoney(420000) === "4 200 XOF".
 *   - To SUBMIT a user-typed amount: multiply by 100 → toCents(4200) === 420000.
 *
 * Five distinct ×100/÷100 bugs were traced to ad-hoc per-view formatters that each
 * re-implemented (and sometimes mis-implemented) this convention. Always use these.
 */

const FR_LOCALE = 'fr-FR'

/** Currencies with no minor unit in practice (CFA francs) — displayed without decimals. */
const NO_DECIMAL_CURRENCIES = new Set(['XOF', 'XAF'])

/**
 * Format an integer centimes amount as a localized currency string.
 * @param cents     amount in centimes (× 100). e.g. 420000 → "4 200 XOF"
 * @param currency  ISO 4217 code (default XOF)
 */
export function formatMoney(cents: number | null | undefined, currency = 'XOF'): string {
  const value = (cents ?? 0) / 100
  const noDecimals = NO_DECIMAL_CURRENCIES.has(currency)
  try {
    return new Intl.NumberFormat(FR_LOCALE, {
      style: 'currency',
      currency,
      // CFA: no minor unit. Other currencies (EUR/USD…): always 2 decimals.
      minimumFractionDigits: noDecimals ? 0 : 2,
      maximumFractionDigits: noDecimals ? 0 : 2,
    }).format(value)
  } catch {
    // Unknown currency code → graceful fallback
    return `${value.toLocaleString(FR_LOCALE)} ${currency}`
  }
}

/**
 * Compact form for dashboards/charts, e.g. 1 250 000 cents → "12,5 k".
 * (No currency suffix — callers add the unit/label.)
 */
export function formatMoneyCompact(cents: number | null | undefined): string {
  const amount = (cents ?? 0) / 100
  if (Math.abs(amount) >= 1_000_000) return `${(amount / 1_000_000).toFixed(1)} M`
  if (Math.abs(amount) >= 1_000)     return `${(amount / 1_000).toFixed(1)} k`
  return String(Math.round(amount))
}

/**
 * Convert a user-typed amount (major units) to integer centimes for submission.
 * @param amount  e.g. 4200 (XOF the user typed) → 420000
 */
export function toCents(amount: number | string | null | undefined): number {
  const n = typeof amount === 'string' ? parseFloat(amount) : (amount ?? 0)
  if (!Number.isFinite(n)) return 0
  return Math.round(n * 100)
}

/**
 * Convert integer centimes back to major units for editing in a number input.
 * @param cents  e.g. 420000 → 4200
 */
export function fromCents(cents: number | null | undefined): number {
  return (cents ?? 0) / 100
}
