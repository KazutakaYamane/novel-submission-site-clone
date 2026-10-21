import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { logoutAction } from "./actions";

const request = vi.hoisted(() => ({
  cookies: new Map<string, string>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
  cookies: async () => ({
    get: (name: string) => (request.cookies.has(name) ? { name, value: request.cookies.get(name) } : undefined),
    set: (name: string, value: string) => request.cookies.set(name, value),
    delete: (name: string) => request.cookies.delete(name),
  }),
}));

vi.mock("next/navigation", () => ({
  redirect: (url: string) => {
    throw new Error(`REDIRECT:${url}`);
  },
}));

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  request.cookies = new Map([["auth_token", "1|abc"]]);
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});


describe("logoutAction", () => {
  it("トークンを付けて Laravel のログアウトを呼び、cookie を消して /login へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 204 }));

    await expect(logoutAction()).rejects.toThrow("REDIRECT:/login");

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://nginx:8080/api/auth/logout");
    expect(init.method).toBe("POST");
    expect(new Headers(init.headers).get("Authorization")).toBe("Bearer 1|abc");
    expect(request.cookies.has("auth_token")).toBe(false);
  });

  it("401 なら cookie を消して /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));

    await expect(logoutAction()).rejects.toThrow("REDIRECT:/session-expired");
    expect(request.cookies.has("auth_token")).toBe(false);
  });

  it("401 以外の失敗はエラーにし、cookie を残す", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 500 }));

    await expect(logoutAction()).rejects.toThrow("500");
    expect(request.cookies.has("auth_token")).toBe(true);
  });
});
