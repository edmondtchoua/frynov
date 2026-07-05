import { describe, it, expect, beforeEach, vi } from 'vitest'
import client from '@/api/client'
import { importExportService } from '@/modules/import-export/services/importExportService'

// Regression: exports/templates/reports must be fetched THROUGH axios (so the
// Bearer token is attached), not via window.open — which navigated the browser
// without the token → backend 401 → "Route [login] not defined".
describe('importExportService — authenticated downloads', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // jsdom has no object-URL / anchor download plumbing
    global.URL.createObjectURL = vi.fn(() => 'blob:mock')
    global.URL.revokeObjectURL = vi.fn()
    vi.mocked(client.get).mockResolvedValue({ data: new Blob(['x']), headers: {} } as any)
  })

  it('exportExcel fetches the file as a blob via axios', async () => {
    await importExportService.exportExcel('products')
    expect(client.get).toHaveBeenCalledWith(
      expect.stringContaining('/api/export/products'),
      expect.objectContaining({ responseType: 'blob' }),
    )
    // …and never via a raw browser navigation
    expect(String(vi.mocked(client.get).mock.calls[0][0])).toContain('format=xlsx')
  })

  it('downloadTemplate fetches the template as a blob via axios', async () => {
    await importExportService.downloadTemplate('customers')
    expect(client.get).toHaveBeenCalledWith('/api/import/template/customers', { responseType: 'blob' })
  })

  it('downloadReport fetches the report as a blob via axios', async () => {
    await importExportService.downloadReport('imp-1')
    expect(client.get).toHaveBeenCalledWith('/api/import/imp-1/report', { responseType: 'blob' })
  })
})
