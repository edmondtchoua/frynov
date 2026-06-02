import { describe, it, expect } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import ReportsTabNav from "@/modules/reports/components/ReportsTabNav.vue"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/reports/sales", component: { template: "<div/>" } },
    { path: "/reports/stock", component: { template: "<div/>" } },
  ],
})

describe("ReportsTabNav", () => {
  it("renders 2 tabs", async () => {
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, { global: { plugins: [router] } })
    const links = w.findAll("a")
    expect(links.length).toBeGreaterThanOrEqual(2)
  })

  it("links to both report routes", async () => {
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, { global: { plugins: [router] } })
    const html = w.html()
    expect(html).toContain("/reports/sales")
    expect(html).toContain("/reports/stock")
  })

  it("hides tabs when allowedTabs restricts", async () => {
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, {
      props: { allowedTabs: ["/reports/sales"] },
      global: { plugins: [router] },
    })
    const links = w.findAll("a")
    expect(links.length).toBe(1)
  })
})
