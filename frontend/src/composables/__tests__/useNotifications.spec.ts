import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotifications } from '@/composables/useNotifications'

describe('useNotifications — client toasts & forbidden feedback', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })
  afterEach(() => { vi.useRealTimers() })

  it('pushToast adds a transient client toast that auto-dismisses', () => {
    const { toasts, pushToast } = useNotifications()
    const before = toasts.value.length

    pushToast('Boom', 'error')

    expect(toasts.value.length).toBe(before + 1)
    const t = toasts.value[toasts.value.length - 1]
    expect(t.message).toBe('Boom')
    expect(t.severity).toBe('error')
    expect(t.type).toBe('client')

    // auto-dismiss after the TTL
    vi.advanceTimersByTime(7_000)
    expect(toasts.value.find(x => x.id === t.id)).toBeUndefined()
  })

  it('pushToast defaults to the error severity', () => {
    const { toasts, pushToast } = useNotifications()
    const before = toasts.value.length

    pushToast('Oops')

    expect(toasts.value.length).toBe(before + 1)
    expect(toasts.value[toasts.value.length - 1].severity).toBe('error')
  })

  it('surfaces an api:forbidden event as an error toast with the API message', () => {
    const { toasts } = useNotifications() // first call registers the window listener (singleton)
    const before = toasts.value.length

    window.dispatchEvent(new CustomEvent('api:forbidden', { detail: { message: 'Action réservée aux gestionnaires.' } }))

    expect(toasts.value.length).toBe(before + 1)
    const t = toasts.value[toasts.value.length - 1]
    expect(t.message).toBe('Action réservée aux gestionnaires.')
    expect(t.severity).toBe('error')
    expect(t.type).toBe('client')
  })

  it('falls back to a generic message when the forbidden event carries none', () => {
    const { toasts } = useNotifications()
    const before = toasts.value.length

    window.dispatchEvent(new CustomEvent('api:forbidden', { detail: {} }))

    expect(toasts.value.length).toBe(before + 1)
    expect(toasts.value[toasts.value.length - 1].message).toBe('Action non autorisée.')
  })
})
