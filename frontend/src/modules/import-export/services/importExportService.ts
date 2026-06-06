import client from '@/api/client'
import type { ImportEntityType, ImportHistoryResponse, ImportMode, ImportSession } from '../types'

/**
 * Fetch a protected file THROUGH axios so the Bearer token is attached, then
 * trigger a browser download. Using window.open()/<a href> would navigate the
 * browser without the token → backend 401 → "Route [login] not defined".
 */
async function downloadFile(url: string, fallbackName: string): Promise<void> {
  const res = await client.get(url, { responseType: 'blob' })

  // Prefer the server-provided filename (Content-Disposition) when present.
  const disposition = (res.headers as Record<string, string>)?.['content-disposition']
  const match = disposition?.match(/filename\*?=(?:UTF-8'')?["']?([^"';\n]+)/i)
  const filename = match ? decodeURIComponent(match[1].trim()) : fallbackName

  const objectUrl = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = objectUrl
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(objectUrl)
}

export const importExportService = {

  // ── Import ──────────────────────────────────────────────────────────────────

  /** Upload a file and start analysis. Returns the ImportSession. */
  upload(file: File, type: ImportEntityType, mode: ImportMode): Promise<ImportSession> {
    const form = new FormData()
    form.append('file', file)
    form.append('type', type)
    form.append('mode', mode)
    return client.post('/api/import/upload', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.data ?? r.data)
  },

  /** Poll session status (including rows). */
  getSession(id: string): Promise<ImportSession> {
    return client.get(`/api/import/${id}`).then(r => r.data.data ?? r.data)
  },

  /** Update column mapping and re-analyze. */
  updateMapping(id: string, mapping: Record<string, string | null>): Promise<ImportSession> {
    return client.patch(`/api/import/${id}/mapping`, { mapping }).then(r => r.data.data ?? r.data)
  },

  /** Approve the session (mark ready for execution). */
  approve(id: string): Promise<ImportSession> {
    return client.post(`/api/import/${id}/approve`).then(r => r.data.data ?? r.data)
  },

  /** Execute the import. */
  execute(id: string): Promise<ImportSession> {
    return client.post(`/api/import/${id}/execute`).then(r => r.data.data ?? r.data)
  },

  /** Cancel / delete the session. */
  cancel(id: string): Promise<void> {
    return client.delete(`/api/import/${id}`).then(() => undefined)
  },

  /** List import history. */
  history(params?: { type?: string; status?: string; page?: number }): Promise<ImportHistoryResponse> {
    return client.get('/api/import/history', { params }).then(r => r.data)
  },

  // ── Downloads (authenticated blob — NOT window.open, which loses the token) ──

  /** Download the PDF report for a completed session. */
  downloadReport(id: string): Promise<void> {
    return downloadFile(`/api/import/${id}/report`, `rapport-import-${id}.pdf`)
  },

  /** Download an import template (XLSX) for the given entity type. */
  downloadTemplate(type: ImportEntityType): Promise<void> {
    return downloadFile(`/api/import/template/${type}`, `modele-import-${type}.xlsx`)
  },

  /** Export an entity as XLSX. */
  exportExcel(type: ImportEntityType, filters: Record<string, string> = {}): Promise<void> {
    const params = new URLSearchParams({ format: 'xlsx', ...filters })
    return downloadFile(`/api/export/${type}?${params.toString()}`, `export-${type}.xlsx`)
  },

  /** Export an entity as PDF. */
  exportPdf(type: ImportEntityType, filters: Record<string, string> = {}): Promise<void> {
    const params = new URLSearchParams({ format: 'pdf', ...filters })
    return downloadFile(`/api/export/${type}?${params.toString()}`, `export-${type}.pdf`)
  },
}
