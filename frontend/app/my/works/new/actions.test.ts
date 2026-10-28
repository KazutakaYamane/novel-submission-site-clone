import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createWorkAction } from "./actions";

const request = vi.hoisted(() => ({
  cookies: new Map<string, string>(),
}));

vi.mock("next/headers", () => ({
  headers: async () => new Headers(),
  cookies: async () => ({
    get: (name: string) => (request.cookies.has(name) ? { name, value: request.cookies.get(name) } : undefined),
    delete: (name: string) => request.cookies.delete(name),
  }),
}));

vi.mock("next/navigation", () => ({
  redirect: (url: string) => {
    throw new Error(`REDIRECT:${url}`);
  },
}));

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";

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

function form(fields: Record<string, string | string[] | Blob>): FormData {
  const f = new FormData();
  for (const [k, v] of Object.entries(fields)) {
    for (const value of Array.isArray(v) ? v : [v]) f.append(k, value);
  }
  return f;
}

const idle = { status: "idle" } as const;

const fields = {
  title: "星を継ぐ者たち",
  catchphrase: "",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "ongoing",
  rating_flags: ["violence"],
  tags: "宇宙、群像劇",
};

describe("createWorkAction", () => {
  it("作品を作り、編集画面へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: { id: WORK_ID } }, { status: 201 }));

    await expect(createWorkAction(idle, form(fields))).rejects.toThrow(`REDIRECT:/my/works/${WORK_ID}`);

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe("http://nginx:8080/api/my/works");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({
      title: "星を継ぐ者たち",
      catchphrase: "",
      introduction: "あらすじの本文",
      genre: "sf",
      status: "ongoing",
      rating_flags: ["violence"],
      tags: ["宇宙", "群像劇"],
    });
  });

  it("422 の errors を fieldErrors にして返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { title: ["タイトルを入力してください"] } }, { status: 422 }),
    );

    await expect(createWorkAction(idle, form({ ...fields, title: "" }))).resolves.toEqual({
      status: "error",
      fieldErrors: { title: ["タイトルを入力してください"] },
    });
  });

  it.each([
    ["genre が enum に無い", { ...fields, genre: "poem" }],
    ["rating_flags が enum に無い", { ...fields, rating_flags: ["gore"] }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, values) => {
    await expect(createWorkAction(idle, form(values))).resolves.toEqual({
      status: "error",
      message: "不正なリクエストです",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("長すぎる欄は、Laravel を呼ばずにその欄のエラーを返す", async () => {
    await expect(createWorkAction(idle, form({ ...fields, tags: "あ".repeat(1001) }))).resolves.toEqual({
      status: "error",
      fieldErrors: { tags: ["入力が長すぎます"] },
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 なら cookie を消して /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));

    await expect(createWorkAction(idle, form(fields))).rejects.toThrow("REDIRECT:/session-expired");
    expect(request.cookies.has("auth_token")).toBe(false);
  });
});
