import { describe, it, expect, beforeEach } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import SalesTabNav from "@/modules/orders/components/SalesTabNav.vue"
import { setupManagerAuth } from "@/test-utils/setupAuth"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/orders", component: { template: "<div/>" } },
    { path: "/orders/returns", component: { template: "<div/>" } },
    { path: "/payments", component: { template: "<div/>" } },
    { path: "/deliveries", component: { template: "<div/>" } },
  ],
})

let pinia: ReturnType<typeof setupManagerAuth>

describe("SalesTabNav", () => {
  beforeEach(() => {
    pinia = setupManagerAuth()
  })

  it("renders all 4 tabs for manager", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, { global: { plugins: [router, pinia] } })
    const links = w.findAll("a")
    expect(links.length).toBeGreaterThanOrEqual(4)
  })

  it("has links to all sales sub-routes", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, { global: { plugins: [router, pinia] } })
    const html = w.html()
    expect(html).toContain("/orders/returns")
    expect(html).toContain("/payments")
    expect(html).toContain("/deliveries")
  })

  it("shows returns badge when count > 0", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, {
      props: { counts: { returns: 5 } },
      global: { plugins: [router, pinia] },
    })
    expect(w.text()).toContain("5")
  })

  it("restricts tabs with allowedTabs prop override", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, {
      props: { allowedTabs: ["/orders", "/payments"] },
      global: { plugins: [router, pinia] },
    })
    const html = w.html()
    expect(html).toContain("/orders")
    expect(html).toContain("/payments")
    expect(html).not.toContain("/orders/returns")
    expect(html).not.toContain("/deliveries")
  })
})
