/**
 * Centralized date helpers — single source of truth for date display.
 *
 * 23 ad-hoc `fmtDate`/`formatDate`/`shortDate` functions existed across the views,
 * each re-implementing `Intl.DateTimeFormat`/`toLocaleDateString` with slightly
 * different options and locales (fr-FR vs fr-SN). Use these instead.
 *
 * All variants are null-safe: invalid/empty input → "—".
 */

const FR_LOCALE = 'fr-FR'
const EMPTY = '—'

function toDate(iso: string | null | undefined): Date | null {
  if (!iso) return null
  // RC-23 — une date PURE ('2026-01-01', ou '2026-01-01T00:00:00.000000Z' issue d'un cast date
  // Laravel) est un jour CALENDAIRE, pas un instant : la parser en UTC la décale d'un jour dans
  // les fuseaux à l'ouest d'UTC. On la construit en heure LOCALE (composantes explicites).
  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})(?:T00:00:00(?:\.0+)?Z?)?$/.exec(iso)
  if (dateOnly) {
    return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]))
  }
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

/** Date only, medium: "1 juin 2026". The most common variant. */
export function formatDate(iso: string | null | undefined): string {
  const d = toDate(iso)
  if (!d) return EMPTY
  return new Intl.DateTimeFormat(FR_LOCALE, {
    day: '2-digit', month: 'short', year: 'numeric',
  }).format(d)
}

/** Date + time: "1 juin 2026 10:00". For audit logs, timestamps. */
export function formatDateTime(iso: string | null | undefined): string {
  const d = toDate(iso)
  if (!d) return EMPTY
  return new Intl.DateTimeFormat(FR_LOCALE, {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  }).format(d)
}

/** Short date, no year: "1 juin". For dense lists/charts. */
export function formatDateShort(iso: string | null | undefined): string {
  const d = toDate(iso)
  if (!d) return EMPTY
  return new Intl.DateTimeFormat(FR_LOCALE, {
    day: 'numeric', month: 'short',
  }).format(d)
}
