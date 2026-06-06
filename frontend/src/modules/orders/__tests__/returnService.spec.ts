import { describe, it, expect, vi, beforeEach } from "vitest"
import { returnService } from "@/modules/orders/services/returnService"

vi.mock("@/services/api", () => ({ default: { get: vi.fn(), post: vi.fn() } }))
import api from "@/services/api"

describe("returnService", () => {
  beforeEach(() => { vi.clearAllMocks() })

  it("list calls /orders/returns", async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } })
    await returnService.list()
    expect(vi.mocked(api.get)).toHaveBeenCalledWith("/orders/returns", expect.anything())
  })

  it("list passes status filter", async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [] } })
    await returnService.list({ status: "pending" })
    expect(vi.mocked(api.get)).toHaveBeenCalledWith("/orders/returns", { params: { status: "pending" } })
  })

  it("approve posts to correct endpoint", async () => {
    vi.mocked(api.post).mockResolvedValue({ data: {} })
    await returnService.approve("ret-1")
    expect(vi.mocked(api.post)).toHaveBeenCalledWith("/orders/returns/ret-1/approve", expect.anything())
  })

  it("restock posts to correct endpoint", async () => {
    vi.mocked(api.post).mockResolvedValue({ data: {} })
    await returnService.restock("ret-2")
    expect(vi.mocked(api.post)).toHaveBeenCalledWith("/orders/returns/ret-2/restock", expect.anything())
  })

  it("reject posts with reason", async () => {
    vi.mocked(api.post).mockResolvedValue({ data: {} })
    await returnService.reject("ret-3", "Delai depasse")
    expect(vi.mocked(api.post)).toHaveBeenCalledWith("/orders/returns/ret-3/reject", { reason: "Delai depasse" })
  })
})
