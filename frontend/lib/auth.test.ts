import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { deleteToken, endSessionAndRedirect, redirectUnauthenticated, setToken } from "./auth";

type CookieOptions = { maxAge?: number; httpOnly?: boolean; secure?: boolean; sameSite?: string; path?: string };

const request = vi.hoisted(() => ({
  cookies: new Map<string, { value: string; options?: CookieOptions }>(),
}));

vi.mock("next/headers", () => ({
  cookies: async () => ({
    get: (name: string) => {
      const c = request.cookies.get(name);
      return c ? { name, value: c.value } : undefined;
    },
    set: (name: string, value: string, options?: CookieOptions) => request.cookies.set(name, { value, options }),
    delete: (name: string) => request.cookies.delete(name),
  }),
}));

vi.mock("next/navigation", () => ({
  redirect: (url: string) => {
    throw new Error(`REDIRECT:${url}`);
  },
}));

beforeEach(() => {
  request.cookies = new Map();
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.useRealTimers();
});

describe("setToken", () => {
  it("auth_token を httpOnly・sameSite=lax・path=/ で保存する", async () => {
    await setToken("1|abc", "2026-10-28T00:00:00+00:00");
    const cookie = request.cookies.get("auth_token");
    expect(cookie?.value).toBe("1|abc");
    expect(cookie?.options).toMatchObject({ httpOnly: true, sameSite: "lax", path: "/" });
  });

  it("maxAge は expires_at までの秒数にする", async () => {
    vi.useFakeTimers({ now: new Date("2026-10-21T00:00:00Z") });
    await setToken("1|abc", "2026-10-28T09:00:00+09:00");
    expect(request.cookies.get("auth_token")?.options?.maxAge).toBe(7 * 24 * 60 * 60);
  });

  it("expires_at が過去なら maxAge を 0 にする", async () => {
    vi.useFakeTimers({ now: new Date("2026-10-21T00:00:00Z") });
    await setToken("1|abc", "2026-10-20T00:00:00Z");
    expect(request.cookies.get("auth_token")?.options?.maxAge).toBe(0);
  });

  it("development 以外では secure を付ける", async () => {
    vi.stubEnv("NODE_ENV", "production");
    await setToken("1|abc", "2026-10-28T00:00:00Z");
    expect(request.cookies.get("auth_token")?.options?.secure).toBe(true);
  });

  it("development では secure を付けない", async () => {
    vi.stubEnv("NODE_ENV", "development");
    await setToken("1|abc", "2026-10-28T00:00:00Z");
    expect(request.cookies.get("auth_token")?.options?.secure).toBe(false);
  });
});

describe("deleteToken", () => {
  it("auth_token を消す", async () => {
    request.cookies.set("auth_token", { value: "1|abc" });
    await deleteToken();
    expect(request.cookies.has("auth_token")).toBe(false);
  });
});

describe("redirectUnauthenticated", () => {
  it("cookie があれば /session-expired へ", async () => {
    request.cookies.set("auth_token", { value: "1|stale" });
    await expect(redirectUnauthenticated()).rejects.toThrow("REDIRECT:/session-expired");
  });

  it("cookie が無ければ /login へ", async () => {
    await expect(redirectUnauthenticated()).rejects.toThrow("REDIRECT:/login");
  });
});

describe("endSessionAndRedirect", () => {
  it("cookie を消してから /session-expired へ", async () => {
    request.cookies.set("auth_token", { value: "1|stale" });
    await expect(endSessionAndRedirect()).rejects.toThrow("REDIRECT:/session-expired");
    expect(request.cookies.has("auth_token")).toBe(false);
  });

  it("cookie が無ければ /login へ", async () => {
    await expect(endSessionAndRedirect()).rejects.toThrow("REDIRECT:/login");
  });
});
