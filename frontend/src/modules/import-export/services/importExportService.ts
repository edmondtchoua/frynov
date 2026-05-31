import client from '@/api/client'
import type { ImportEntityType, ImportHistoryResponse, ImportMode, ImportSession } from '../types'

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
    return client.delete(`/api/import/${id}`)
  },

  /** List import history. */
  history(params?: { type?: string; status?: string; page?: number }): Promise<ImportHistoryResponse> {
    return client.get('/api/import/history', { params }).then(r => r.data)
  },

  /** Download PDF report for a completed session. */
  downloadReport(id: string): void {
    window.open(`/api/import/${id}/report`, '_blank')
  },

  // ── Templates ───────────────────────────────────────────────────────────────

  downloadTemplate(type: ImportEntityType): void {
    window.open(`/api/import/template/${type}`, '_blank')
  },

  // ── Export ──────────────────────────────────────────────────────────────────

  exportExcel(type: ImportEntityType, filters?: Record<string, string>): void {
    const params = new URLSearchParams({ format: 'xlsx', ...filters })
    window.open(`/api/export/${type}?${params}`, '_blank')
  },

  exportPdf(type: ImportEntityType, filters?: Record<string, string>): void {
    const params = new URLSearchParams({ format: 'pdf', ...filters })
    window.open(`/api/export/${type}?${params}`, '_blank')
  },
}
