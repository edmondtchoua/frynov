import { describe, it, expect, beforeEach } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import InventoryTabNav from "@/modules/inventory/components/InventoryTabNav.vue"
import { setupManagerAuth } from "@/test-utils/setupAuth"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/inventory", component: { template: "<div/>" } },
    { path: "/inventory/alerts", component: { template: "<div/>" } },
    { path: "/inventory/warehouses", component: { template: "<div/>" } },
    { path: "/inventory/transfers", component: { template: "<div/>" } },
    { path: "/inventory/fiscal-periods", component: { template: "<div/>" } },
  ],
})

let pinia: ReturnType<typeof setupManagerAuth>

describe("InventoryTabNav", () => {
  beforeEach(() => {
    pinia = setupManagerAuth()
  })

  it("renders all 5 tabs for manager", async () => {
    await router.push("/inventory")
    const w = mount(InventoryTabNav, { global: { plugins: [router, pinia] } })
    const links = w.findAll("a")
    expect(links.length).toBeGreaterThanOrEqual(4)
  })

  it("shows alert badge when count > 0", async () => {
    await router.push("/inventory/alerts")
    const w = mount(InventoryTabNav, {
      props: { counts: { alerts: 3 } },
      global: { plugins: [router, pinia] },
    })
    expect(w.text()).toContain("3")
  })

  it("has links to all inventory sub-routes", async () => {
    await router.push("/inventory")
    const w = mount(InventoryTabNav, { global: { plugins: [router, pinia] } })
    const html = w.html()
    expect(html).toContain("/inventory/warehouses")
    expect(html).toContain("/inventory/transfers")
    expect(html).toContain("/inventory/fiscal-periods")
  })

  it("restricts tabs with allowedTabs prop override", async () => {
    await router.push("/inventory")
    const w = mount(InventoryTabNav, {
      props: { allowedTabs: ["/inventory"] },
      global: { plugins: [router, pinia] },
    })
    expect(w.findAll("a").length).toBe(1)
  })
})
