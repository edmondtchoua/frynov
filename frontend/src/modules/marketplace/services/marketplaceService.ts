import client from '@/api/client'

export interface MarketplaceListing {
  id: string
  product_id: string
  variant_id: string | null
  platform: string
  external_product_id: string
  external_variant_id: string | null
  external_sku: string | null
  external_url: string | null
  sync_status: 'active' | 'closed' | 'error' | 'syncing' | 'pending_manual' | 'paused'
  last_synced_at: string | null
  last_sync_error: Record<string, unknown> | null
  sync_retry_count: number
  is_auto_close_enabled: boolean
  is_auto_reopen_enabled: boolean
  close_threshold: number
  is_price_sync_enabled: boolean
  product?: { id: string; name: string; sku: string } | null
  variant?: { id: string; name: string; sku: string } | null
  created_at: string
}

export interface MarketplaceSyncAlert {
  id: string
  listing_id: string
  severity: 'info' | 'warning' | 'error' | 'critical'
  type: string
  message: string
  context: Record<string, unknown> | null
  is_read: boolean
  requires_action: boolean
  resolved_at: string | null
  created_at: string
  listing?: { id: string; platform: string; external_product_id: string } | null
}

export const marketplaceService = {
  // ── Platforms ─────────────────────────────────────────────────────────────
  getPlatforms(): Promise<Array<{ code: string; label: string }>> {
    return client.get('/api/marketplace/platforms').then(r => r.data.data ?? [])
  },

  // ── Listings ──────────────────────────────────────────────────────────────
  listListings(params?: { platform?: string; status?: string }): Promise<{ data: MarketplaceListing[]; meta: any }> {
    return client.get('/api/marketplace/listings', { params }).then(r => r.data)
  },

  createListing(data: Partial<MarketplaceListing>): Promise<MarketplaceListing> {
    return client.post('/api/marketplace/listings', data).then(r => r.data.data)
  },

  updateListing(id: string, data: Partial<MarketplaceListing>): Promise<MarketplaceListing> {
    return client.patch(`/api/marketplace/listings/${id}`, data).then(r => r.data.data)
  },

  deleteListing(id: string): Promise<void> {
    return client.delete(`/api/marketplace/listings/${id}`).then(() => undefined)
  },

  // ── Alerts ────────────────────────────────────────────────────────────────
  listAlerts(params?: { include_read?: boolean }): Promise<{ data: MarketplaceSyncAlert[]; total?: number }> {
    return client.get('/api/marketplace/alerts', { params }).then(r => r.data)
  },

  markAlertRead(id: string): Promise<void> {
    return client.patch(`/api/marketplace/alerts/${id}/read`).then(() => undefined)
  },

  unreadCount(): Promise<number> {
    return client.get('/api/marketplace/alerts?per_page=1').then(r => r.data.total ?? 0)
  },
}
