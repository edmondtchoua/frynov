import client from '@/api/client'
import type {
  Account, AccountingOverview, AccountingPeriod, AccountingSettings, BalanceSheet, BankReconciliation,
  Entry, EntryLineInput, FiscalYear, GeneralLedger, IncomeStatement, Invoice, InvoiceDraftLine, Journal, LettrageData, Tax, TrialBalance,
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

  /** RC-41 — clôture d'exercice : bascule le résultat sur 13 + poste le report-à-nouveau. */
  closeFiscalYear(id: string): Promise<{ fiscal_year: FiscalYear; next_year: FiscalYear; carry_forward_entry: Entry; result_minor: number }> {
    return client.post(`/api/accounting/fiscal-years/${id}/close`).then(r => r.data.data)
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

  // ── Factures (RC-30) ─────────────────────────────────────────────────────
  invoices(params?: { status?: string; customer_id?: string; kind?: string; page?: number; per_page?: number }) {
    return client.get('/api/accounting/invoices', { params }).then(r => normalizePage<Invoice>(r.data))
  },

  invoice(id: string): Promise<Invoice> {
    return client.get(`/api/accounting/invoices/${id}`).then(r => r.data.data)
  },

  createInvoice(payload: { customer_name?: string; currency?: string; due_date?: string; notes?: string; lines: InvoiceDraftLine[] }): Promise<Invoice> {
    return client.post('/api/accounting/invoices', payload).then(r => r.data.data)
  },

  issueInvoice(id: string): Promise<Invoice> {
    return client.post(`/api/accounting/invoices/${id}/issue`).then(r => r.data.data)
  },

  allocatePayment(id: string, payload: { payment_id: string; amount_minor: number }) {
    return client.post(`/api/accounting/invoices/${id}/payments`, payload).then(r => r.data.data)
  },

  /** URL du PDF (téléchargement direct — le navigateur porte le token via l'app). */
  invoicePdfUrl(id: string): string {
    return `/api/accounting/invoices/${id}/pdf`
  },

  // ── Avoirs / notes de crédit (RC-33) ──────────────────────────────────────
  creditNotes(params?: { status?: string; customer_id?: string; page?: number; per_page?: number }) {
    return client.get('/api/accounting/invoices', { params: { ...params, kind: 'credit_note' } }).then(r => normalizePage<Invoice>(r.data))
  },

  createCreditNote(invoiceId: string, lines?: InvoiceDraftLine[]): Promise<Invoice> {
    return client.post(`/api/accounting/invoices/${invoiceId}/credit-notes`, lines ? { lines } : {}).then(r => r.data.data)
  },

  issueCreditNote(id: string): Promise<Invoice> {
    return client.post(`/api/accounting/credit-notes/${id}/issue`).then(r => r.data.data)
  },

  applyCreditNote(id: string, payload: { invoice_id: string; amount_minor: number }) {
    return client.post(`/api/accounting/credit-notes/${id}/apply`, payload).then(r => r.data.data)
  },

  // ── États : balance & grand livre (RC-38) ─────────────────────────────────
  trialBalance(params?: { from?: string; to?: string }): Promise<TrialBalance> {
    return client.get('/api/accounting/reports/trial-balance', { params }).then(r => r.data.data)
  },

  generalLedger(accountId: string, params?: { from?: string; to?: string }): Promise<GeneralLedger> {
    return client.get('/api/accounting/reports/general-ledger', { params: { ...params, account_id: accountId } }).then(r => r.data.data)
  },

  // ── États financiers SYSCOHADA (RC-42) ────────────────────────────────────
  incomeStatement(params?: { from?: string; to?: string }): Promise<IncomeStatement> {
    return client.get('/api/accounting/reports/income-statement', { params }).then(r => r.data.data)
  },

  balanceSheet(params?: { from?: string; to?: string }): Promise<BalanceSheet> {
    return client.get('/api/accounting/reports/balance-sheet', { params }).then(r => r.data.data)
  },

  // ── Lettrage (RC-39) ──────────────────────────────────────────────────────
  lettrage(accountId: string, onlyOpen = false): Promise<LettrageData> {
    return client.get('/api/accounting/reports/lettrage', { params: { account_id: accountId, only_open: onlyOpen } }).then(r => r.data.data)
  },

  letterLines(accountId: string, lineIds: string[]): Promise<{ code: string; account: LettrageData }> {
    return client.post('/api/accounting/reports/lettrage', { account_id: accountId, line_ids: lineIds }).then(r => r.data.data)
  },

  unletterCode(accountId: string, code: string): Promise<{ account: LettrageData }> {
    return client.post('/api/accounting/reports/lettrage/unletter', { account_id: accountId, code }).then(r => r.data.data)
  },

  // ── Rapprochement bancaire (RC-43) ────────────────────────────────────────
  bankReconciliation(accountId: string, statementBalance?: number): Promise<BankReconciliation> {
    return client.get('/api/accounting/reports/bank-reconciliation', {
      params: { account_id: accountId, statement_balance: statementBalance ?? undefined },
    }).then(r => r.data.data)
  },

  pointBankLines(accountId: string, lineIds: string[], pointed: boolean): Promise<BankReconciliation> {
    return client.post('/api/accounting/reports/bank-reconciliation/point', { account_id: accountId, line_ids: lineIds, pointed }).then(r => r.data.data)
  },
}
