/**
 * useUrlFilters — keep a reactive filter object in sync with the URL query string
 * (audit UX-12). Two effects, wired by the caller so it keeps control of when data loads:
 *
 *   const { hydrate, push } = useUrlFilters(filters, { defaults: { page: 1 } })
 *   onMounted(() => { hydrate(); load() })          // restore filters from URL, then load
 *   watch(filters, push, { deep: true })            // mirror changes back to the URL
 *
 * Benefits: filters survive a page refresh, the back button and bookmarks, and a list
 * view becomes shareable by URL. Empty values and values equal to their default are
 * omitted so the URL stays clean. Types are coerced to match the current filter value
 * (numbers stay numbers), and `router.replace` is used so filtering never pollutes history.
 */
import { useRoute, useRouter } from 'vue-router'

type Filters = Record<string, string | number>

export function useUrlFilters<T extends Filters>(
  filters: T,
  opts: { defaults?: Partial<T> } = {},
) {
  const route    = useRoute()
  const router   = useRouter()
  const defaults = opts.defaults ?? {}

  /** Populate `filters` from the current URL query (call before the first load). */
  function hydrate(): void {
    for (const key of Object.keys(filters)) {
      const raw = route.query[key]
      if (raw === undefined) continue
      const value   = Array.isArray(raw) ? raw[0] : raw
      const current = filters[key]
      if (typeof current === 'number') {
        const n = Number(value)
        if (!Number.isNaN(n)) (filters as Filters)[key] = n
      } else {
        ;(filters as Filters)[key] = value ?? ''
      }
    }
  }

  /** Mirror `filters` into the URL query, dropping empty / default values. */
  function push(): void {
    const query: Record<string, string> = {}
    for (const key of Object.keys(filters)) {
      const value = filters[key]
      const def   = (defaults as Filters)[key]
      if (value === '' || value === null || value === undefined) continue
      if (def !== undefined && value === def) continue
      query[key] = String(value)
    }
    router.replace({ query }).catch(() => { /* ignore redundant navigation */ })
  }

  return { hydrate, push }
}
