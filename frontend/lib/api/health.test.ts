import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getHealth } from "./health";

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("getHealth", () => {
  it("/api/health の応答本文を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ status: "ok" }));
    await expect(getHealth()).resolves.toEqual({ status: "ok" });
    expect(fetchMock.mock.calls[0][0]).toBe("http://nginx:8080/api/health");
  });

  it("ok でない応答ならエラーにする", async () => {
    fetchMock.mockResolvedValue(new Response("{}", { status: 500 }));
    await expect(getHealth()).rejects.toThrow("500");
  });
});
