/**
 * LandingView — Automated quality gate
 *
 * Runs on every update to guarantee:
 *  1. No unescaped apostrophes in single-quoted JS strings (most common bug)
 *  2. All mandatory section anchors present
 *  3. Auth-aware CTAs exist (isAuthenticated checks)
 *  4. Geo-content composable is used
 *  5. Both africa and global data paths exist
 *  6. No Date.now() / Math.random() / new Date() calls
 *  7. Static year 2026 in copyright
 *
 * Strategy: source-file analysis only (fast, zero mounting dependencies).
 * Run:  npm run test:landing
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { resolve }      from 'path'

const LANDING_PATH = resolve(__dirname, '../LandingView.vue')
const src = readFileSync(LANDING_PATH, 'utf-8')

const scriptMatch = src.match(/<script setup[^>]*>([\s\S]*?)<\/script>/)
const script      = scriptMatch?.[1] ?? ''
const scriptLines = script.split('\n')

const tmplMatch = src.match(/<template>([\s\S]*?)<\/template>/)
const template  = tmplMatch?.[1] ?? ''

// ---- 1. No unescaped apostrophes in single-quoted strings ------------------
// Only check lines that are clearly JS value assignments (q:, desc:, tagline:, etc.)
// and use single-quoted strings. Skip double-quoted strings and template literals.
describe('Syntax safety - no unescaped apostrophes', () => {
  it('has no unescaped apostrophe inside a single-quoted JS string', () => {
    const issues: string[] = []

    scriptLines.forEach((line, i) => {
      const trimmed = line.trim()
      // Skip comments and template literals
      if (trimmed.startsWith('//') || trimmed.startsWith('*') || trimmed.includes('`')) return
      // Skip lines that start their value with a double quote (safe)
      if (/:\s*"/.test(trimmed) && !/:\s*'/.test(trimmed)) return

      // Only look at lines where the value is single-quoted.
      // Pattern: key: 'value with apostrophe in it'
      // A single-quoted string that contains an unescaped apostrophe would
      // look like: 'word d'word' — the inner apostrophe terminates the string early.
      // We detect this by finding: opening-quote + text + NON-ESCAPED-apostrophe + lowercase-letter
      // Specifically: '...letter'letter (the middle apostrophe has a letter BEFORE it and a letter AFTER it)
      const singleQuotedValue = trimmed.match(/:\s*'(.*)'/)
      if (!singleQuotedValue) return

      // Look for unescaped apostrophe pattern inside the value portion
      // The value content is everything between the outer quotes
      const valueContent = singleQuotedValue[1]
      // Match: lowercase/accented letter followed by ' followed by lowercase/accented letter
      // This is the signature of an unescaped apostrophe (d'achats, l'utilisateur, etc.)
      const innerMatch = valueContent.match(/[a-zà-ÿ]'[a-zA-Zà-ÿ]/)
      if (innerMatch) {
        issues.push('  line ' + (i + 1) + ': ' + trimmed + ' -> unescaped: "' + innerMatch[0] + '"')
      }
    })

    expect(issues, 'Unescaped apostrophes found:\n' + issues.join('\n')).toHaveLength(0)
  })
})

// ---- 2. Mandatory section anchors ------------------------------------------
describe('Structure - mandatory sections', () => {
  const required: Array<[string, string]> = [
    ['id="features"', 'Features section'],
    ['id="pricing"',  'Pricing section'],
    ['id="faq"',      'FAQ section'],
    ['href="#features"', 'Nav Features link'],
    ['href="#pricing"',  'Nav Pricing link'],
  ]
  required.forEach(([anchor, label]) => {
    it('contains ' + anchor + ' (' + label + ')', () => {
      expect(src, 'Missing: ' + label).toContain(anchor)
    })
  })
})

// ---- 3. Auth-aware CTAs (route values, not quote-specific) -----------------
describe('Auth-aware CTAs', () => {
  it('checks auth.isAuthenticated in template', () => {
    expect(template).toContain('auth.isAuthenticated')
  })
  it('has /admin route for super-admin', () => {
    expect(src).toContain('/admin')
  })
  it('has /dashboard route for regular users', () => {
    expect(src).toContain('/dashboard')
  })
  it('has /register for unauthenticated visitors', () => {
    expect(src).toContain('/register')
  })
  it('has /login for returning users', () => {
    expect(src).toContain('/login')
  })
})

// ---- 4. Geo-personalization -------------------------------------------------
describe('Geo-personalization', () => {
  it('imports useGeoContent', () => { expect(script).toContain('useGeoContent') })
  it('defines isAfrica computed', () => { expect(script).toContain('isAfrica') })
  it('has Africa-specific XOF content', () => { expect(script).toContain('XOF') })
  it('has localized EUR pricing', () => { expect(script).toMatch(/europe_eur|'€'|"€"/) })
  it('hero eyebrow is geo-aware (heroEyebrow computed)', () => {
    expect(script).toContain('heroEyebrow')
  })
  it('hero subtitle is geo-aware (heroSub computed)', () => {
    expect(script).toContain('heroSub')
  })
  it('global hero eyebrow mentions Europe and North America', () => {
    expect(script).toContain('Europe')
    expect(script).toContain('Amérique')
  })
  it('global stats mention 3 continents', () => {
    expect(script).toContain('continents')
  })
  it('PS heading highlight is geo-aware', () => {
    expect(script).toContain('psHeadingHighlight')
  })
  it('has Africa + Global feature payment variants', () => {
    expect(script).toContain('paymentFeatureGlobal')
    expect(script).toContain('paymentFeatureAfrica')
  })
  it('has Africa + Global testimonials', () => {
    expect(script).toContain('testimonialsGlobal')
    expect(script).toContain('testimonialsAfrica')
  })
  it('has market-aware localized plans and a manual selector', () => {
    expect(script).toContain('pricingAmounts')
    expect(script).toContain('waemu_xof')
    expect(script).toContain('canada_cad')
    expect(src).toContain('market-select')
  })
  it('has Africa + Global stats', () => {
    expect(script).toContain('statsGlobal')
    expect(script).toContain('statsAfrica')
  })
})

// ---- 5. Determinism - no runtime calls -------------------------------------
describe('Determinism and static content', () => {
  it('has no Date.now() call', () => { expect(script).not.toContain('Date.now()') })
  it('has no new Date() call', () => { expect(script).not.toMatch(/new\s+Date\s*\(/) })
  it('has no Math.random() call', () => { expect(script).not.toContain('Math.random()') })
  it('contains static year 2026 in copyright', () => {
    expect(src).toMatch(/2026.*Frynov|Frynov.*2026/)
  })
})

// ---- 6. Content completeness -----------------------------------------------
describe('Content completeness', () => {
  const WORDS = ['Inventaire', 'Commandes', 'Clients', 'Rapports', 'Starter', 'Essentiel', 'Pro', 'Enterprise']
  WORDS.forEach(w => {
    it('contains the word "' + w + '"', () => { expect(src).toContain(w) })
  })
  it('has at least 3 FAQ items', () => {
    const count = (script.match(/q:\s*['"]/g) ?? []).length
    expect(count, 'Expected >= 3 FAQ items, found ' + count).toBeGreaterThanOrEqual(3)
  })
  it('has at least 3 testimonials', () => {
    const count = (script.match(/quote:\s*['"]/g) ?? []).length
    expect(count, 'Expected >= 3 testimonials, found ' + count).toBeGreaterThanOrEqual(3)
  })
})