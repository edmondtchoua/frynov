import { createRouter, createWebHistory } from 'vue-router'
import { setupGuards } from './guards'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (to, _from, savedPosition) => {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0 }
  },
  routes: [

    // ── Public / Landing ───────────────────────────────────────────────────
    {
      path: '/',
      name: 'landing',
      component: () => import('@/pages/LandingView.vue'),
      meta: { public: true },
    },

    // ── Auth ──────────────────────────────────────────────────────────────
    {
      path: '/login',
      name: 'login',
      component: () => import('@/modules/auth/views/LoginView.vue'),
      meta: { layout: 'auth', public: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/modules/auth/views/RegisterView.vue'),
      meta: { layout: 'auth', public: true },
    },

    // ── Onboarding ────────────────────────────────────────────────────────
    {
      path: '/onboarding',
      name: 'onboarding',
      component: () => import('@/modules/onboarding/views/OnboardingView.vue'),
      meta: { public: true },
    },

    // ── App ───────────────────────────────────────────────────────────────
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/modules/dashboard/views/DashboardView.vue'),
      meta: { layout: 'app' },
    },

    // ── Catalog ───────────────────────────────────────────────────────────
    {
      path: '/catalog',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'catalog.products',
          component: () => import('@/modules/catalog/views/ProductListView.vue'),
        },
        {
          path: 'products/create',
          name: 'catalog.products.create',
          component: () => import('@/modules/catalog/views/ProductFormView.vue'),
        },
        {
          path: 'products/:id',
          name: 'catalog.products.show',
          component: () => import('@/modules/catalog/views/ProductFormView.vue'),
        },
        {
          path: 'categories',
          name: 'catalog.categories',
          component: () => import('@/modules/catalog/views/CategoryListView.vue'),
        },
        {
          path: 'variants',
          name: 'catalog.variants',
          component: () => import('@/modules/catalog/views/VariantsView.vue'),
        },
        {
          path: 'labels',
          name: 'catalog.labels',
          component: () => import('@/modules/catalog/views/LabelPrintView.vue'),
        },
      ],
    },

    // ── Inventory ─────────────────────────────────────────────────────────
    {
      path: '/inventory',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'inventory.stock',
          component: () => import('@/modules/inventory/views/StockListView.vue'),
        },
        {
          path: 'alerts',
          name: 'inventory.alerts',
          component: () => import('@/modules/inventory/views/StockAlertsView.vue'),
        },
        {
          path: 'movements/:productId',
          name: 'inventory.movements',
          component: () => import('@/modules/inventory/views/MovementHistoryView.vue'),
        },
        {
          path: 'warehouses',
          name: 'inventory.warehouses',
          component: () => import('@/modules/inventory/views/WarehouseView.vue'),
        },
        {
          path: 'transfers',
          name: 'inventory.transfers',
          component: () => import('@/modules/inventory/views/StockTransferView.vue'),
        },
        {
          path: 'fiscal-periods',
          name: 'inventory.fiscal-periods',
          component: () => import('@/modules/inventory/views/FiscalPeriodView.vue'),
        },
      ],
    },

    // ── Marketplace ───────────────────────────────────────────────────────
    {
      path: '/marketplace',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'marketplace.listings',
          component: () => import('@/modules/marketplace/views/MarketplaceListingsView.vue'),
        },
      ],
    },

    // ── Orders ────────────────────────────────────────────────────────────
    {
      path: '/orders',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'orders.list',
          component: () => import('@/modules/orders/views/OrderListView.vue'),
        },
        {
          path: 'new',
          name: 'orders.create',
          component: () => import('@/modules/orders/views/OrderCreateView.vue'),
        },
        {
          path: 'returns',
          name: 'orders.returns',
          component: () => import('@/modules/orders/views/ReturnsView.vue'),
        },
        {
          path: ':id',
          name: 'orders.show',
          component: () => import('@/modules/orders/views/OrderDetailView.vue'),
        },
      ],
    },

    // ── Billing ───────────────────────────────────────────────────────────
    {
      path: '/billing',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'billing',
          component: () => import('@/modules/billing/views/BillingView.vue'),
        },
        {
          path: 'upgrade',
          name: 'billing.upgrade',
          component: () => import('@/modules/billing/views/UpgradeView.vue'),
        },
      ],
    },

    // ── Customers ─────────────────────────────────────────────────────────
    {
      path: '/customers',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'customers.list',
          component: () => import('@/modules/customers/views/CustomerListView.vue'),
        },
        {
          path: ':id',
          name: 'customers.show',
          component: () => import('@/modules/customers/views/CustomerDetailView.vue'),
        },
      ],
    },

    // ── Payments ──────────────────────────────────────────────────────────
    {
      path: '/payments',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'payments.list',
          component: () => import('@/modules/payments/views/PaymentListView.vue'),
        },
      ],
    },

    // ── Deliveries ────────────────────────────────────────────────────────
    {
      path: '/deliveries',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'deliveries.list',
          component: () => import('@/modules/deliveries/views/DeliveryListView.vue'),
        },
      ],
    },

    // ── Suppliers ─────────────────────────────────────────────────────────
    {
      path: '/suppliers',
      meta: { layout: 'app' },
      children: [
        {
          path: '',
          name: 'suppliers.list',
          component: () => import('@/modules/suppliers/views/SupplierListView.vue'),
        },
        {
          path: ':id',
          name: 'suppliers.show',
          component: () => import('@/modules/suppliers/views/SupplierDetailView.vue'),
          meta: { layout: 'app', title: 'Fournisseur' },
        },
      ],
    },

    // ── Import / Export ───────────────────────────────────────────────────
    {
      path: '/import',
      meta: { layout: 'app' },
      children: [
        { path: '', redirect: { name: 'import.history' } },
        {
          path: 'history',
          name: 'import.history',
          component: () => import('@/modules/import-export/views/ImportHistoryView.vue'),
        },
        {
          path: 'new',
          name: 'import.wizard',
          component: () => import('@/modules/import-export/views/ImportWizardView.vue'),
        },
      ],
    },

    // ── Reports ───────────────────────────────────────────────────────────────
    {
      path: '/reports',
      meta: { layout: 'app' },
      children: [
        { path: '', redirect: { name: 'reports.sales' } },
        {
          path: 'sales',
          name: 'reports.sales',
          component: () => import('@/modules/reports/views/SalesReportView.vue'),
        },
        {
          path: 'stock',
          name: 'reports.stock',
          component: () => import('@/modules/reports/views/StockReportView.vue'),
        },
      ],
    },

    // ── Settings ──────────────────────────────────────────────────────────
    {
      path: '/settings',
      name: 'settings',
      component: () => import('@/modules/settings/views/SettingsView.vue'),
      meta: { layout: 'app' },
    },

    // ── Profile (all authenticated users, incl. super-admin) ──────────────
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/modules/auth/views/ProfileView.vue'),
      meta: { layout: 'app' },
    },

    // ── Admin back-office ─────────────────────────────────────────────────
    {
      path: '/admin',
      meta: { layout: 'admin', requiresSuperAdmin: true },
      children: [
        {
          path: '',
          name: 'admin.dashboard',
          component: () => import('@/modules/admin/views/AdminDashboardView.vue'),
        },
        {
          path: 'tenants',
          name: 'admin.tenants',
          component: () => import('@/modules/admin/views/TenantListView.vue'),
        },
        {
          path: 'tenants/:id',
          name: 'admin.tenants.detail',
          component: () => import('@/modules/admin/views/TenantDetailView.vue'),
        },
        {
          path: 'modules',
          name: 'admin.modules',
          component: () => import('@/modules/admin/views/ModuleListView.vue'),
        },
        {
          path: 'plans',
          name: 'admin.plans',
          component: () => import('@/modules/admin/views/PlanListView.vue'),
        },
        {
          path: 'promotions',
          name: 'admin.promotions',
          component: () => import('@/modules/admin/views/PromotionListView.vue'),
        },
        {
          path: 'manual-payments',
          name: 'admin.manual-payments',
          component: () => import('@/modules/admin/views/ManualPaymentView.vue'),
        },
        {
          path: 'audit',
          name: 'admin.audit',
          component: () => import('@/modules/admin/views/AuditLogView.vue'),
        },
      ],
    },

    // ── 404 ───────────────────────────────────────────────────────────────
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/shared/views/NotFoundView.vue'),
      meta: { layout: 'app', public: true },
    },
  ],
})

setupGuards(router)

export default router
