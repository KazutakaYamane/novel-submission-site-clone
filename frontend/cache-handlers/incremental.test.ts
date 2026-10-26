import { afterEach, describe, expect, it, vi } from "vitest";
import { createCacheHandler } from "./default.mjs";
import { createMemoryRedis } from "./redis.mjs";
import IncrementalCacheHandler, { createIncrementalCacheHandler } from "./incremental.mjs";

function appPage(html: string) {
  return {
    kind: "APP_PAGE",
    html,
    rscData: Buffer.from("rsc"),
    segmentData: new Map([["/_index", Buffer.from("segment")]]),
    headers: {},
    status: 200,
  };
}

describe("Redis の cacheHandler(事前描画したページ)", () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it("保存したページを読み出すと、Buffer と Map を含めて同じ値が返る", async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    const handler = createIncrementalCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });

    await handler.set("/works/1", appPage("<p>作品</p>"), { tags: ["work:1"] });

    expect(await handler.get("/works/1", {})).toEqual({ lastModified: 1_000_000, value: appPage("<p>作品</p>") });
  });

  it("null を保存するとページを消す", async () => {
    const handler = createIncrementalCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
    await handler.set("/works/1", appPage("<p>作品</p>"), { tags: [] });

    await handler.set("/works/1", null, { tags: [] });

    expect(await handler.get("/works/1", {})).toBeNull();
  });

  it("ビルド ID が違うハンドラは、前のビルドのページを読まない", async () => {
    const redis = createMemoryRedis();
    const previous = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    const current = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b2" });

    await previous.set("/works/1", appPage("<p>作品</p>"), { tags: [] });

    expect(await current.get("/works/1", {})).toBeNull();
  });

  it("別のタスクでタグを失効させると、それより前に保存したそのタグのページだけが返らなくなる", async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    const redis = createMemoryRedis();
    const taskA = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    const taskB = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    await taskB.set("/works/1", appPage("作品1"), { tags: ["work:1"] });
    await taskB.set("/works/2", appPage("作品2"), { tags: ["work:2"] });

    vi.advanceTimersByTime(1000);
    await taskA.revalidateTag(["work:1"], { expire: 0 });
    vi.advanceTimersByTime(1000);
    await taskB.set("/works/1/new", appPage("作り直した作品1"), { tags: ["work:1"] });

    expect(await taskB.get("/works/1", {})).toBeNull();
    expect(await taskB.get("/works/2", {})).not.toBeNull();
    expect(await taskB.get("/works/1/new", {})).not.toBeNull();
  });

  it('"use cache" のハンドラで失効させたタグでも、そのタグのページが返らなくなる', async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    const redis = createMemoryRedis();
    const pages = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    const useCache = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    await pages.set("/works/1", appPage("作品1"), { tags: ["work:1"] });

    vi.advanceTimersByTime(1000);
    await useCache.updateTags(["work:1"], { expire: 0 });

    expect(await pages.get("/works/1", {})).toBeNull();
  });

  it("2 つのハンドラが同じタグを同時に失効させても、Redis に書くのは 1 回で、両方のエントリが失効する", async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    const redis = createMemoryRedis();
    const pages = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    const useCache = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    await pages.set("/works/1", appPage("作品1"), { tags: ["work:1"] });
    const hset = vi.spyOn(redis, "hset");

    vi.advanceTimersByTime(1000);
    // Next.js の revalidateTag は、cacheHandlers の updateTags と cacheHandler の revalidateTag を同時に呼ぶ
    await Promise.all([useCache.updateTags(["work:1"], { expire: 0 }), pages.revalidateTag(["work:1"], { expire: 0 })]);

    expect(hset).toHaveBeenCalledTimes(1);
    expect(await pages.get("/works/1", {})).toBeNull();
  });

  describe("Next.js の実際の渡し方(set の ctx.tags は空で、タグはページのヘッダーにある)", () => {
    // 事前描画したページのヘッダーには、暗黙のタグと、静的シェルで使った "use cache" の cacheTag が入る
    function prerenderedPage(html: string, tags: string) {
      return { kind: "APP_PAGE", html, rscData: Buffer.from("rsc"), headers: { "x-next-cache-tags": tags }, status: 200 };
    }
    const getContext = { kind: "APP_PAGE", isRoutePPREnabled: true, isFallback: false };

    it("ヘッダーの cacheTag を別のタスクで失効させると、そのページだけが返らなくなる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const redis = createMemoryRedis();
      const taskA = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      const taskB = createIncrementalCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      await taskB.set("/works/1", prerenderedPage("作品1", "_N_T_/layout,_N_T_/works/1,work:1"), { tags: [] });
      await taskB.set("/works/2", prerenderedPage("作品2", "_N_T_/layout,_N_T_/works/2,work:2"), { tags: [] });

      vi.advanceTimersByTime(1000);
      await taskA.revalidateTag(["work:1"], { expire: 0 });

      expect(await taskB.get("/works/1", getContext)).toBeNull();
      expect(await taskB.get("/works/2", getContext)).not.toBeNull();
    });

    it("ヘッダーの暗黙のタグを失効させると、そのページが返らなくなる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createIncrementalCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("/works/1", prerenderedPage("作品1", "_N_T_/layout,_N_T_/works/1"), { tags: [] });

      vi.advanceTimersByTime(1000);
      await handler.revalidateTag(["_N_T_/works/1"]);

      expect(await handler.get("/works/1", getContext)).toBeNull();
    });
  });

  it("Next.js が new するクラスは、cacheHandler のメソッドをすべて持つ", () => {
    // Next.js はリクエストごとに new する
    const handler = new IncrementalCacheHandler();

    for (const method of ["get", "set", "revalidateTag", "resetRequestCache"]) {
      expect(handler).toHaveProperty(method, expect.any(Function));
    }
  });

  describe("ページの有効期限", () => {
    const DAY = 60 * 60 * 24;

    it("Next.js が渡す cacheControl.expire の秒数が過ぎると、ページが返らなくなる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createIncrementalCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("/works/1", appPage("作品1"), { tags: [], cacheControl: { revalidate: DAY, expire: 2 * DAY } });

      vi.advanceTimersByTime(2 * DAY * 1000 - 1);
      expect(await handler.get("/works/1", {})).not.toBeNull();
      vi.advanceTimersByTime(1);
      expect(await handler.get("/works/1", {})).toBeNull();
    });

    it("cacheControl.expire が 7 日より長いページも、7 日で返らなくなる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createIncrementalCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("/works/1", appPage("作品1"), { tags: [], cacheControl: { revalidate: DAY, expire: 0xfffffffe } });

      vi.advanceTimersByTime(7 * DAY * 1000 - 1);
      expect(await handler.get("/works/1", {})).not.toBeNull();
      vi.advanceTimersByTime(1);
      expect(await handler.get("/works/1", {})).toBeNull();
    });

    it("cacheControl に expire がないページも、7 日で返らなくなる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createIncrementalCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("/login", appPage("ログイン"), { tags: [], cacheControl: { revalidate: false } });

      vi.advanceTimersByTime(7 * DAY * 1000 - 1);
      expect(await handler.get("/login", {})).not.toBeNull();
      vi.advanceTimersByTime(1);
      expect(await handler.get("/login", {})).toBeNull();
    });
  });
});
