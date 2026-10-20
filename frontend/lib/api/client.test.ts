import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiFetch, apiFetchAsUser } from "./client";

const request = vi.hoisted(() => ({
  headers: new Headers(),
  cookies: new Map<string, string>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => request.headers,
  cookies: async () => ({
    get: (name: string) => (request.cookies.has(name) ? { name, value: request.cookies.get(name) } : undefined),
  }),
}));

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  request.headers = new Headers();
  request.cookies = new Map();
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn().mockResolvedValue(Response.json({ status: "ok" }));
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

function sentHeaders(): Headers {
  return new Headers(fetchMock.mock.calls[0][1].headers);
}

describe("apiFetch", () => {
  it("INTERNAL_API_URL に /api とパスを連結して呼ぶ", async () => {
    await apiFetch("/health");
    expect(fetchMock.mock.calls[0][0]).toBe("http://nginx:8080/api/health");
  });

  it("INTERNAL_API_URL が未設定ならエラーにする", async () => {
    vi.stubEnv("INTERNAL_API_URL", "");
    await expect(apiFetch("/health")).rejects.toThrow("INTERNAL_API_URL");
  });

  it("init を fetch に渡す", async () => {
    await apiFetch("/works", { method: "POST", headers: { "Content-Type": "application/json" } });
    expect(fetchMock.mock.calls[0][1].method).toBe("POST");
    expect(sentHeaders().get("Content-Type")).toBe("application/json");
  });

  it("タイムアウトの signal を付ける", async () => {
    await apiFetch("/health");
    expect(fetchMock.mock.calls[0][1].signal).toBeInstanceOf(AbortSignal);
  });

  it("応答をステータスに関係なくそのまま返す", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 404 }));
    const res = await apiFetch("/works/x");
    expect(res.status).toBe(404);
  });

  it("cookie やリクエストヘッダーがあってもトークンと X-User-IP を付けない", async () => {
    request.cookies.set("auth_token", "secret");
    request.headers = new Headers({ "x-forwarded-for": "203.0.113.1" });
    await apiFetch("/health");
    expect(sentHeaders().has("Authorization")).toBe(false);
    expect(sentHeaders().has("X-User-IP")).toBe(false);
  });
});

describe("apiFetchAsUser", () => {
  it("auth_token cookie があれば Authorization: Bearer を付ける", async () => {
    request.cookies.set("auth_token", "1|abc");
    await apiFetchAsUser("/me");
    expect(sentHeaders().get("Authorization")).toBe("Bearer 1|abc");
  });

  it("auth_token cookie が無ければ Authorization を付けない", async () => {
    await apiFetchAsUser("/me");
    expect(sentHeaders().has("Authorization")).toBe(false);
  });

  it("init のヘッダーを残して認証ヘッダーを足す", async () => {
    request.cookies.set("auth_token", "1|abc");
    await apiFetchAsUser("/works", { method: "POST", headers: { "Content-Type": "application/json" } });
    expect(sentHeaders().get("Content-Type")).toBe("application/json");
    expect(sentHeaders().get("Authorization")).toBe("Bearer 1|abc");
  });

  describe("X-User-IP", () => {
    it("cloudfront-viewer-address からポートを除いた値を付ける", async () => {
      request.headers = new Headers({
        "cloudfront-viewer-address": "203.0.113.1:46532",
        "x-forwarded-for": "198.51.100.1",
      });
      await apiFetchAsUser("/health");
      expect(sentHeaders().get("X-User-IP")).toBe("203.0.113.1");
    });

    it("IPv6 の cloudfront-viewer-address も末尾のポートだけを除く", async () => {
      request.headers = new Headers({ "cloudfront-viewer-address": "2001:db8::1:46532" });
      await apiFetchAsUser("/health");
      expect(sentHeaders().get("X-User-IP")).toBe("2001:db8::1");
    });

    it("cloudfront-viewer-address が無ければ x-forwarded-for の先頭を付ける", async () => {
      request.headers = new Headers({ "x-forwarded-for": "203.0.113.1, 10.0.0.1" });
      await apiFetchAsUser("/health");
      expect(sentHeaders().get("X-User-IP")).toBe("203.0.113.1");
    });

    it("どちらのヘッダーも無ければ付けない", async () => {
      await apiFetchAsUser("/health");
      expect(sentHeaders().has("X-User-IP")).toBe(false);
    });
  });
});
