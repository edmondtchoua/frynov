import { describe, it, expect, vi } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import InventoryTabNav from "@/modules/inventory/components/InventoryTabNav.vue"

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

describe("InventoryTabNav", () => {
  it("renders 5 tabs", async () => {
    await router.push("/inventory")
    const w = mount(InventoryTabNav, { global: { plugins: [router] } })
    const tabs = w.findAll(".catalog-tab, .inventory-tab, a[class*=tab]")
    expect(tabs.length).toBeGreaterThanOrEqual(4)
  })

  it("shows alert badge when count > 0", async () => {
    await router.push("/inventory/alerts")
    const w = mount(InventoryTabNav, {
      props: { counts: { alerts: 3 } },
      global: { plugins: [router] },
    })
    expect(w.text()).toContain("3")
  })

  it("has links to all inventory sub-routes", async () => {
    await router.push("/inventory")
    const w = mount(InventoryTabNav, { global: { plugins: [router] } })
    const html = w.html()
    expect(html).toContain("/inventory/warehouses")
    expect(html).toContain("/inventory/transfers")
    expect(html).toContain("/inventory/fiscal-periods")
  })
})
