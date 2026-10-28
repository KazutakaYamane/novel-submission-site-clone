import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createEpisode,
  getMyEpisode,
  listMyEpisodes,
  publishEpisode,
  unpublishEpisode,
  updateEpisode,
} from "./my-episodes";

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

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";
const EPISODE_ID = "0192f0a0-0000-7000-8000-000000000011";
const BASE = `http://nginx:8080/api/my/works/${WORK_ID}/episodes`;

const episodeResource = {
  id: EPISODE_ID,
  title: "第1話",
  body: "本文",
  position: 1,
  status: "published",
  published_at: "2026-10-09T03:00:00+00:00",
  public_path: `/works/${WORK_ID}/episodes/${EPISODE_ID}`,
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

describe("listMyEpisodes", () => {
  it("トークンを付けて話の一覧を呼び、タイトルと状態を返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({
        data: [
          { id: EPISODE_ID, title: "第1話", position: 1, status: "published", published_at: "2026-10-09T03:00:00+00:00" },
          { id: "e2", title: "第2話", position: 2, status: "draft", published_at: null },
        ],
      }),
    );

    await expect(listMyEpisodes(WORK_ID)).resolves.toEqual([
      { id: EPISODE_ID, title: "第1話", status: "published" },
      { id: "e2", title: "第2話", status: "draft" },
    ]);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(BASE);
    expect(new Headers(init.headers).get("Authorization")).toBe("Bearer 1|abc");
  });

  it.each([403, 404])("%i なら null を返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(listMyEpisodes(WORK_ID)).resolves.toBeNull();
  });

  it("UUID でなければ Laravel を呼ばずに null を返す", async () => {
    await expect(listMyEpisodes("x")).resolves.toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 なら /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(listMyEpisodes(WORK_ID)).rejects.toThrow("REDIRECT:/session-expired");
  });
});

describe("getMyEpisode", () => {
  it("話を呼び、エディタが使う項目を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: episodeResource }));

    await expect(getMyEpisode(WORK_ID, EPISODE_ID)).resolves.toEqual({
      id: EPISODE_ID,
      title: "第1話",
      body: "本文",
      status: "published",
      publicPath: `/works/${WORK_ID}/episodes/${EPISODE_ID}`,
    });
    expect(fetchMock.mock.calls[0][0]).toBe(`${BASE}/${EPISODE_ID}`);
  });

  it.each([403, 404])("%i なら null を返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(getMyEpisode(WORK_ID, EPISODE_ID)).resolves.toBeNull();
  });

  it("話の ID が UUID でなければ Laravel を呼ばずに null を返す", async () => {
    await expect(getMyEpisode(WORK_ID, "x")).resolves.toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("401 なら /session-expired へリダイレクトする", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(getMyEpisode(WORK_ID, EPISODE_ID)).rejects.toThrow("REDIRECT:/session-expired");
  });
});

describe("createEpisode", () => {
  it("POST で話を作り、ID を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: { ...episodeResource, status: "draft" } }, { status: 201 }));

    await expect(createEpisode(WORK_ID, { title: "第1話" })).resolves.toEqual({ ok: true, data: { id: EPISODE_ID } });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(BASE);
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body)).toEqual({ title: "第1話" });
  });

  it("422 の errors を返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { title: ["タイトルを入力してください"] } }, { status: 422 }),
    );
    await expect(createEpisode(WORK_ID, { title: "" })).resolves.toEqual({
      ok: false,
      reason: "invalid",
      fieldErrors: { title: ["タイトルを入力してください"] },
    });
  });
});

describe("updateEpisode", () => {
  it("PATCH でタイトルと本文を送る", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: episodeResource }));

    await expect(updateEpisode(WORK_ID, EPISODE_ID, { title: "第1話", body: "本文" })).resolves.toEqual({
      ok: true,
      data: undefined,
    });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`${BASE}/${EPISODE_ID}`);
    expect(init.method).toBe("PATCH");
    expect(JSON.parse(init.body)).toEqual({ title: "第1話", body: "本文" });
  });

  it.each([403, 404])("%i なら not_found を返す", async (status) => {
    fetchMock.mockResolvedValue(Response.json({ message: "x" }, { status }));
    await expect(updateEpisode(WORK_ID, EPISODE_ID, { title: "第1話", body: "本文" })).resolves.toEqual({
      ok: false,
      reason: "not_found",
    });
  });
});

describe("publishEpisode / unpublishEpisode", () => {
  it.each([
    ["publishEpisode", publishEpisode, "publish"],
    ["unpublishEpisode", unpublishEpisode, "unpublish"],
  ] as const)("%s は POST .../%s を呼ぶ", async (_, fn, action) => {
    fetchMock.mockResolvedValue(Response.json({ data: episodeResource }));

    await expect(fn(WORK_ID, EPISODE_ID)).resolves.toEqual({ ok: true, data: undefined });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`${BASE}/${EPISODE_ID}/${action}`);
    expect(init.method).toBe("POST");
  });

  it("本文が空で公開できないときは 422 の errors を返す", async () => {
    fetchMock.mockResolvedValue(
      Response.json({ message: "x", errors: { body: ["本文を入力してください"] } }, { status: 422 }),
    );
    await expect(publishEpisode(WORK_ID, EPISODE_ID)).resolves.toEqual({
      ok: false,
      reason: "invalid",
      fieldErrors: { body: ["本文を入力してください"] },
    });
  });

  it("401 なら unauthenticated を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Unauthenticated." }, { status: 401 }));
    await expect(unpublishEpisode(WORK_ID, EPISODE_ID)).resolves.toEqual({ ok: false, reason: "unauthenticated" });
  });
});
