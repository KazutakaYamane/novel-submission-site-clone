import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { saveEpisodeAction, unpublishEpisodeAction } from "./actions";

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
const EPISODE_URL = `http://nginx:8080/api/my/works/${WORK_ID}/episodes/${EPISODE_ID}`;

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

function form(fields: Record<string, string | Blob>): FormData {
  const f = new FormData();
  for (const [k, v] of Object.entries(fields)) f.set(k, v);
  return f;
}

const idle = { status: "idle" } as const;

const fields = { workId: WORK_ID, episodeId: EPISODE_ID, title: "第1話", body: "本文", intent: "save" };

const ok = () => Response.json({ data: { id: EPISODE_ID } });

describe("saveEpisodeAction", () => {
  it("下書き保存は PATCH だけを呼び、画面を描き直す", async () => {
    fetchMock.mockResolvedValue(ok());

    await expect(saveEpisodeAction(idle, form(fields))).resolves.toEqual({ status: "ok" });

    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(EPISODE_URL);
    expect(init.method).toBe("PATCH");
    expect(JSON.parse(init.body)).toEqual({ title: "第1話", body: "本文" });
    expect(cache.refresh).toHaveBeenCalledOnce();
  });

  it("公開は PATCH で保存してから publish を呼ぶ", async () => {
    fetchMock.mockImplementation(async () => ok());

    await expect(saveEpisodeAction(idle, form({ ...fields, intent: "publish" }))).resolves.toEqual({ status: "ok" });

    expect(fetchMock.mock.calls.map(([url, init]) => [url, init.method])).toEqual([
      [EPISODE_URL, "PATCH"],
      [`${EPISODE_URL}/publish`, "POST"],
    ]);
    expect(cache.refresh).toHaveBeenCalledOnce();
  });

  it("保存が 422 なら公開せず、errors を返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { title: ["タイトルを入力してください"] } }, { status: 422 }),
    );

    await expect(saveEpisodeAction(idle, form({ ...fields, title: "", intent: "publish" }))).resolves.toEqual({
      status: "error",
      fieldErrors: { title: ["タイトルを入力してください"] },
    });
    expect(fetchMock).toHaveBeenCalledOnce();
    expect(cache.refresh).not.toHaveBeenCalled();
  });

  it("公開が 422 でも、保存した内容を出すため描き直す", async () => {
    fetchMock
      .mockResolvedValueOnce(ok())
      .mockResolvedValueOnce(
        Response.json({ message: "x", errors: { body: ["本文が空の話は公開できません。"] } }, { status: 422 }),
      );

    await expect(saveEpisodeAction(idle, form({ ...fields, body: "", intent: "publish" }))).resolves.toEqual({
      status: "error",
      fieldErrors: { body: ["本文が空の話は公開できません。"] },
    });
    expect(cache.refresh).toHaveBeenCalledOnce();
  });

  it.each([403, 404])("%i なら見つからないエラーを返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(saveEpisodeAction(idle, form(fields))).resolves.toEqual({
      status: "error",
      message: "作品または話が見つかりません",
    });
  });

  it.each([
    ["workId が UUID でない", { ...fields, workId: "x" }],
    ["episodeId が UUID でない", { ...fields, episodeId: "x" }],
    ["title が長すぎる", { ...fields, title: "あ".repeat(1001) }],
    ["body が長すぎる", { ...fields, body: "a".repeat(6_990_475) }],
    ["body がファイル", { ...fields, body: new Blob(["x"]) }],
    ["intent が想定外", { ...fields, intent: "unpublish" }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, values) => {
    await expect(saveEpisodeAction(idle, form(values))).resolves.toEqual({
      status: "error",
      message: "不正なリクエストです",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 なら cookie を消して /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));

    await expect(saveEpisodeAction(idle, form(fields))).rejects.toThrow("REDIRECT:/session-expired");
    expect(request.cookies.has("auth_token")).toBe(false);
  });
});

describe("unpublishEpisodeAction", () => {
  it("unpublish を呼び、画面を描き直す", async () => {
    fetchMock.mockResolvedValue(ok());

    await expect(unpublishEpisodeAction(idle, form({ workId: WORK_ID, episodeId: EPISODE_ID }))).resolves.toEqual({
      status: "ok",
    });

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`${EPISODE_URL}/unpublish`);
    expect(init.method).toBe("POST");
    expect(cache.refresh).toHaveBeenCalledOnce();
  });

  it("422 なら errors を返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { status: ["公開されていない話です。"] } }, { status: 422 }),
    );
    await expect(unpublishEpisodeAction(idle, form({ workId: WORK_ID, episodeId: EPISODE_ID }))).resolves.toEqual({
      status: "error",
      fieldErrors: { status: ["公開されていない話です。"] },
    });
  });

  it.each([
    ["workId が UUID でない", { workId: "x", episodeId: EPISODE_ID }],
    ["episodeId が無い", { workId: WORK_ID }],
  ])("%s なら Laravel を呼ばずにエラーを返す", async (_, values) => {
    await expect(unpublishEpisodeAction(idle, form(values))).resolves.toEqual({
      status: "error",
      message: "不正なリクエストです",
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
