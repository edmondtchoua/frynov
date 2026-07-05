import { watch, onBeforeUnmount, type Ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

/**
 * Data-loss protection for critical forms (audit UX-07).
 *
 * While `isDirty` is true:
 *  - a native beforeunload prompt guards tab close / reload;
 *  - in-app route navigation asks for confirmation before leaving.
 *
 * Usage:
 *   const dirty = ref(false)
 *   useUnsavedChanges(dirty)
 *   // set dirty = true on first edit, dirty = false after a successful save.
 */
export function useUnsavedChanges(
  isDirty: Ref<boolean>,
  message = 'Des modifications non enregistrées seront perdues. Quitter cette page ?',
): void {
  const beforeUnload = (e: BeforeUnloadEvent) => {
    if (!isDirty.value) return
    e.preventDefault()
    e.returnValue = '' // Chrome requires returnValue to be set
  }

  watch(
    isDirty,
    (dirty) => {
      if (dirty) window.addEventListener('beforeunload', beforeUnload)
      else window.removeEventListener('beforeunload', beforeUnload)
    },
    { immediate: true },
  )

  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

  onBeforeRouteLeave(() => {
    if (!isDirty.value) return true
    return window.confirm(message)
  })
}
