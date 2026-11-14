import { renderToStaticMarkup } from "react-dom/server";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { NewWorks } from "./new-works";

const calls = vi.hoisted(() => [] as string[]);

vi.mock("next/cache", () => ({ cacheTag: vi.fn(), cacheLife: vi.fn() }));
vi.mock("next/server", () => ({
  connection: async () => {
    calls.push("connection");
  },
}));

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";

const summary = {
  id: WORK_ID,
  title: "星を継ぐ者たち",
  catchphrase: "最後の星が消える夜に",
  genre: "sf",
  status: "ongoing",
  published_episode_count: 2,
  last_episode_published_at: "2026-10-09T03:00:00+00:00",
  author: { name: "山田太郎" },
};

let fetchMock: ReturnType<typeof vi.fn>;

function respond(data: unknown[]) {
  fetchMock.mockImplementation(async () => {
    calls.push("fetch");
    return Response.json({ data });
  });
}

async function render(): Promise<string> {
  return renderToStaticMarkup(await NewWorks());
}

const text = (html: string) => html.replace(/<[^>]+>/g, "");

beforeEach(() => {
  calls.length = 0;
  vi.stubEnv("INTERNAL_API_URL", "http://nginx:8080");
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("NewWorks", () => {
  it("事前描画で止まるよう、Laravel を呼ぶ前に connection() を待つ", async () => {
    respond([summary]);
    await render();
    expect(calls).toEqual(["connection", "fetch"]);
  });

  it("作品ごとにキャッチコピー・タイトル・作者名・ジャンル・連載状態と話数・最終更新日を出す", async () => {
    respond([summary]);
    const html = text(await render());
    expect(html).toContain("最後の星が消える夜に");
    expect(html).toContain("星を継ぐ者たち／山田太郎");
    expect(html).toContain("SF");
    expect(html).toContain("連載中 2話");
    expect(html).toContain("2026年10月9日 更新");
  });

  it("タイトルを作品ページへのリンクにする", async () => {
    respond([summary]);
    expect(await render()).toContain(`<a href="/works/${WORK_ID}">星を継ぐ者たち</a>`);
  });

  it("完結済の作品は「完結済」と出す", async () => {
    respond([{ ...summary, status: "completed", published_episode_count: 178 }]);
    expect(text(await render())).toContain("完結済 178話");
  });

  it("キャッチコピーが無い作品は、キャッチコピーの行を出さない", async () => {
    respond([{ ...summary, catchphrase: null }]);
    const html = await render();
    expect(html.match(/<p/g)).toHaveLength(1);
    expect(text(html)).toContain("星を継ぐ者たち／山田太郎");
  });

  it("作品が無ければ、無いことを出す", async () => {
    respond([]);
    expect(text(await render())).toBe("まだ公開された作品はありません");
  });
});
