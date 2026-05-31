export type ImportEntityType = 'products' | 'customers' | 'suppliers'

export type ImportMode = 'create_only' | 'update_only' | 'create_update' | 'simulate'

export type ImportStatus =
  | 'draft'
  | 'analyzing'
  | 'analyzed'
  | 'awaiting_approval'
  | 'importing'
  | 'completed'
  | 'partial'
  | 'failed'
  | 'cancelled'

export type RowStatus = 'pending' | 'valid' | 'error' | 'warning' | 'imported' | 'skipped'

export type RowAction = 'create' | 'update' | 'skip'

export interface ImportRow {
  id: string
  row_number: number
  status: RowStatus
  action: RowAction | null
  mapped_data: Record<string, any> | null
  errors: Array<{ field: string; message: string }> | null
  warnings: Array<{ field: string; message: string }> | null
  entity_id: string | null
}

export interface ImportSession {
  id: string
  type: ImportEntityType
  status: ImportStatus
  mode: ImportMode
  original_filename: string

  total_rows: number
  valid_rows: number
  error_rows: number
  warning_rows: number
  imported_rows: number
  skipped_rows: number

  column_mapping: Record<string, string | null> | null
  summary: { created: number; updated: number; skipped: number; errors: number } | null
  error_message: string | null

  analyzed_at: string | null
  approved_at: string | null
  completed_at: string | null
  approved_by: string | null
  performed_by: string

  created_at: string
  updated_at: string

  rows?: ImportRow[]
}

export interface ImportHistoryResponse {
  data: ImportSession[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const ENTITY_LABELS: Record<ImportEntityType, string> = {
  products:  'Produits',
  customers: 'Clients',
  suppliers: 'Fournisseurs',
}

export const MODE_LABELS: Record<ImportMode, string> = {
  create_only:   'Création uniquement',
  update_only:   'Mise à jour uniquement',
  create_update: 'Création + Mise à jour',
  simulate:      'Simulation (aucune écriture)',
}

export const STATUS_LABELS: Record<ImportStatus, string> = {
  draft:              'Brouillon',
  analyzing:          'Analyse en cours…',
  analyzed:           'Analysé (erreurs)',
  awaiting_approval:  'En attente d\'approbation',
  importing:          'Import en cours…',
  completed:          'Terminé',
  partial:            'Partiellement importé',
  failed:             'Échoué',
  cancelled:          'Annulé',
}
