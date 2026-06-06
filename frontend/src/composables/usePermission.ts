import { computed } from "vue"
import { useAuthStore } from "@/stores/auth"

export function usePermission() {
  const auth = useAuthStore()
  const roles = computed(() => auth.user?.roles ?? [])

  function hasRole(role) {
    return roles.value.includes(role)
  }

  function hasAnyRole(roleList) {
    return roleList.some(r => roles.value.includes(r))
  }

  const canManageStock    = computed(() => hasAnyRole(["admin", "manager"]))
  const canManageCatalog  = computed(() => hasAnyRole(["admin", "manager"]))
  const canManageOrders   = computed(() => hasAnyRole(["admin", "manager"]))
  const canViewReports    = computed(() => hasAnyRole(["admin", "manager"]))
  const canManageUsers    = computed(() => hasAnyRole(["admin", "manager"]))
  const canVoidPayments   = computed(() => hasAnyRole(["admin", "manager"]))
  const canApproveReturns = computed(() => hasAnyRole(["admin", "manager"]))
  const isSuperAdmin      = computed(() => auth.user?.is_super_admin === true)
  const isAdmin           = computed(() => hasRole("admin") || isSuperAdmin.value)
  const isManagerOrAbove  = computed(() => hasAnyRole(["admin", "manager"]) || isSuperAdmin.value)

  // Tab visibility per module — undefined means "show all" (manager+)
  // Restricted roles (agent, cashier, commercial) see only the listed paths.

  const catalogTabs = computed<string[] | undefined>(() => {
    if (isManagerOrAbove.value) return undefined // all tabs
    // agents/cashier/commercial: products list only
    return ['/catalog']
  })

  const inventoryTabs = computed<string[] | undefined>(() => {
    if (isManagerOrAbove.value) return undefined
    // agents see stock only; no alerts/warehouses/transfers/fiscal
    return ['/inventory']
  })

  const salesTabs = computed<string[] | undefined>(() => {
    if (isManagerOrAbove.value) return undefined
    // cashier/commercial: orders + payments; no returns/deliveries management
    return ['/orders', '/payments']
  })

  const reportsTabs = computed<string[] | undefined>(() => {
    if (isManagerOrAbove.value) return undefined
    // non-managers have no reports access (guard at route level too)
    return []
  })

  return {
    hasRole, hasAnyRole,
    canManageStock, canManageCatalog, canManageOrders,
    canViewReports, canManageUsers, canVoidPayments, canApproveReturns,
    isSuperAdmin, isAdmin, isManagerOrAbove,
    catalogTabs, inventoryTabs, salesTabs, reportsTabs,
  }
}
