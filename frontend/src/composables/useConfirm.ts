import { reactive, readonly } from 'vue'
import { t } from '@/i18n'

/**
 * Confirmation centrée (refonte UI — Phase 2).
 *
 * Remplace les `window.confirm()` natifs par une boîte de dialogue centrée,
 * cohérente avec le design system (mêmes boutons, angles, polices). Un host
 * unique `ConfirmDialog.vue` (monté dans `App.vue`) lit l'état partagé et
 * résout la promesse retournée par `confirm()`.
 *
 * Usage dans une vue :
 * ```ts
 * const { confirm } = useConfirm()
 * if (!(await confirm({ title: 'Supprimer', message: '…', danger: true }))) return
 * ```
 */
export interface ConfirmOptions {
  /** Titre court affiché dans l'en-tête. */
  title: string
  /** Message / question affichée dans le corps. */
  message: string
  /** Libellé du bouton de validation (défaut : « Confirmer »). */
  confirmLabel?: string
  /** Libellé du bouton d'annulation (défaut : « Annuler »). */
  cancelLabel?: string
  /** Style destructif (bouton rouge) — pour les suppressions / actions irréversibles. */
  danger?: boolean
}

interface ConfirmState {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  cancelLabel: string
  danger: boolean
}

const state = reactive<ConfirmState>({
  open: false,
  title: '',
  message: '',
  confirmLabel: '',
  cancelLabel: '',
  danger: false,
})

let resolver: ((value: boolean) => void) | null = null

function confirm(options: ConfirmOptions): Promise<boolean> {
  state.title        = options.title
  state.message      = options.message
  state.confirmLabel = options.confirmLabel ?? t('common.confirm')
  state.cancelLabel  = options.cancelLabel ?? t('common.cancel')
  state.danger       = options.danger ?? false
  state.open         = true
  return new Promise<boolean>((resolve) => { resolver = resolve })
}

/**
 * Résolution interne, appelée par le host `ConfirmDialog` à la validation,
 * à l'annulation ou à toute fermeture du volet (croix / Échap / clic-extérieur).
 */
export function settleConfirm(value: boolean): void {
  if (!state.open && resolver === null) return
  state.open = false
  const resolve = resolver
  resolver = null
  resolve?.(value)
}

/** État partagé (lecture seule) consommé par le host `ConfirmDialog`. */
export const confirmState = readonly(state)

export function useConfirm() {
  return { confirm }
}
