import { describe, expect, it } from 'vitest'
import { moduleRouteTarget } from '../route'

describe('moduleRouteTarget', () => {
  it('normalizes route prefixes without creating double-slash locations', () => {
    expect(moduleRouteTarget({ tenant_active: true, route_prefix: '/dashboard' })).toBe('/dashboard')
    expect(moduleRouteTarget({ tenant_active: true, route_prefix: 'catalog' })).toBe('/catalog')
  })

  it('disables links for inactive modules or empty route prefixes', () => {
    expect(moduleRouteTarget({ tenant_active: false, route_prefix: '/orders' })).toBe('#')
    expect(moduleRouteTarget({ tenant_active: true, route_prefix: '' })).toBe('#')
  })
})
