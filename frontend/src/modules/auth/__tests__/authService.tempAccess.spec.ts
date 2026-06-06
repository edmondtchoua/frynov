import { describe, it, expect, vi, afterEach } from 'vitest'
import client from '@/api/client'
import { authService } from '@/modules/auth/services/authService'

// RBAC Phase C: managers grant a time-boxed role; it auto-expires server-side.
describe('authService temporary access', () => {
  afterEach(() => vi.clearAllMocks())

  it('grants a temporary role with an expiry', async () => {
    vi.mocked(client.post).mockResolvedValue({ data: { message: 'ok' } } as any)

    await authService.grantTemporaryAccess('u1', { role: 'manager', expires_at: '2026-07-01T00:00:00.000Z' })

    expect(client.post).toHaveBeenCalledWith('/api/workspace/users/u1/temporary-access', {
      role: 'manager',
      expires_at: '2026-07-01T00:00:00.000Z',
    })
  })

  it('revokes a temporary grant early', async () => {
    vi.mocked(client.delete).mockResolvedValue({ data: {} } as any)

    await authService.revokeTemporaryAccess('g1')

    expect(client.delete).toHaveBeenCalledWith('/api/workspace/temporary-access/g1')
  })
})
