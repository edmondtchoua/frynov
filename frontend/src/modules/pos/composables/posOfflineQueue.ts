import { ref } from 'vue'
import type { PosCheckoutPayload } from '../types'

/**
 * Offline sale queue for the mobile till.
 *
 * On flaky connectivity (common on mobile in the field), a completed sale is
 * persisted to localStorage instead of being lost. When the network returns, the
 * queue is flushed in order. Each entry is keyed to its session so a sale is never
 * replayed against the wrong (or closed) drawer.
 *
 * NOTE: queued sales have NOT touched the server — stock is only decremented on a
 * successful flush. The UI must make the "pending" state visible.
 */
const STORAGE_KEY = 'frynov.pos.offline_queue'

export interface QueuedSale {
  id: string            // client-generated id (dedupe)
  session_id: string
  payload: PosCheckoutPayload
  total_cents: number
  queued_at: string
}

function read(): QueuedSale[] {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]')
  } catch {
    return []
  }
}

function write(items: QueuedSale[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
}

/** Shared reactive view of the queue, kept in sync with localStorage. */
const queue = ref<QueuedSale[]>(read())

function refresh() {
  queue.value = read()
}

export function usePosOfflineQueue() {
  function enqueue(sale: Omit<QueuedSale, 'id' | 'queued_at'>, id: string, queuedAt: string) {
    const items = read()
    items.push({ ...sale, id, queued_at: queuedAt })
    write(items)
    refresh()
  }

  function remove(id: string) {
    write(read().filter(s => s.id !== id))
    refresh()
  }

  function clear() {
    write([])
    refresh()
  }

  /**
   * Flush every queued sale through `send`. Stops on the first failure (likely the
   * network is still down) so ordering is preserved and nothing is dropped.
   * Returns the number successfully sent.
   */
  async function flush(send: (sale: QueuedSale) => Promise<void>): Promise<number> {
    let sent = 0
    for (const sale of read()) {
      try {
        await send(sale)
        remove(sale.id)
        sent++
      } catch {
        break
      }
    }
    return sent
  }

  return { queue, enqueue, remove, clear, flush, refresh }
}
