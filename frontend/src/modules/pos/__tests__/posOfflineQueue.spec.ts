import { describe, it, expect, beforeEach, vi } from 'vitest'
import { usePosOfflineQueue, type QueuedSale } from '@/modules/pos/composables/posOfflineQueue'

function makeSale(id: string): Omit<QueuedSale, 'id' | 'queued_at'> {
  return {
    session_id: 'sess-1',
    total_cents: 25000,
    payload: { items: [{ product_id: 'p1', quantity: 1 }], method: 'mobile_money' },
  }
}

describe('posOfflineQueue', () => {
  beforeEach(() => {
    localStorage.clear()
    usePosOfflineQueue().clear()
  })

  it('persists queued sales and exposes them reactively', () => {
    const q = usePosOfflineQueue()
    q.enqueue(makeSale('a'), 'a', '2026-07-05T10:00:00Z')
    q.enqueue(makeSale('b'), 'b', '2026-07-05T10:01:00Z')

    expect(q.queue.value).toHaveLength(2)
    expect(JSON.parse(localStorage.getItem('frynov.pos.offline_queue')!)).toHaveLength(2)
  })

  it('flushes every queued sale in order and empties the queue', async () => {
    const q = usePosOfflineQueue()
    q.enqueue(makeSale('a'), 'a', '2026-07-05T10:00:00Z')
    q.enqueue(makeSale('b'), 'b', '2026-07-05T10:01:00Z')

    const seen: string[] = []
    const sent = await q.flush(async sale => { seen.push(sale.id) })

    expect(sent).toBe(2)
    expect(seen).toEqual(['a', 'b'])       // order preserved
    expect(q.queue.value).toHaveLength(0)
  })

  it('stops at the first failure so nothing is dropped or reordered', async () => {
    const q = usePosOfflineQueue()
    q.enqueue(makeSale('a'), 'a', '2026-07-05T10:00:00Z')
    q.enqueue(makeSale('b'), 'b', '2026-07-05T10:01:00Z')

    const send = vi.fn()
      .mockResolvedValueOnce(undefined)      // 'a' succeeds
      .mockRejectedValueOnce(new Error('offline')) // 'b' fails

    const sent = await q.flush(send)

    expect(sent).toBe(1)
    expect(q.queue.value).toHaveLength(1)
    expect(q.queue.value[0].id).toBe('b')  // the failed one stays, still first
  })
})
