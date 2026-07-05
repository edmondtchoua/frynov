import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { vFocusTrap } from '@/directives/focusTrap'

describe('vFocusTrap directive', () => {
  it('calls the bound handler on Escape', async () => {
    const close = vi.fn()
    const w = mount(
      { template: '<div v-focus-trap="close"><button>a</button><button>b</button></div>', setup: () => ({ close }) },
      { global: { directives: { 'focus-trap': vFocusTrap } }, attachTo: document.body },
    )

    await w.find('div').trigger('keydown', { key: 'Escape' })
    expect(close).toHaveBeenCalledTimes(1)
    w.unmount()
  })

  it('ignores Escape when no handler is bound (focus-trap only)', async () => {
    const w = mount(
      { template: '<div v-focus-trap><button>a</button></div>' },
      { global: { directives: { 'focus-trap': vFocusTrap } }, attachTo: document.body },
    )
    // No throw on Escape / Tab when bound value is undefined.
    await w.find('div').trigger('keydown', { key: 'Escape' })
    await w.find('div').trigger('keydown', { key: 'Tab' })
    expect(w.find('button').exists()).toBe(true)
    w.unmount()
  })
})
