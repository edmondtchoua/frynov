import { describe, it, expect, vi, beforeEach } from "vitest"
import { productService } from "@/modules/catalog/services/productService"

vi.mock("@/api/client", () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn() } }))
import client from "@/api/client"

describe("productService", () => {
  beforeEach(() => { vi.clearAllMocks() })

  it("list calls /api/catalog/products", async () => {
    vi.mocked(client.get).mockResolvedValue({ data: { data: [], meta: { total: 0, current_page: 1, last_page: 1, per_page: 20 } } })
    await productService.list()
    expect(vi.mocked(client.get)).toHaveBeenCalledWith("/api/catalog/products", expect.anything())
  })

  it("list passes filters", async () => {
    vi.mocked(client.get).mockResolvedValue({ data: { data: [], meta: { total: 0, current_page: 1, last_page: 1, per_page: 20 } } })
    await productService.list({ status: "active", search: "boubou" })
    expect(vi.mocked(client.get)).toHaveBeenCalledWith(expect.any(String), { params: expect.objectContaining({ status: "active" }) })
  })

  it("get resolves product data", async () => {
    vi.mocked(client.get).mockResolvedValue({ data: { data: { id: "p1", sku: "PROD-000001" } } })
    const result = await productService.get("p1")
    expect(result.id).toBe("p1")
  })

  it("create posts and resolves", async () => {
    vi.mocked(client.post).mockResolvedValue({ data: { data: { id: "p2" } } })
    await productService.create({ name: "Test", price_amount: 1000, price_currency: "XOF", status: "draft" })
    expect(vi.mocked(client.post)).toHaveBeenCalled()
  })

  it("archive calls patch archive", async () => {
    vi.mocked(client.patch).mockResolvedValue({ data: {} })
    await productService.archive("p3")
    expect(vi.mocked(client.patch)).toHaveBeenCalledWith(expect.stringContaining("archive"))
  })

  it("categories.list calls /api/catalog/categories", async () => {
    vi.mocked(client.get).mockResolvedValue({ data: [] })
    await productService.categories.list()
    expect(vi.mocked(client.get)).toHaveBeenCalledWith(expect.stringContaining("categories"), undefined)
  })
})
