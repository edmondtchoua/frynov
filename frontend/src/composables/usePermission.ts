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

  return {
    hasRole, hasAnyRole,
    canManageStock, canManageCatalog, canManageOrders,
    canViewReports, canManageUsers, canVoidPayments, canApproveReturns,
    isSuperAdmin, isAdmin,
  }
}
