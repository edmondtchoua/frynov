import { createRouter, createWebHistory } from 'vue-router'
import { setupGuards } from './guards'

const router = createRouter({
  // createWebHistory() without base URL prevents the //path double-slash bug
  // in Vue Router 4 when BASE_URL = '/'
  history: createWebHistory(),
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

    // ── Portail client digital (RC-6C — public : jeton / lien magique / par email) ──
    {
      path: '/portal',
      name: 'portal',
      component: () => import('@/pages/DigitalPortalView.vue'),
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
    {
      // RC-10 F-3 — réinitialisation de mot de passe par code email.
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/modules/auth/views/ForgotPasswordView.vue'),
      meta: { layout: 'auth', public: true },
    },
    {
      // RC-12 F-5 — acceptation d'invitation (activation de compte par code email).
      path: '/accept-invitation',
      name: 'accept-invitation',
      component: () => import('@/modules/auth/views/AcceptInvitationView.vue'),
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

    // Contextual "access unavailable" page (module disabled / permission / quota)
    {
      path: '/unavailable',
      name: 'access-unavailable',
      component: () => import('@/pages/AccessUnavailableView.vue'),
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
          // ── Detail / consultation page (Sprint 17) ──
          path: 'products/:id',
          name: 'catalog.products.show',
          component: () => import('@/modules/catalog/views/ProductShowPage.vue'),
        },
        {
          // ── Edit form — separate from show page ──
          path: 'products/:id/edit',
          name: 'catalog.products.edit',
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
        {
          path: 'attributes',
          name: 'catalog.attributes',
          component: () => import('@/modules/catalog/views/AttributesView.vue'),
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
          path: 'adjustments',
          name: 'inventory.adjustments',
          component: () => import('@/modules/inventory/views/StockAdjustmentView.vue'),
        },
        {
          path: 'fiscal-periods',
          name: 'inventory.fiscal-periods',
          component: () => import('@/modules/inventory/views/FiscalPeriodView.vue'),
        },
        {
          path: 'batch-delivery',
          name: 'inventory.batch-delivery',
          component: () => import('@/modules/inventory/views/BatchDeliveryView.vue'),
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

    // ── Point de vente (POS) ──────────────────────────────────────────────
    {
      path: '/pos',
      name: 'pos',
      component: () => import('@/modules/pos/views/PosView.vue'),
      meta: { layout: 'app' },
    },
    {
      // Caisse Desktop — pavé de paiement mixte, mouvements, remboursement, raccourcis (RC-16).
      path: '/pos/desktop',
      name: 'pos.desktop',
      component: () => import('@/modules/pos/views/PosDesktopView.vue'),
      meta: { layout: 'app' },
    },
    {
      // POS mobile — tactile, Mobile Money, file hors-ligne (RC-16).
      path: '/pos/mobile',
      name: 'pos.mobile',
      component: () => import('@/modules/pos/views/PosMobileView.vue'),
      meta: { layout: 'app' },
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
        {
          // RC-17 (M-2) — analyse d'inventaire : ABC, KPIs (DSI/rotation/fill rate), réconciliation.
          path: 'insights',
          name: 'reports.insights',
          component: () => import('@/modules/reports/views/InventoryInsightsView.vue'),
        },
      ],
    },

    // ── Comptabilité (RC-23 — référentiel SYSCOHADA) ──────────────────────
    {
      path: '/accounting',
      meta: { layout: 'app' },
      children: [
        { path: '', redirect: { name: 'accounting.invoices' } },
        {
          path: 'invoices',
          name: 'accounting.invoices',
          component: () => import('@/modules/accounting/views/InvoicesView.vue'),
        },
        {
          path: 'credit-notes',
          name: 'accounting.creditNotes',
          component: () => import('@/modules/accounting/views/CreditNotesView.vue'),
        },
        {
          path: 'entries',
          name: 'accounting.entries',
          component: () => import('@/modules/accounting/views/EntriesView.vue'),
        },
        {
          path: 'chart',
          name: 'accounting.chart',
          component: () => import('@/modules/accounting/views/ChartOfAccountsView.vue'),
        },
        {
          path: 'taxes',
          name: 'accounting.taxes',
          component: () => import('@/modules/accounting/views/TaxesView.vue'),
        },
        {
          path: 'periods',
          name: 'accounting.periods',
          component: () => import('@/modules/accounting/views/PeriodsView.vue'),
        },
        {
          path: 'settings',
          name: 'accounting.settings',
          component: () => import('@/modules/accounting/views/AccountingSettingsView.vue'),
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
          path: 'country-rules',
          name: 'admin.country-rules',
          component: () => import('@/modules/admin/views/CountryRuleListView.vue'),
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
