import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { startDemoAction } from "./demo-start-actions";

const request = vi.hoisted(() => ({
  cookies: new Map<string, { value: string; options?: { maxAge?: number; httpOnly?: boolean } }>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers({ "x-forwarded-for": "203.0.113.1" }),
  cookies: async () => ({
    get: (name: string) => {
      const c = request.cookies.get(name);
      return c ? { name, value: c.value } : undefined;
    },
    set: (name: string, value: string, options?: { maxAge?: number }) => request.cookies.set(name, { value, options }),
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
  request.cookies = new Map();
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

function form(fields: Record<string, string | Blob>): FormData {
  const f = new FormData();
  for (const [k, v] of Object.entries(fields)) f.set(k, v);
  return f;
}

const idle = { status: "idle" } as const;
const SAMPLE_WORK_ID = "019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b";

function created(name: string, sampleWorkId: string | null): Response {
  return Response.json(
    {
      data: { id: "u1", name, email: "demo-x@example.com", created_at: "2026-11-13T00:00:00+00:00" },
      token: "1|demo",
      expires_at: "2026-11-20T00:00:00+00:00",
      sample_work_id: sampleWorkId,
    },
    { status: 201 },
  );
}

describe("startDemoAction", () => {
  it("作者で始めると、トークンを cookie に保存して初期作品の編集画面へリダイレクトする", async () => {
    vi.useFakeTimers({ now: new Date("2026-11-13T00:00:00Z") });
    fetchMock.mockResolvedValue(created("デモ作者-0190", SAMPLE_WORK_ID));

    await expect(startDemoAction(idle, form({ role: "author" }))).rejects.toThrow(
      `REDIRECT:/my/works/${SAMPLE_WORK_ID}`,
    );

    expect(request.cookies.get("auth_token")).toEqual({
      value: "1|demo",
      options: expect.objectContaining({ maxAge: 604800, httpOnly: true }),
    });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://nginx:8080/api/demo/sessions");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({ role: "author" });
    // デモの開始は IP 単位でレート制限されるので X-User-IP を付けて呼ぶ
    expect(new Headers(init.headers).get("X-User-IP")).toBe("203.0.113.1");
  });

  it("読者で始めると、トークンを cookie に保存して / へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(created("デモ読者-0190", null));

    await expect(startDemoAction(idle, form({ role: "reader" }))).rejects.toThrow("REDIRECT:/");

    expect(request.cookies.get("auth_token")?.value).toBe("1|demo");
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ role: "reader" });
  });

  it.each([
    ["role が無い", {}],
    ["role が一覧に無い", { role: "admin" }],
    ["role がファイル", { role: new Blob(["author"]) }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, fields) => {
    await expect(startDemoAction(idle, form(fields))).resolves.toEqual({
      status: "error",
      message: "不正なリクエストです",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("429 なら時間をおくよう求めるエラーを返し、cookie を保存しない", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Too Many Attempts." }, { status: 429 }));

    await expect(startDemoAction(idle, form({ role: "author" }))).resolves.toEqual({
      status: "error",
      message: "1 時間に始められるデモの回数の上限に達しました。しばらく待ってから試してください",
    });
    expect(request.cookies.has("auth_token")).toBe(false);
  });

  it.each([500, 422])("%i なら始められなかった旨のエラーを返し、cookie を保存しない", async (status) => {
    fetchMock.mockResolvedValue(new Response(null, { status }));

    await expect(startDemoAction(idle, form({ role: "reader" }))).resolves.toEqual({
      status: "error",
      message: "デモを始められませんでした。時間をおいて試してください",
    });
    expect(request.cookies.has("auth_token")).toBe(false);
  });
});
