import type { Directive } from 'vue'

/**
 * v-focus-trap — accessibility for modal dialogs (audit UX-04).
 *
 * On mount: remembers the previously-focused element, moves focus into the dialog,
 * and keeps Tab/Shift+Tab cycling within it. On unmount: restores focus to the
 * trigger. If a function is bound (`v-focus-trap="close"`), Escape calls it.
 *
 * Usage: put it on the dialog container (the `.modal`, not the overlay):
 *   <div class="modal" v-focus-trap="close" role="dialog" aria-modal="true"> … </div>
 */
const FOCUSABLE =
  'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'

interface TrapEl extends HTMLElement {
  _focusTrap?: { prev: Element | null; onKeydown: (e: KeyboardEvent) => void }
}

function focusable(el: HTMLElement): HTMLElement[] {
  return Array.from(el.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
    e => e.offsetParent !== null || e === document.activeElement,
  )
}

export const vFocusTrap: Directive<TrapEl, ((e?: KeyboardEvent) => void) | undefined> = {
  mounted(el, binding) {
    const prev = document.activeElement

    const onKeydown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && typeof binding.value === 'function') {
        binding.value(e)
        return
      }
      if (e.key !== 'Tab') return
      const items = focusable(el)
      if (!items.length) return
      const first = items[0]
      const last = items[items.length - 1]
      const active = document.activeElement as HTMLElement | null
      if (e.shiftKey && active === first) {
        e.preventDefault()
        last.focus()
      } else if (!e.shiftKey && active === last) {
        e.preventDefault()
        first.focus()
      } else if (active && !el.contains(active)) {
        e.preventDefault()
        first.focus()
      }
    }

    el._focusTrap = { prev, onKeydown }
    el.addEventListener('keydown', onKeydown)

    // Move focus into the dialog after it paints.
    requestAnimationFrame(() => {
      const items = focusable(el)
      ;(items[0] ?? el).focus?.()
    })
  },

  unmounted(el) {
    if (!el._focusTrap) return
    el.removeEventListener('keydown', el._focusTrap.onKeydown)
    const prev = el._focusTrap.prev as HTMLElement | null
    if (prev && typeof prev.focus === 'function') prev.focus()
    delete el._focusTrap
  },
}
