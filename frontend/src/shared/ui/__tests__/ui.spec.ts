import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseButton from '@/shared/ui/BaseButton.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { vFocusTrap } from '@/directives/focusTrap'

describe('StateBlock (UX-05)', () => {
  it('renders a spinner + aria-busy for loading', () => {
    const w = mount(StateBlock, { props: { variant: 'loading' } })
    expect(w.find('.state-block__spinner').exists()).toBe(true)
    expect(w.attributes('aria-busy')).toBe('true')
    expect(w.text()).toContain('Chargement')
  })

  it('renders a forbidden state with custom title/message + action slot', () => {
    const w = mount(StateBlock, {
      props: { variant: 'forbidden', title: 'Module désactivé', message: 'Activez-le dans Abonnement.' },
      slots: { action: '<button class="cta">Voir</button>' },
    })
    expect(w.classes()).toContain('state-block--forbidden')
    expect(w.text()).toContain('Module désactivé')
    expect(w.find('.cta').exists()).toBe(true)
  })
})

describe('BaseButton (UX-03)', () => {
  it('applies variant/size classes and disables while loading', () => {
    const w = mount(BaseButton, { props: { variant: 'danger', size: 'sm', loading: true }, slots: { default: 'Supprimer' } })
    expect(w.classes()).toContain('btn-base--danger')
    expect(w.classes()).toContain('btn-base--sm')
    expect(w.find('button').element.disabled).toBe(true)
    expect(w.attributes('aria-busy')).toBe('true')
  })
})

describe('BaseModal (UX-03/UX-04)', () => {
  it('renders dialog semantics when open and emits close', async () => {
    const w = mount(BaseModal, {
      props: { modelValue: true, title: 'Titre' },
      slots: { default: '<p>contenu</p>' },
      global: { directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
    })
    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')
    await w.find('.modal-close').trigger('click')
    expect(w.emitted('update:modelValue')?.[0]).toEqual([false])
  })
})
