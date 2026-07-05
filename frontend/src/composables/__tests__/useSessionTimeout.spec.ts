import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSessionTimeout } from '@/composables/useSessionTimeout'

describe('useSessionTimeout', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })
  afterEach(() => { vi.useRealTimers() })

  it('exports start and reset functions', () => {
    const { start, reset } = useSessionTimeout()
    expect(typeof start).toBe('function')
    expect(typeof reset).toBe('function')
  })

  it('can be started without throwing', () => {
    const { start } = useSessionTimeout()
    expect(() => start()).not.toThrow()
  })

  it('reset does not throw after start', () => {
    const { start, reset } = useSessionTimeout()
    start()
    expect(() => reset()).not.toThrow()
  })
})
