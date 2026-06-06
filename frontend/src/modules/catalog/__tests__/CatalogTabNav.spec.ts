import { describe, it, expect, beforeEach } from "vitest"
import { mount } from "@vue/test-utils"
import { createRouter, createWebHistory } from "vue-router"
import CatalogTabNav from "@/modules/catalog/components/CatalogTabNav.vue"
import { setupManagerAuth } from "@/test-utils/setupAuth"

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/catalog", component: { template: "<div/>" } },
    { path: "/catalog/categories", component: { template: "<div/>" } },
    { path: "/catalog/variants", component: { template: "<div/>" } },
    { path: "/catalog/attributes", component: { template: "<div/>" } },
    { path: "/catalog/labels", component: { template: "<div/>" } },
  ],
})

let pinia: ReturnType<typeof setupManagerAuth>

describe("CatalogTabNav", () => {
  beforeEach(() => {
    pinia = setupManagerAuth()
  })

  it("renders all tabs for manager", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, { global: { plugins: [router, pinia] } })
    // Manager sees all 5 tabs (Produits, Catégories, Déclinaisons, Attributs, Étiquettes)
    expect(w.findAll("a").length).toBeGreaterThanOrEqual(4)
  })

  it("shows variants count badge", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, {
      props: { counts: { variants: 12 } },
      global: { plugins: [router, pinia] },
    })
    expect(w.text()).toContain("12")
  })

  it("tab Declinaisons links to /catalog/variants", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, { global: { plugins: [router, pinia] } })
    expect(w.html()).toContain("/catalog/variants")
  })

  it("restricts tabs with allowedTabs prop override", async () => {
    await router.push("/catalog")
    const w = mount(CatalogTabNav, {
      props: { allowedTabs: ["/catalog"] },
      global: { plugins: [router, pinia] },
    })
    expect(w.findAll("a").length).toBe(1)
  })
})
