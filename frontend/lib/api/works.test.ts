import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getPublishedWork } from "./works";

const cache = vi.hoisted(() => ({
  cacheTag: vi.fn(),
  cacheLife: vi.fn(),
}));

vi.mock("next/cache", () => cache);

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";

const publicWork = {
  id: WORK_ID,
  title: "星を継ぐ者たち",
  catchphrase: "最後の星が消える夜に",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "ongoing",
  rating_flags: ["violence"],
  tags: ["宇宙", "群像劇"],
  published_at: "2026-10-01T00:00:00+00:00",
  last_episode_published_at: "2026-10-09T03:00:00+00:00",
  author: { name: "山田太郎" },
  episodes: [
    { id: "0192f0a0-0000-7000-8000-000000000011", title: "第1話", position: 1, published_at: "2026-10-01T00:00:00+00:00" },
    { id: "0192f0a0-0000-7000-8000-000000000012", title: "第2話", position: 2, published_at: "2026-10-09T03:00:00+00:00" },
  ],
};

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
  cache.cacheTag.mockClear();
  cache.cacheLife.mockClear();
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("getPublishedWork", () => {
  it("/api/works/{id} を呼び、画面が使う項目だけを返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: publicWork }));
    await expect(getPublishedWork(WORK_ID)).resolves.toEqual({
      id: WORK_ID,
      title: "星を継ぐ者たち",
      authorName: "山田太郎",
      catchphrase: "最後の星が消える夜に",
      introduction: "あらすじの本文",
      genre: "sf",
      status: "ongoing",
      ratingFlags: ["violence"],
      tags: ["宇宙", "群像劇"],
      lastEpisodePublishedAt: "2026-10-09T03:00:00+00:00",
      episodes: [
        { id: "0192f0a0-0000-7000-8000-000000000011", title: "第1話", publishedAt: "2026-10-01T00:00:00+00:00" },
        { id: "0192f0a0-0000-7000-8000-000000000012", title: "第2話", publishedAt: "2026-10-09T03:00:00+00:00" },
      ],
    });
    expect(fetchMock.mock.calls[0][0]).toBe(`http://nginx:8080/api/works/${WORK_ID}`);
  });

  it.each([
    ["パスの文字を含む", "../my/works"],
    ["UUID の形でない", "not-a-uuid"],
    // Laravel は大文字でも同じ作品を返すが、破棄は小文字のタグにしか届かない
    ["大文字の UUID", WORK_ID.toUpperCase()],
  ])("ID が%s なら、Laravel を呼ばず、キャッシュもせずに null を返す", async (_, workId) => {
    await expect(getPublishedWork(workId)).resolves.toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
    expect(cache.cacheTag).not.toHaveBeenCalled();
  });

  it("トークンも X-User-IP も付けない", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: publicWork }));
    await getPublishedWork(WORK_ID);
    const headers = new Headers(fetchMock.mock.calls[0][1].headers);
    expect(headers.has("Authorization")).toBe(false);
    expect(headers.has("X-User-IP")).toBe(false);
  });

  it("work:{id} のタグと days の期限でキャッシュする", async () => {
    fetchMock.mockResolvedValue(Response.json({ data: publicWork }));
    await getPublishedWork(WORK_ID);
    expect(cache.cacheTag).toHaveBeenCalledWith(`work:${WORK_ID}`);
    expect(cache.cacheLife).toHaveBeenCalledWith("days");
  });

  it("404 なら null を返す", async () => {
    fetchMock.mockResolvedValue(Response.json({ message: "Not Found" }, { status: 404 }));
    await expect(getPublishedWork(WORK_ID)).resolves.toBeNull();
  });

  it("404 以外の失敗はエラーにする", async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 500 }));
    await expect(getPublishedWork(WORK_ID)).rejects.toThrow("500");
  });
});
