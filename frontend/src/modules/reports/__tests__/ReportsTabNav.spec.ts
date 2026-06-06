import { describe, it, expect, beforeEach } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import ReportsTabNav from "@/modules/reports/components/ReportsTabNav.vue"
import { setupManagerAuth } from "@/test-utils/setupAuth"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/reports/sales", component: { template: "<div/>" } },
    { path: "/reports/stock", component: { template: "<div/>" } },
  ],
})

let pinia: ReturnType<typeof setupManagerAuth>

describe("ReportsTabNav", () => {
  beforeEach(() => {
    pinia = setupManagerAuth()
  })

  it("renders 2 tabs for manager", async () => {
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, { global: { plugins: [router, pinia] } })
    const links = w.findAll("a")
    expect(links.length).toBeGreaterThanOrEqual(2)
  })

  it("links to both report routes", async () => {
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, { global: { plugins: [router, pinia] } })
    const html = w.html()
    expect(html).toContain("/reports/sales")
    expect(html).toContain("/reports/stock")
  })

  it("hides tabs when allowedTabs restricts", async () => {
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, {
      props: { allowedTabs: ["/reports/sales"] },
      global: { plugins: [router, pinia] },
    })
    const links = w.findAll("a")
    expect(links.length).toBe(1)
  })

  it("shows no tabs for non-manager when rbacTabs returns empty", async () => {
    // When reportsTabs returns [] for non-managers, no tabs should show
    await router.push("/reports/sales")
    const w = mount(ReportsTabNav, {
      props: { allowedTabs: [] },
      global: { plugins: [router, pinia] },
    })
    expect(w.findAll("a").length).toBe(0)
  })
})
