import { describe, it, expect, vi } from 'vitest'
import { ref, defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import FormField from '@/shared/ui/FormField.vue'

// useUnsavedChanges imports onBeforeRouteLeave — stub it (no router in unit test).
vi.mock('vue-router', () => ({ onBeforeRouteLeave: vi.fn() }))
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'

describe('FormField (UX-07)', () => {
  it('links the error to the control via aria-describedby and exposes slot props', () => {
    const w = mount(FormField, {
      props: { label: 'Nom', required: true, error: 'Champ requis' },
      slots: {
        default: `<template #default="{ id, errorId, invalid }">
          <input :id="id" :aria-describedby="errorId" :aria-invalid="invalid" />
        </template>`,
      },
    })
    const input = w.find('input')
    const err = w.find('.form-field__error')
    expect(err.exists()).toBe(true)
    expect(err.attributes('role')).toBe('alert')
    // input's aria-describedby points at the error element's id
    expect(input.attributes('aria-describedby')).toBe(err.attributes('id'))
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(w.find('label').attributes('for')).toBe(input.attributes('id'))
  })

  it('shows hint (not error) when valid', () => {
    const w = mount(FormField, {
      props: { label: 'Email', hint: 'Pro recommandé' },
      slots: { default: '<input />' },
    })
    expect(w.find('.form-field__hint').text()).toBe('Pro recommandé')
    expect(w.find('.form-field__error').exists()).toBe(false)
  })
})

describe('useUnsavedChanges (UX-07)', () => {
  it('guards tab close only while dirty', async () => {
    const dirty = ref(false)
    const Comp = defineComponent({ setup() { useUnsavedChanges(dirty); return () => null } })
    mount(Comp)

    const fire = () => {
      const e = new Event('beforeunload', { cancelable: true })
      window.dispatchEvent(e)
      return e.defaultPrevented
    }

    expect(fire()).toBe(false) // not dirty → no prompt
    dirty.value = true
    await Promise.resolve()
    expect(fire()).toBe(true)  // dirty → prompt
  })
})
