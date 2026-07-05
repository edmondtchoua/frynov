import { describe, it, expect, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ConfirmDialog from '@/shared/ui/ConfirmDialog.vue'
import { useConfirm, settleConfirm } from '@/composables/useConfirm'
import { vFocusTrap } from '@/directives/focusTrap'
import { setLocale } from '@/i18n'

function mountHost() {
  return mount(ConfirmDialog, {
    global: { directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

describe('ConfirmDialog + useConfirm (refonte — confirmations centrées)', () => {
  // Réinitialise l'état singleton entre les tests (ferme un éventuel dialogue ouvert).
  beforeEach(() => { settleConfirm(false); setLocale('fr') })

  it('reste masqué tant que confirm() n\'est pas appelé', () => {
    const w = mountHost()
    expect(w.find('[role="dialog"]').exists()).toBe(false)
  })

  it('s\'ouvre en boîte centrée avec titre/message et résout true à la validation', async () => {
    const w = mountHost()
    const { confirm } = useConfirm()
    const p = confirm({ title: 'Supprimer', message: 'Action irréversible ?', danger: true })
    await flushPromises()

    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.classes()).toContain('modal--center')   // variant centré
    expect(dialog.text()).toContain('Supprimer')
    expect(dialog.text()).toContain('Action irréversible ?')
    // danger → bouton de validation rouge
    expect(w.find('[data-test="confirm-accept"]').classes()).toContain('btn-danger')

    await w.find('[data-test="confirm-accept"]').trigger('click')
    await expect(p).resolves.toBe(true)
    await flushPromises()
    expect(w.find('[role="dialog"]').exists()).toBe(false) // refermé après réponse
  })

  it('résout false à l\'annulation', async () => {
    const w = mountHost()
    const { confirm } = useConfirm()
    const p = confirm({ title: 'X', message: 'Y' })
    await flushPromises()

    await w.findAll('button').find(b => b.text() === 'Annuler')!.trigger('click')
    await expect(p).resolves.toBe(false)
  })

  it('utilise « Confirmer » (i18n common.confirm) comme libellé par défaut + bouton primaire hors danger', async () => {
    const w = mountHost()
    const { confirm } = useConfirm()
    confirm({ title: 'X', message: 'Y' })
    await flushPromises()

    const accept = w.find('[data-test="confirm-accept"]')
    expect(accept.text()).toBe('Confirmer')
    expect(accept.classes()).toContain('btn-primary')
    settleConfirm(false)
  })
})
