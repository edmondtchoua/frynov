/**
 * useProgress — module-level singleton progress bar state.
 *
 * Used by Axios interceptors AND Vue Router guards so any pending
 * request or navigation triggers the same animated progress bar.
 *
 * start() / done() are reference-counted — nested concurrent calls
 * keep the bar alive until the last one completes.
 */

import { ref } from 'vue'

const _active   = ref(false)
const _progress = ref(0)
const _error    = ref(false)

let _pendingCount = 0
let _fillInterval: ReturnType<typeof setInterval> | null = null
let _doneTimeout:  ReturnType<typeof setTimeout>  | null = null

function _clearTimers() {
  if (_fillInterval) { clearInterval(_fillInterval); _fillInterval = null }
  if (_doneTimeout)  { clearTimeout(_doneTimeout);   _doneTimeout  = null }
}

/** Start (or join) an in-progress indication. */
export function progressStart() {
  _pendingCount++
  _error.value = false

  if (_active.value) return   // already running — counter was enough

  _clearTimers()
  _active.value   = true
  _progress.value = 3          // instant tiny bump

  // Ease toward 80% — fast at first, then slow
  _fillInterval = setInterval(() => {
    if (_progress.value < 80) {
      const step = _progress.value < 30 ? 6
                 : _progress.value < 55 ? 2.5
                 : _progress.value < 70 ? 1
                 : 0.3
      _progress.value = Math.min(80, _progress.value + step)
    }
  }, 120)
}

/** Finish an in-progress indication (no error). */
export function progressDone() {
  _pendingCount = Math.max(0, _pendingCount - 1)
  if (_pendingCount > 0) return   // other requests still pending

  _clearTimers()
  _progress.value = 100

  _doneTimeout = setTimeout(() => {
    _active.value   = false
    _progress.value = 0
    _error.value    = false
  }, 380)
}

/** Finish with an error state (bar turns red). */
export function progressFail() {
  _pendingCount = Math.max(0, _pendingCount - 1)
  if (_pendingCount > 0) return

  _clearTimers()
  _error.value    = true
  _progress.value = 100

  _doneTimeout = setTimeout(() => {
    _active.value   = false
    _progress.value = 0
    _error.value    = false
  }, 600)
}

/** Hard reset (e.g. after a nav abort). */
export function progressReset() {
  _pendingCount = 0
  _clearTimers()
  _active.value   = false
  _progress.value = 0
  _error.value    = false
}

/** Composable — reactive references only (read-only from components). */
export function useProgress() {
  return {
    active:   _active   as Readonly<typeof _active>,
    progress: _progress as Readonly<typeof _progress>,
    error:    _error    as Readonly<typeof _error>,
  }
}
