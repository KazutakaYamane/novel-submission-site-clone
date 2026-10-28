import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createEpisodeAction, updateWorkAction } from "./actions";

const request = vi.hoisted(() => ({
  cookies: new Map<string, string>(),
}));

const cache = vi.hoisted(() => ({ refresh: vi.fn() }));

vi.mock("next/cache", () => cache);

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
const EPISODE_ID = "0192f0a0-0000-7000-8000-000000000011";

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  request.cookies = new Map([["auth_token", "1|abc"]]);
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
  cache.refresh.mockClear();
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

const workFields = {
  workId: WORK_ID,
  title: "星を継ぐ者たち",
  catchphrase: "最後の星が消える夜に",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "completed",
  tags: "",
};

describe("updateWorkAction", () => {
  it("作品を更新し、画面を描き直す", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: { id: WORK_ID } }));

    await expect(updateWorkAction(idle, form(workFields))).resolves.toEqual({ status: "ok" });

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`http://nginx:8080/api/my/works/${WORK_ID}`);
    expect(init.method).toBe("PATCH");
    // チェックを外したセルフレイティングと空のタグは、空の配列で送って外す
    expect(JSON.parse(init.body)).toEqual({
      title: "星を継ぐ者たち",
      catchphrase: "最後の星が消える夜に",
      introduction: "あらすじの本文",
      genre: "sf",
      status: "completed",
      rating_flags: [],
      tags: [],
    });
    expect(cache.refresh).toHaveBeenCalledOnce();
  });

  it("422 の errors を fieldErrors にして返し、描き直さない", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { title: ["タイトルを入力してください"] } }, { status: 422 }),
    );

    await expect(updateWorkAction(idle, form({ ...workFields, title: "" }))).resolves.toEqual({
      status: "error",
      fieldErrors: { title: ["タイトルを入力してください"] },
    });
    expect(cache.refresh).not.toHaveBeenCalled();
  });

  it.each([403, 404])("%i なら見つからないエラーを返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(updateWorkAction(idle, form(workFields))).resolves.toEqual({
      status: "error",
      message: "作品または話が見つかりません",
    });
  });

  it.each([
    ["workId が UUID でない", { ...workFields, workId: "../works" }],
    ["workId が無い", { ...workFields, workId: undefined }],
    ["status が enum に無い", { ...workFields, status: "paused" }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, values) => {
    const defined = Object.fromEntries(Object.entries(values).filter(([, v]) => v !== undefined)) as Record<string, string>;
    await expect(updateWorkAction(idle, form(defined))).resolves.toEqual({
      status: "error",
      message: "不正なリクエストです",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 なら cookie を消して /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));

    await expect(updateWorkAction(idle, form(workFields))).rejects.toThrow("REDIRECT:/session-expired");
    expect(request.cookies.has("auth_token")).toBe(false);
  });
});

describe("createEpisodeAction", () => {
  it("話を作り、エディタへリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: { id: EPISODE_ID } }, { status: 201 }));

    await expect(createEpisodeAction(idle, form({ workId: WORK_ID, title: "第1話" }))).rejects.toThrow(
      `REDIRECT:/my/works/${WORK_ID}/episodes/${EPISODE_ID}`,
    );

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`http://nginx:8080/api/my/works/${WORK_ID}/episodes`);
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({ title: "第1話" });
  });

  it("422 の errors を fieldErrors にして返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { title: ["タイトルを入力してください"] } }, { status: 422 }),
    );

    await expect(createEpisodeAction(idle, form({ workId: WORK_ID, title: "" }))).resolves.toEqual({
      status: "error",
      fieldErrors: { title: ["タイトルを入力してください"] },
    });
  });

  it.each([
    ["workId が UUID でない", { workId: "x", title: "第1話" }],
    ["title が無い", { workId: WORK_ID }],
    ["title が長すぎる", { workId: WORK_ID, title: "あ".repeat(1001) }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, values) => {
    await expect(createEpisodeAction(idle, form(values))).resolves.toEqual({
      status: "error",
      message: "不正なリクエストです",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
