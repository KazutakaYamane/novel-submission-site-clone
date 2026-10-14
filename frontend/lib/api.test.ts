import { afterEach, describe, expect, it, vi } from "vitest";
import { apiUrl, fetchHealth } from "./api";

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("apiUrl", () => {
  it("INTERNAL_API_URL に /api/v1 とパスを連結する", () => {
    vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
    expect(apiUrl("/health")).toBe("http://nginx:8080/api/v1/health");
  });

  it("INTERNAL_API_URL が未設定ならエラーにする", () => {
    vi.stubEnv("INTERNAL_API_URL", "");
    expect(() => apiUrl("/health")).toThrow("INTERNAL_API_URL");
  });
});

describe("fetchHealth", () => {
  it("ok でない応答ならエラーにする", async () => {
    vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status: 500 })));
    await expect(fetchHealth()).rejects.toThrow("500");
  });

  it("userIp を X-User-IP ヘッダーで渡す", async () => {
    vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
    const fetchMock = vi.fn().mockResolvedValue(Response.json({ status: "ok" }));
    vi.stubGlobal("fetch", fetchMock);
    await fetchHealth("203.0.113.1");
    expect(fetchMock.mock.calls[0][1].headers).toEqual({ "X-User-IP": "203.0.113.1" });
  });
});
