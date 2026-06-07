import type { ErpModule } from '@/modules/auth/types'

export function moduleRouteTarget(mod: Pick<ErpModule, 'tenant_active' | 'route_prefix'>): string {
  if (!mod.tenant_active || !mod.route_prefix) {
    return '#'
  }

  const routePrefix = mod.route_prefix.trim().replace(/^\/+/, '')

  return routePrefix ? `/${routePrefix}` : '#'
}
