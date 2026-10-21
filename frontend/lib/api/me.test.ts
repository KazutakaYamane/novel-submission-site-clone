import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getCurrentUser } from "./me";

const request = vi.hoisted(() => ({
  cookies: new Map<string, string>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
  cookies: async () => ({
    get: (name: string) => (request.cookies.has(name) ? { name, value: request.cookies.get(name) } : undefined),
  }),
}));

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  request.cookies = new Map();
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("getCurrentUser", () => {
  it("トークンを付けて /api/me を呼び、id と name を返す", async () => {
    request.cookies.set("auth_token", "1|abc");
    fetchMock.mockResolvedValue(
      Response.json({
        data: { id: "u1", name: "山田太郎", email: "taro@example.com", created_at: "2026-10-21T00:00:00+00:00" },
      }),
    );
    await expect(getCurrentUser()).resolves.toEqual({ id: "u1", name: "山田太郎" });
    expect(fetchMock.mock.calls[0][0]).toBe("http://nginx:8080/api/me");
    expect(new Headers(fetchMock.mock.calls[0][1].headers).get("Authorization")).toBe("Bearer 1|abc");
  });

  it("401 なら null を返し、リダイレクトしない", async () => {
    request.cookies.set("auth_token", "1|stale");
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(getCurrentUser()).resolves.toBeNull();
  });

  it("cookie が無ければ Laravel を呼ばずに null を返す", async () => {
    await expect(getCurrentUser()).resolves.toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 以外の失敗はエラーにする", async () => {
    request.cookies.set("auth_token", "1|abc");
    fetchMock.mockResolvedValue(new Response(null, { status: 500 }));
    await expect(getCurrentUser()).rejects.toThrow("500");
  });
});
