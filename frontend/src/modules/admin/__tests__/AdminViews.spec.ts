import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setupManagerAuth } from '@/test-utils/setupAuth'

// The 8 admin back-office views all load through adminService — mock it once.
const { adminService } = vi.hoisted(() => ({
  adminService: {
    getDashboard: vi.fn(), getTenants: vi.fn(), getTenant: vi.fn(),
    getModules: vi.fn(), getPlans: vi.fn(), getAuditLogs: vi.fn(),
    getManualPayments: vi.fn(), getPromotions: vi.fn(), getTenantModules: vi.fn(),
  },
}))
vi.mock('@/modules/admin/services/adminService', () => ({ adminService }))

import AdminDashboardView from '@/modules/admin/views/AdminDashboardView.vue'
import TenantListView     from '@/modules/admin/views/TenantListView.vue'
import TenantDetailView   from '@/modules/admin/views/TenantDetailView.vue'
import ModuleListView     from '@/modules/admin/views/ModuleListView.vue'
import PlanListView       from '@/modules/admin/views/PlanListView.vue'
import PromotionListView  from '@/modules/admin/views/PromotionListView.vue'
import ManualPaymentView  from '@/modules/admin/views/ManualPaymentView.vue'
import AuditLogView       from '@/modules/admin/views/AuditLogView.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin', name: 'admin.dashboard', component: { template: '<div/>' } },
    { path: '/admin/tenants', name: 'admin.tenants', component: { template: '<div/>' } },
    { path: '/admin/tenants/:id', name: 'admin.tenants.detail', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

async function mountView(view: any) {
  const w = mount(view, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

const page = (rows: any[]) => ({ data: rows, meta: { current_page: 1, last_page: 1, per_page: 20, total: rows.length } })

beforeEach(() => vi.clearAllMocks())

describe('Admin back-office — smoke', () => {
  it('AdminDashboardView renders platform KPIs', async () => {
    adminService.getDashboard.mockResolvedValue({
      overview: { tenants: 12, active_tenants: 9, suspended_tenants: 1, total_users: 40, total_modules: 10, total_plans: 3 },
      subscriptions: {}, by_plan: [], recent_tenants: [], recent_logs: [],
    })
    const w = await mountView(AdminDashboardView)
    expect(w.text()).toContain('12')   // total tenants
    expect(adminService.getDashboard).toHaveBeenCalled()
  })

  it('TenantListView lists tenants', async () => {
    adminService.getTenants.mockResolvedValue(page([
      { id: 't1', name: 'Boutique Dakar', slug: 'boutique-dakar', status: 'active', plan: 'pro', subscription_status: 'active', created_at: '2026-05-01T00:00:00Z' },
    ]))
    const w = await mountView(TenantListView)
    expect(w.text()).toContain('Boutique Dakar')
  })

  it('TenantDetailView renders a tenant', async () => {
    await router.push('/admin/tenants/t1')
    adminService.getTenant.mockResolvedValue({
      tenant: {
        id: 't1', name: 'Boutique Dakar', slug: 'boutique-dakar', status: 'active', plan: 'pro',
        subscription_status: 'active', created_at: '2026-05-01T00:00:00Z',
        users: [{ id: 'u1', name: 'Awa', email: 'awa@x.sn', is_super_admin: false }],
      },
      subscription: { status: 'active', plan_code: 'pro' },
    })
    adminService.getPlans.mockResolvedValue([])
    adminService.getTenantModules.mockResolvedValue([])
    const w = await mountView(TenantDetailView)
    expect(w.text()).toContain('Boutique Dakar')
  })

  it('ModuleListView lists modules', async () => {
    adminService.getModules.mockResolvedValue([
      { id: 'm1', code: 'catalog', name: 'Catalogue', category: 'core', status: 'active', is_core: true, is_visible: true, sort_order: 1, total_activations: 5 },
    ])
    const w = await mountView(ModuleListView)
    expect(w.text()).toContain('Catalogue')
  })

  it('PlanListView lists plans', async () => {
    adminService.getPlans.mockResolvedValue([
      { id: 'p1', code: 'pro', name: 'Pro', description: '', price_monthly_cents: 1500000, price_yearly_cents: 15000000, currency: 'XOF', max_users: 10, max_products: 5000, max_monthly_orders: 2000, trial_days: 14, features: [], is_active: true, is_public: true },
    ])
    const w = await mountView(PlanListView)
    expect(w.text()).toContain('Pro')
  })

  it('PromotionListView lists promotions', async () => {
    adminService.getPromotions.mockResolvedValue(page([
      { id: 'pr1', code: 'WELCOME10', discount_type: 'percent', discount_value: 10, current_uses: 0, is_active: true, created_at: '2026-05-01T00:00:00Z' },
    ]))
    const w = await mountView(PromotionListView)
    expect(w.text()).toContain('WELCOME10')
  })

  it('ManualPaymentView lists manual payments', async () => {
    adminService.getManualPayments.mockResolvedValue(page([
      { id: 'mp1', tenant_id: 't1', tenant_name: 'Boutique Dakar', plan_code: 'pro', amount_cents: 1500000, currency: 'XOF', payment_method: 'bank_transfer', status: 'pending', created_at: '2026-05-01T00:00:00Z' },
    ]))
    const w = await mountView(ManualPaymentView)
    expect(w.text()).toContain('Boutique Dakar')
  })

  it('AuditLogView lists audit entries', async () => {
    adminService.getAuditLogs.mockResolvedValue(page([
      { id: 'a1', user_id: 'u1', tenant_id: 't1', action: 'tenant.suspended', subject_type: 'tenant', subject_id: 't1', ip_address: '1.2.3.4', created_at: '2026-05-01T00:00:00Z', user: { id: 'u1', name: 'Admin', email: 'a@x.com' } },
    ]))
    const w = await mountView(AuditLogView)
    expect(w.text()).toContain('tenant.suspended')
  })
})
