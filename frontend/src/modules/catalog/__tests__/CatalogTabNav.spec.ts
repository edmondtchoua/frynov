import { describe, it, expect } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import CatalogTabNav from "@/modules/catalog/components/CatalogTabNav.vue"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/catalog", component: { template: "<div/>" } },
    { path: "/catalog/categories", component: { template: "<div/>" } },
    { path: "/catalog/variants", component: { template: "<div/>" } },
    { path: "/catalog/labels", component: { template: "<div/>" } },
  ],
})

describe("CatalogTabNav", () => {
  it("renders 4 tabs", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, { global: { plugins: [router] } })
    expect(w.findAll("a").length).toBe(4)
  })

  it("shows variants count badge", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, {
      props: { counts: { variants: 12 } },
      global: { plugins: [router] },
    })
    expect(w.text()).toContain("12")
  })

  it("tab Declinaisons links to /catalog/variants", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, { global: { plugins: [router] } })
    expect(w.html()).toContain("/catalog/variants")
  })

  it("restricts tabs with allowedTabs prop", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, {
      props: { allowedTabs: ["/catalog"] },
      global: { plugins: [router] },
    })
    expect(w.findAll("a").length).toBe(1)
  })
})
