import { describe, it, expect } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import SalesTabNav from "@/modules/orders/components/SalesTabNav.vue"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/orders", component: { template: "<div/>" } },
    { path: "/orders/returns", component: { template: "<div/>" } },
    { path: "/payments", component: { template: "<div/>" } },
    { path: "/deliveries", component: { template: "<div/>" } },
  ],
})

describe("SalesTabNav", () => {
  it("renders 4 tabs", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, { global: { plugins: [router] } })
    const links = w.findAll("a")
    expect(links.length).toBeGreaterThanOrEqual(4)
  })

  it("has links to all sales sub-routes", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, { global: { plugins: [router] } })
    const html = w.html()
    expect(html).toContain("/orders/returns")
    expect(html).toContain("/payments")
    expect(html).toContain("/deliveries")
  })

  it("shows returns badge when count > 0", async () => {
    await router.push("/orders")
    const w = mount(SalesTabNav, {
      props: { counts: { returns: 5 } },
      global: { plugins: [router] },
    })
    expect(w.text()).toContain("5")
  })
})
