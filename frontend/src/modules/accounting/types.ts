/** RC-23 — types du module Comptabilité (référentiel SYSCOHADA). */

export interface AccountClass {
  code: number
  name: string
  type: 'bilan' | 'gestion'
}

export interface Account {
  id: string
  class_code: number
  code: string
  name: string
  parent_id: string | null
  kind: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
  is_auxiliary: boolean
  is_system: boolean
  is_active: boolean
}

export interface Journal {
  id: string
  code: string
  name: string
  type: string
  sequence_prefix: string
  is_system: boolean
  is_active: boolean
}

export interface Tax {
  id: string
  code: string
  name: string
  rate_bp: number
  is_inclusive: boolean
  country: string | null
  is_active: boolean
}

export interface AccountingSettings {
  id: string
  country: string | null
  currency: string
  fiscal_year_start_month: number
  numbering_rules: Record<string, string> | null
  default_accounts: Record<string, string> | null
  auto_post: boolean
}

export interface AccountingPeriod {
  id: string
  label: string
  starts_on: string
  ends_on: string
  status: 'open' | 'locked' | 'closed'
  lock_reason: string | null
}

export interface FiscalYear {
  id: string
  label: string
  starts_on: string
  ends_on: string
  status: 'open' | 'closing' | 'closed'
  periods: AccountingPeriod[]
}

export interface AccountingOverview {
  provisioned: boolean
  accounts: number
  journals: number
  taxes: number
  classes: AccountClass[]
}

/** RC-25 — ligne d'écriture (lecture). */
export interface EntryLine {
  id: string
  account_id: string
  label: string | null
  debit_minor: number
  credit_minor: number
  account?: { id: string; code: string; name: string }
}

export interface Entry {
  id: string
  number: string | null
  entry_date: string
  label: string
  currency: string
  status: 'draft' | 'posted' | 'reversed'
  source_type: string | null
  source_id: string | null
  rule_code: string | null
  reversal_of_id: string | null
  reversed_by_id: string | null
  journal?: { id: string; code: string; name: string }
  lines: EntryLine[]
}

/** Ligne saisie (création). */
export interface EntryLineInput {
  account_id: string
  label?: string
  debit_minor: number
  credit_minor: number
}

/** RC-30 — factures. */
export interface InvoiceLine {
  id: string
  label: string
  quantity: number
  unit_price_minor: number
  discount_bp: number
  tax_id: string | null
  subtotal_minor: number
  tax_minor: number
  total_minor: number
}

export interface Invoice {
  id: string
  number: string | null
  kind: string
  customer_id: string | null
  customer_name: string | null
  order_id: string | null
  currency: string
  issue_date: string | null
  due_date: string | null
  status: 'draft' | 'issued' | 'partially_paid' | 'paid' | 'cancelled'
  credit_note_of_id?: string | null
  subtotal_minor: number
  tax_total_minor: number
  total_minor: number
  paid_minor: number
  credited_minor?: number
  entry_id: string | null
  lines: InvoiceLine[]
}

export interface InvoiceDraftLine {
  label: string
  quantity: number
  unit_price_minor: number
  discount_bp?: number
  tax_id?: string | null
}

/** RC-38 — balance générale. */
export interface TrialBalanceRow {
  account_id: string
  code: string
  name: string
  kind: string
  opening_minor: number
  debit_minor: number
  credit_minor: number
  closing_minor: number
}

export interface TrialBalance {
  rows: TrialBalanceRow[]
  totals: { debit_minor: number; credit_minor: number; closing_debit_minor: number; closing_credit_minor: number }
  from: string | null
  to: string
}

/** RC-38 — grand livre d'un compte. */
export interface LedgerLine {
  date: string
  number: string | null
  journal: string
  label: string | null
  debit_minor: number
  credit_minor: number
  running_minor: number
}

export interface GeneralLedger {
  account: { id: string; code: string; name: string; kind: string }
  opening_minor: number
  debit_total_minor: number
  credit_total_minor: number
  closing_minor: number
  lines: LedgerLine[]
}

/** RC-39 — lettrage d'un compte de tiers. */
export interface LettrageLine {
  id: string
  date: string
  number: string | null
  journal: string
  label: string | null
  debit_minor: number
  credit_minor: number
  lettrage_code: string | null
}

export interface LettrageData {
  account: { id: string; code: string; name: string }
  lines: LettrageLine[]
  summary: {
    lettered_debit_minor: number
    lettered_credit_minor: number
    open_debit_minor: number
    open_credit_minor: number
    open_balance_minor: number
  }
}

/** RC-42 — états financiers SYSCOHADA. */
export interface StatementLine {
  code: string
  name: string
  class: number
  amount_minor: number
}

export interface IncomeStatement {
  charges: StatementLine[]
  produits: StatementLine[]
  total_charges_minor: number
  total_produits_minor: number
  result_minor: number
  from: string | null
  to: string
}

export interface BalanceSheet {
  actif: StatementLine[]
  passif: StatementLine[]
  total_actif_minor: number
  total_passif_minor: number
  result_minor: number
  balanced: boolean
  from: string | null
  to: string
}
