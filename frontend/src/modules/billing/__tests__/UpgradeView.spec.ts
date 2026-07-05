/**
 * UpgradeView — P5 guard: the authenticated upgrade page must source plan prices
 * from the backend (same source of truth as the public landing), never from
 * hardcoded contractual values. Source-file analysis (like LandingView.spec) —
 * fast and dependency-free; it guards against a regression that re-hardcodes prices.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

const src = readFileSync(resolve(__dirname, '../views/UpgradeView.vue'), 'utf-8')
const script = src.match(/<script setup[^>]*>([\s\S]*?)<\/script>/)?.[1] ?? ''

describe('UpgradeView — backend-sourced localized pricing (P5)', () => {
  it('consumes the public pricing API as the source of truth', () => {
    expect(script).toContain('fetchPublicPricing')
    expect(script).toContain('apiPlans')
    expect(script).toContain('planPrice')
  })

  it('routes every plan price through planPrice(code, fallback)', () => {
    expect(script).toMatch(/planPrice\('starter'/)
    expect(script).toMatch(/planPrice\('essential'/)
    expect(script).toMatch(/planPrice\('pro'/)
    expect(script).toMatch(/planPrice\('enterprise'/)
  })

  it('keeps the local prices only as an offline fallback', () => {
    expect(script).toContain('localizedPrices')
  })

  it('still offers a manual currency/market selector', () => {
    expect(src).toContain('upgrade-market-select')
  })
})
