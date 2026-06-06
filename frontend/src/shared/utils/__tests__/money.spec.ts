import { describe, it, expect } from 'vitest'
import { formatMoney, formatMoneyCompact, toCents, fromCents } from '@/shared/utils/money'

describe('money util — centimes convention', () => {
  describe('formatMoney (cents → display)', () => {
    it('divides centimes by 100', () => {
      // 420000 cents = 4 200 XOF (the recurring ×100 bug source)
      expect(formatMoney(420000)).toContain('4')
      expect(formatMoney(420000)).toContain('200')
      expect(formatMoney(420000)).not.toContain('420')
    })

    it('renders XOF/XAF without decimals', () => {
      expect(formatMoney(420050, 'XAF')).not.toContain(',')
      expect(formatMoney(420050, 'XAF')).not.toContain('.')
    })

    it('renders EUR with 2 decimals', () => {
      // 1250 cents = 12,50 €
      expect(formatMoney(1250, 'EUR')).toMatch(/12[.,]50/)
    })

    it('treats null/undefined as 0', () => {
      expect(formatMoney(null)).toContain('0')
      expect(formatMoney(undefined)).toContain('0')
    })

    it('falls back gracefully on an invalid currency', () => {
      const out = formatMoney(10000, 'NOTACUR')
      expect(out).toContain('NOTACUR')
    })
  })

  describe('formatMoneyCompact', () => {
    it('compacts thousands and millions', () => {
      expect(formatMoneyCompact(1_250_000)).toBe('12.5 k')   // 12 500 → 12.5 k (toFixed → dot)
      expect(formatMoneyCompact(500_000_000)).toBe('5.0 M')  // 5 000 000 → 5.0 M
    })
    it('shows small amounts plainly', () => {
      expect(formatMoneyCompact(45000)).toBe('450')
    })
  })

  describe('toCents (input → cents)', () => {
    it('multiplies by 100 and rounds', () => {
      expect(toCents(4200)).toBe(420000)
      expect(toCents(12.5)).toBe(1250)
      expect(toCents('15000')).toBe(1500000)
    })
    it('handles empty/invalid input as 0', () => {
      expect(toCents('')).toBe(0)
      expect(toCents(null)).toBe(0)
      expect(toCents(undefined)).toBe(0)
    })
    it('round-trips with fromCents', () => {
      expect(fromCents(toCents(4200))).toBe(4200)
    })
  })

  describe('fromCents (cents → editable major units)', () => {
    it('divides by 100', () => {
      expect(fromCents(420000)).toBe(4200)
      expect(fromCents(null)).toBe(0)
    })
  })
})
