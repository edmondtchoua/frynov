import client from '@/api/client'
import type {
  Account, AccountingOverview, AccountingPeriod, AccountingSettings,
  Entry, EntryLineInput, FiscalYear, Journal, Tax,
} from '../types'

function normalizePage<T>(d: any): { data: T[]; meta: { current_page: number; last_page: number; total: number } } {
  return {
    data: d.data ?? [],
    meta: d.meta ?? { current_page: d.current_page ?? 1, last_page: d.last_page ?? 1, total: d.total ?? 0 },
  }
}

/** RC-23 — API du référentiel comptable SYSCOHADA. */
export const accountingService = {
  overview(): Promise<AccountingOverview> {
    return client.get('/api/accounting/overview').then(r => r.data.data)
  },

  provision(): Promise<AccountingSettings> {
    return client.post('/api/accounting/provision').then(r => r.data.data)
  },

  accounts(params?: { class?: number; search?: string; page?: number; per_page?: number }): Promise<{ data: Account[]; meta: { current_page: number; last_page: number; total: number } }> {
    return client.get('/api/accounting/accounts', { params }).then(r => {
      const d = r.data
      return {
        data: d.data ?? [],
        meta: d.meta ?? { current_page: d.current_page ?? 1, last_page: d.last_page ?? 1, total: d.total ?? 0 },
      }
    })
  },

  createAccount(payload: { code: string; name: string; kind: string; parent_id?: string }): Promise<Account> {
    return client.post('/api/accounting/accounts', payload).then(r => r.data.data)
  },

  updateAccount(id: string, payload: { name?: string; is_active?: boolean }): Promise<Account> {
    return client.put(`/api/accounting/accounts/${id}`, payload).then(r => r.data.data)
  },

  journals(): Promise<Journal[]> {
    return client.get('/api/accounting/journals').then(r => r.data.data ?? [])
  },

  taxes(): Promise<Tax[]> {
    return client.get('/api/accounting/taxes').then(r => r.data.data ?? [])
  },

  createTax(payload: { code: string; name: string; rate_bp: number; is_inclusive?: boolean }): Promise<Tax> {
    return client.post('/api/accounting/taxes', payload).then(r => r.data.data)
  },

  updateTax(id: string, payload: Partial<Pick<Tax, 'name' | 'rate_bp' | 'is_inclusive' | 'is_active'>>): Promise<Tax> {
    return client.put(`/api/accounting/taxes/${id}`, payload).then(r => r.data.data)
  },

  settings(): Promise<AccountingSettings | null> {
    return client.get('/api/accounting/settings').then(r => r.data.data ?? null)
  },

  updateSettings(payload: Partial<Pick<AccountingSettings, 'fiscal_year_start_month' | 'auto_post' | 'default_accounts' | 'numbering_rules'>>): Promise<AccountingSettings> {
    return client.put('/api/accounting/settings', payload).then(r => r.data.data)
  },

  fiscalYears(): Promise<FiscalYear[]> {
    return client.get('/api/accounting/fiscal-years').then(r => r.data.data ?? [])
  },

  lockPeriod(id: string, reason?: string): Promise<AccountingPeriod> {
    return client.post(`/api/accounting/periods/${id}/lock`, { reason }).then(r => r.data.data)
  },

  unlockPeriod(id: string, reason: string): Promise<AccountingPeriod> {
    return client.post(`/api/accounting/periods/${id}/unlock`, { reason }).then(r => r.data.data)
  },

  // ── Écritures (RC-25/26) ─────────────────────────────────────────────────
  entries(params?: { journal_id?: string; status?: string; source_type?: string; page?: number; per_page?: number }) {
    return client.get('/api/accounting/entries', { params }).then(r => normalizePage<Entry>(r.data))
  },

  entry(id: string): Promise<Entry> {
    return client.get(`/api/accounting/entries/${id}`).then(r => r.data.data)
  },

  createEntry(payload: { journal_id: string; entry_date: string; label: string; lines: EntryLineInput[] }): Promise<Entry> {
    return client.post('/api/accounting/entries', payload).then(r => r.data.data)
  },

  postEntry(id: string): Promise<Entry> {
    return client.post(`/api/accounting/entries/${id}/post`).then(r => r.data.data)
  },

  reverseEntry(id: string, reason?: string): Promise<Entry> {
    return client.post(`/api/accounting/entries/${id}/reverse`, { reason }).then(r => r.data.data)
  },
}
