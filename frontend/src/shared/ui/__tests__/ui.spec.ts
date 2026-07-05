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

describe('BaseModal (UX-03/UX-04 + refonte Side-Drawer)', () => {
  const mountModal = (props: Record<string, unknown>) =>
    mount(BaseModal, {
      props: { modelValue: true, title: 'Titre', ...props },
      slots: { default: '<p>contenu</p>' },
      global: { directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
    })

  it('renders dialog semantics when open and emits close', async () => {
    const w = mountModal({})
    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')
    await w.find('.modal-close').trigger('click')
    expect(w.emitted('update:modelValue')?.[0]).toEqual([false])
  })

  it('defaults to the right-side drawer variant', () => {
    const w = mountModal({})
    expect(w.find('.modal-overlay').classes()).toContain('modal-overlay--drawer')
    const box = w.find('[role="dialog"]')
    expect(box.classes()).toContain('modal--drawer')
    expect(box.classes()).toContain('modal--md') // taille par défaut
  })

  it('switches to a centered box for variant="center" + honours size', () => {
    const w = mountModal({ variant: 'center', size: 'sm' })
    expect(w.find('.modal-overlay').classes()).toContain('modal-overlay--center')
    const box = w.find('[role="dialog"]')
    expect(box.classes()).toContain('modal--center')
    expect(box.classes()).toContain('modal--sm')
  })

  it('renders an optional subtitle in the header', () => {
    const w = mountModal({ subtitle: 'BAS-0015 · Bassine 30L' })
    expect(w.find('.modal-subtitle').text()).toBe('BAS-0015 · Bassine 30L')
  })

  it('omits the subtitle node when none is provided', () => {
    const w = mountModal({})
    expect(w.find('.modal-subtitle').exists()).toBe(false)
  })
})
