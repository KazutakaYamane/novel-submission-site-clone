import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { signupAction } from "./actions";

const request = vi.hoisted(() => ({
  cookies: new Map<string, { value: string; options?: { maxAge?: number } }>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
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
const valid = { name: "山田太郎", email: "taro@example.com", password: "password123" };

describe("signupAction", () => {
  it("成功するとトークンを cookie に保存して /my/works へリダイレクトする", async () => {
    vi.useFakeTimers({ now: new Date("2026-10-21T00:00:00Z") });
    fetchMock.mockResolvedValue(
      Response.json(
        {
          data: { id: "u1", name: "山田太郎", email: "taro@example.com", created_at: "2026-10-21T00:00:00+00:00" },
          token: "1|abc",
          expires_at: "2026-10-28T00:00:00+00:00",
        },
        { status: 201 },
      ),
    );

    await expect(signupAction(idle, form(valid))).rejects.toThrow("REDIRECT:/my/works");

    expect(request.cookies.get("auth_token")).toEqual({ value: "1|abc", options: expect.objectContaining({ maxAge: 604800 }) });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://nginx:8080/api/auth/register");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual(valid);
  });

  it("422 の errors を項目ごとの fieldErrors にして返し、cookie を保存しない", async () => {
    const errors = {
      name: ["名前を入力してください"],
      email: ["このメールアドレスは既に使われています"],
    };
    fetchMock.mockResolvedValue(Response.json({ message: "名前を入力してください (and 1 more error)", errors }, { status: 422 }));

    await expect(signupAction(idle, form({ ...valid, name: "" }))).resolves.toEqual({ status: "error", fieldErrors: errors });
    expect(request.cookies.has("auth_token")).toBe(false);
  });

  it.each([
    ["name が無い", { email: valid.email, password: valid.password }],
    ["email がファイル", { ...valid, email: new Blob(["x"]) }],
    ["password が長すぎる", { ...valid, password: "a".repeat(1001) }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, fields) => {
    await expect(signupAction(idle, form(fields))).resolves.toEqual({ status: "error", message: "不正なリクエストです" });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("422 以外の失敗はエラーにする", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 500 }));
    await expect(signupAction(idle, form(valid))).rejects.toThrow("500");
  });
});
