import { describe, it, expect } from 'vitest'
import { formatDate, formatDateTime, formatDateShort } from '@/shared/utils/date'

const ISO = '2026-06-01T10:05:00Z'

describe('date util', () => {
  describe('formatDate (date only)', () => {
    it('renders day/month/year', () => {
      const out = formatDate(ISO)
      expect(out).toContain('2026')
      expect(out.toLowerCase()).toContain('juin')
    })
    it('returns — for null/invalid', () => {
      expect(formatDate(null)).toBe('—')
      expect(formatDate(undefined)).toBe('—')
      expect(formatDate('not-a-date')).toBe('—')
      expect(formatDate('')).toBe('—')
    })
  })

  describe('formatDateTime', () => {
    it('includes hour and minute', () => {
      const out = formatDateTime(ISO)
      expect(out).toContain('2026')
      expect(out).toMatch(/\d{2}:\d{2}/)
    })
    it('returns — for null', () => {
      expect(formatDateTime(null)).toBe('—')
    })
  })

  describe('formatDateShort', () => {
    it('omits the year', () => {
      const out = formatDateShort(ISO)
      expect(out.toLowerCase()).toContain('juin')
      expect(out).not.toContain('2026')
    })
    it('returns — for invalid', () => {
      expect(formatDateShort('garbage')).toBe('—')
    })
  })
})
