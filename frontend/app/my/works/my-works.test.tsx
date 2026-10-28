import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { MyWorks } from "./my-works";

const request = vi.hoisted(() => ({
  cookies: new Map<string, string>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
  cookies: async () => ({
    get: (name: string) => (request.cookies.has(name) ? { name, value: request.cookies.get(name) } : undefined),
  }),
}));

vi.mock("next/navigation", () => ({
  redirect: (url: string) => {
    throw new Error(`REDIRECT:${url}`);
  },
}));

const responses: Record<string, () => Response> = {
  "http://nginx:8080/api/me": () =>
    Response.json({
      data: { id: "u1", name: "山田太郎", email: "taro@example.com", created_at: "2026-10-21T00:00:00+00:00" },
    }),
  "http://nginx:8080/api/my/works": () => Response.json({ data: [] }),
};

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

describe("MyWorks", () => {
  it("/api/me と /api/my/works を、片方の応答を待たずに呼ぶ", async () => {
    const pending: (() => void)[] = [];
    fetchMock.mockImplementation(
      (url: string) => new Promise<Response>((resolve) => pending.push(() => resolve(responses[url]()))),
    );

    const rendered = MyWorks();
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    expect(fetchMock.mock.calls.map(([url]) => url).sort()).toEqual([
      "http://nginx:8080/api/me",
      "http://nginx:8080/api/my/works",
    ]);

    pending.forEach((resolve) => resolve());
    await expect(rendered).resolves.toBeTruthy();
  });

  it("cookie が無ければ /login へリダイレクトする", async () => {
    request.cookies = new Map();
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(MyWorks()).rejects.toThrow("REDIRECT:/login");
  });

  it("期限切れの cookie なら /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(MyWorks()).rejects.toThrow("REDIRECT:/session-expired");
  });
});
