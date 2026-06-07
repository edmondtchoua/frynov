import { describe, it, expect } from 'vitest'
import { reactive, watch, defineComponent } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useUrlFilters } from '@/composables/useUrlFilters'

function makeHarness() {
  const filters = reactive({ search: '', status: '', page: 1 })
  const Comp = defineComponent({
    template: '<div/>',
    setup() {
      const { hydrate, push } = useUrlFilters(filters, { defaults: { page: 1 } })
      hydrate()
      watch(filters, push, { deep: true })
      return {}
    },
  })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: Comp }, { path: '/list', component: Comp }],
  })
  return { filters, Comp, router }
}

describe('useUrlFilters', () => {
  it('hydrates filters from the URL query (coercing numbers)', async () => {
    const { filters, Comp, router } = makeHarness()
    router.push('/list?search=abc&status=active&page=3')
    await router.isReady()
    mount(Comp, { global: { plugins: [router] } })
    await flushPromises()

    expect(filters.search).toBe('abc')
    expect(filters.status).toBe('active')
    expect(filters.page).toBe(3)            // string query coerced to number
  })

  it('mirrors filter changes back to the URL, omitting empties and defaults', async () => {
    const { filters, Comp, router } = makeHarness()
    router.push('/list')
    await router.isReady()
    mount(Comp, { global: { plugins: [router] } })
    await flushPromises()

    filters.search = 'shoes'
    filters.page = 1                         // equals the default → must be omitted
    await flushPromises()

    expect(router.currentRoute.value.query.search).toBe('shoes')
    expect(router.currentRoute.value.query.page).toBeUndefined()    // default omitted
    expect(router.currentRoute.value.query.status).toBeUndefined()  // empty omitted
  })

  it('ignores unknown query keys not present in the filter object', async () => {
    const { filters, Comp, router } = makeHarness()
    router.push('/list?search=x&foo=bar')
    await router.isReady()
    mount(Comp, { global: { plugins: [router] } })
    await flushPromises()

    expect(filters.search).toBe('x')
    expect((filters as Record<string, unknown>).foo).toBeUndefined()
  })
})
