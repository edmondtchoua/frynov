/**
 * Garde i18n (UX-13) — gate CI dur.
 *
 * 1. PARITÉ : les clés de `messages.fr` et `messages.en` sont strictement identiques.
 * 2. ANTI-RÉGRESSION : aucune vue de module déjà internationalisée ne contient de texte
 *    français « en dur » dans son <template> (heuristique : caractère accenté hors `{{ }}`).
 *    Les vues pas encore traduites sont listées dans ALLOWLIST (ratchet : on retire une
 *    entrée dès que la vue est traduite — cf. docs/recette/i18n-coverage.md).
 *
 * Portée : `src/modules/(asterisk)(asterisk)/views/*.vue`. Le script-side et le chrome partagé ne sont
 * pas (encore) couverts ici ; ils relèvent de la Definition of Done + du tracker.
 */
import { describe, it, expect } from 'vitest'
import { messages } from '../index'

// ── Vues de module non encore internationalisées (ratchet) ───────────────────
// Retirer une ligne dès que la vue est traduite FR+EN. Objectif : liste vide.
const ALLOWLIST = new Set<string>([
  'admin/views/AdminDashboardView.vue',
  'admin/views/AuditLogView.vue',
  'admin/views/ModuleListView.vue',
  'billing/views/BillingView.vue',
  'billing/views/UpgradeView.vue',
  'inventory/views/BatchDeliveryView.vue',
  'inventory/views/MovementHistoryView.vue',
  'onboarding/views/OnboardingView.vue',
  'import-export/views/ImportHistoryView.vue',
  'import-export/views/ImportWizardView.vue',
  'marketplace/views/MarketplaceListingsView.vue',
  'settings/views/SettingsView.vue',
  // Customers — session concurrente, exclu tant que le verrou n'est pas levé.
  'customers/views/CustomerListView.vue',
  'customers/views/CustomerDetailView.vue',
])

const ACCENTED = /[àâäéèêëîïôöùûüçœÀÂÄÉÈÊËÎÏÔÖÙÛÜÇŒ]/

// ── Helpers ──────────────────────────────────────────────────────────────────
function flatten(dict: Record<string, unknown>, prefix = ''): string[] {
  const keys: string[] = []
  for (const [k, v] of Object.entries(dict)) {
    const path = prefix ? `${prefix}.${k}` : k
    if (v && typeof v === 'object') keys.push(...flatten(v as Record<string, unknown>, path))
    else keys.push(path)
  }
  return keys
}

function templateBlock(src: string): string {
  const m = src.match(/<template>([\s\S]*)<\/template>/i)
  return m ? m[1] : ''
}

/** Texte FR « en dur » à haute confiance (caractère accenté) dans le <template>. */
function hardcodedFrench(src: string): string[] {
  const tpl = templateBlock(src)
  const hits: string[] = []

  // Attributs statiques (placeholder/title/aria-label/alt/label="…"), pas les liaisons :attr
  const attrRe = /\s(?:placeholder|title|aria-label|alt|label)="([^"]*)"/g
  let m: RegExpExecArray | null
  while ((m = attrRe.exec(tpl)) !== null) {
    if (ACCENTED.test(m[1])) hits.push(`attr> ${m[1].trim().slice(0, 70)}`)
  }

  // Nœuds texte : retirer commentaires, interpolations {{…}}, puis les balises
  const text = tpl
    .replace(/<!--[\s\S]*?-->/g, ' ')
    .replace(/\{\{[\s\S]*?\}\}/g, ' ')
    .replace(/<[^>]+>/g, '\n')
  for (const line of text.split('\n')) {
    const t = line.trim()
    if (t && ACCENTED.test(t)) hits.push(`text> ${t.slice(0, 70)}`)
  }
  return hits
}

// Sources brutes des vues de module (chargées par Vite à la compilation des tests)
const RAW = import.meta.glob('../../modules/**/views/*.vue', {
  query: '?raw',
  import: 'default',
  eager: true,
}) as Record<string, string>

/** '../../modules/admin/views/X.vue' → 'admin/views/X.vue' */
function relKey(globKey: string): string {
  const i = globKey.indexOf('modules/')
  return i >= 0 ? globKey.slice(i + 'modules/'.length) : globKey
}

describe('i18n — parité des clés FR/EN', () => {
  it('messages.fr et messages.en ont exactement les mêmes clés', () => {
    const fr = new Set(flatten(messages.fr as Record<string, unknown>))
    const en = new Set(flatten(messages.en as Record<string, unknown>))
    const missingInEn = [...fr].filter((k) => !en.has(k)).sort()
    const missingInFr = [...en].filter((k) => !fr.has(k)).sort()
    expect(missingInEn, `Clés présentes en FR mais absentes en EN:\n${missingInEn.join('\n')}`).toEqual([])
    expect(missingInFr, `Clés présentes en EN mais absentes en FR:\n${missingInFr.join('\n')}`).toEqual([])
  })
})

describe('i18n — anti-régression texte FR en dur (vues de module)', () => {
  it('aucune vue traduite (hors allowlist) ne contient de texte FR accentué en dur', () => {
    const offenders: string[] = []
    for (const [globKey, src] of Object.entries(RAW)) {
      const rel = relKey(globKey)
      if (ALLOWLIST.has(rel)) continue
      const hits = hardcodedFrench(src)
      if (hits.length) offenders.push(`\n${rel}:\n  ${hits.join('\n  ')}`)
    }
    expect(offenders, `Texte FR en dur détecté (utiliser $t/t) :${offenders.join('')}`).toEqual([])
  })

  it("l'allowlist ne référence que des vues réellement présentes (anti-bitrot)", () => {
    const present = new Set(Object.keys(RAW).map(relKey))
    const stale = [...ALLOWLIST].filter((p) => !present.has(p)).sort()
    expect(stale, `Entrées d'allowlist obsolètes (vue déplacée/supprimée) :\n${stale.join('\n')}`).toEqual([])
  })
})
