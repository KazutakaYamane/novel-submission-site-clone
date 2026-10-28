import { beforeEach, describe, expect, it, vi } from "vitest";
import { getPublishedEpisode } from "./episodes";

const cache = vi.hoisted(() => ({
  cacheTag: vi.fn(),
  cacheLife: vi.fn(),
}));

const client = vi.hoisted(() => ({
  apiFetch: vi.fn(),
  apiFetchAsUser: vi.fn(),
}));

vi.mock("next/cache", () => cache);
vi.mock("./client", () => client);

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";
const EPISODE_ID = "0192f0a0-0000-7000-8000-000000000012";

const publicEpisode = {
  id: EPISODE_ID,
  title: "第2話",
  body: "一行目\n二行目",
  position: 2,
  published_at: "2026-10-09T03:00:00+00:00",
  work: { id: WORK_ID, title: "星を継ぐ者たち", author: { name: "山田太郎" } },
  previous: { id: "0192f0a0-0000-7000-8000-000000000011", title: "第1話" },
  next: null,
};

beforeEach(() => {
  client.apiFetch.mockReset();
  client.apiFetchAsUser.mockReset();
  cache.cacheTag.mockClear();
  cache.cacheLife.mockClear();
});

describe("getPublishedEpisode", () => {
  it("/works/{workId}/episodes/{episodeId} を apiFetch で呼び、画面が使う項目だけを返す", async () => {
    client.apiFetch.mockResolvedValue(Response.json({ data: publicEpisode }));
    await expect(getPublishedEpisode(WORK_ID, EPISODE_ID)).resolves.toEqual({
      id: EPISODE_ID,
      title: "第2話",
      body: "一行目\n二行目",
      work: { id: WORK_ID, title: "星を継ぐ者たち" },
      previous: { id: "0192f0a0-0000-7000-8000-000000000011", title: "第1話" },
      next: null,
    });
    expect(client.apiFetch).toHaveBeenCalledWith(`/works/${WORK_ID}/episodes/${EPISODE_ID}`);
    expect(client.apiFetchAsUser).not.toHaveBeenCalled();
  });

  it("episode:{id} と work:{workId} のタグ、days の期限でキャッシュする", async () => {
    client.apiFetch.mockResolvedValue(Response.json({ data: publicEpisode }));
    await getPublishedEpisode(WORK_ID, EPISODE_ID);
    expect(cache.cacheTag).toHaveBeenCalledWith(`episode:${EPISODE_ID}`, `work:${WORK_ID}`);
    expect(cache.cacheLife).toHaveBeenCalledWith("days");
  });

  it.each([
    ["作品 ID", "../my/works", EPISODE_ID],
    ["話 ID", WORK_ID, "not-a-uuid"],
    // Laravel は大文字でも同じ話を返すが、破棄は小文字のタグにしか届かない
    ["話 ID(大文字)", WORK_ID, EPISODE_ID.toUpperCase()],
  ])("%sが UUID の形でなければ、Laravel を呼ばず、キャッシュもせずに null を返す", async (_, workId, episodeId) => {
    await expect(getPublishedEpisode(workId, episodeId)).resolves.toBeNull();
    expect(client.apiFetch).not.toHaveBeenCalled();
    expect(cache.cacheTag).not.toHaveBeenCalled();
  });

  it("404 なら null を返す", async () => {
    client.apiFetch.mockResolvedValue(Response.json({ message: "Not Found" }, { status: 404 }));
    await expect(getPublishedEpisode(WORK_ID, EPISODE_ID)).resolves.toBeNull();
  });

  it("404 以外の失敗はエラーにする", async () => {
    client.apiFetch.mockResolvedValue(new Response(null, { status: 500 }));
    await expect(getPublishedEpisode(WORK_ID, EPISODE_ID)).rejects.toThrow("500");
  });
});
