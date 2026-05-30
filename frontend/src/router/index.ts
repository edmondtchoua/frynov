import { createRouter, createWebHistory } from 'vue-router'
import { setupGuards } from './guards'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Auth ──────────────────────────────────────────────────────────────
    {
      path: '/login',
      name: 'login',
      component: () => import('@/modules/auth/views/LoginView.vue'),
      meta: { layout: 'auth', public: true },
    },

    // ── App ───────────────────────────────────────────────────────────────
    {
      path: '/',
      redirect: '/dashboard',
    },
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
          path: 'create',
          name: 'orders.create',
          component: () => import('@/modules/orders/views/OrderCreateView.vue'),
        },
        {
          path: ':id',
          name: 'orders.show',
          component: () => import('@/modules/orders/views/OrderDetailView.vue'),
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
