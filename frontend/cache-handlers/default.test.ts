import { afterEach, describe, expect, it, vi } from "vitest";
import { createCacheHandler } from "./default.mjs";
import { createMemoryRedis } from "./redis.mjs";

const DAY = 60 * 60 * 24;

function entry(body: string, overrides: { tags?: string[]; timestamp?: number; expire?: number } = {}) {
  return {
    value: new ReadableStream<Uint8Array>({
      start(controller) {
        controller.enqueue(new TextEncoder().encode(body));
        controller.close();
      },
    }),
    tags: overrides.tags ?? [],
    stale: 300,
    timestamp: overrides.timestamp ?? Date.now(),
    expire: overrides.expire ?? 7 * DAY,
    revalidate: DAY,
  };
}

async function readBody(stream: ReadableStream<Uint8Array>) {
  return new Response(stream).text();
}

describe("Redis の cacheHandlers.default", () => {
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("保存したエントリを読み出すと、同じ値とメタデータが返る", async () => {
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
    const saved = entry("作品の本文", { tags: ["work:1"] });

    await handler.set("k", Promise.resolve(saved));
    const got = await handler.get("k", []);

    expect(got).toMatchObject({ tags: ["work:1"], stale: 300, timestamp: saved.timestamp, expire: 7 * DAY, revalidate: DAY });
    expect(await readBody(got!.value)).toBe("作品の本文");
  });

  it("保存の途中で同じキーを読むと、保存が終わるのを待ってから返す", async () => {
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
    const { promise, resolve } = Promise.withResolvers<ReturnType<typeof entry>>();

    const saving = handler.set("k", promise);
    const reading = handler.get("k", []);
    resolve(entry("生成中だった本文"));
    await saving;

    expect(await readBody((await reading)!.value)).toBe("生成中だった本文");
  });

  it("保存の途中で同じキーを読み、その保存が失敗したときは、キャッシュなしとして返す", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
    const { promise, reject } = Promise.withResolvers<ReturnType<typeof entry>>();

    const saving = handler.set("k", promise);
    const reading = handler.get("k", []);
    reject(new Error("生成に失敗"));
    await saving;

    await expect(reading).resolves.toBeUndefined();
  });

  it("保存された値が壊れているときは、キャッシュなしとして返す", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    const redis = createMemoryRedis();
    const handler = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });

    await redis.set("t:use-cache:b1:entry:k", "{壊れた JSON");

    await expect(handler.get("k", [])).resolves.toBeUndefined();
  });

  it("ビルド ID が違うハンドラは、前のビルドのエントリを読まない", async () => {
    const redis = createMemoryRedis();
    const previous = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
    const current = createCacheHandler({ redis, keyPrefix: "t", buildId: "b2" });

    await previous.set("k", Promise.resolve(entry("前のビルド")));

    expect(await current.get("k", [])).toBeUndefined();
  });

  it("エントリは expire の秒数が過ぎると返らなくなる", async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
    await handler.set("k", Promise.resolve(entry("本文", { expire: 7 * DAY })));

    vi.advanceTimersByTime(7 * DAY * 1000 - 1);
    expect(await handler.get("k", [])).toBeDefined();
    vi.advanceTimersByTime(1);
    expect(await handler.get("k", [])).toBeUndefined();
  });

  it("expire が整数でなくても保存する(Redis の EX は整数しか受け付けない)", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });

    await handler.set("k", Promise.resolve(entry("本文", { expire: 90.5 })));

    expect(await handler.get("k", [])).toBeDefined();
  });

  it("expire が 7 日より長くても、7 日で返らなくなる(前のビルドのキーを残さない)", async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
    // cacheLife を付けない "use cache" の expire は、Next.js の INFINITE_CACHE(0xfffffffe 秒)になる
    await handler.set("k", Promise.resolve(entry("本文", { expire: 0xfffffffe })));

    vi.advanceTimersByTime(7 * DAY * 1000 - 1);
    expect(await handler.get("k", [])).toBeDefined();
    vi.advanceTimersByTime(1);
    expect(await handler.get("k", [])).toBeUndefined();
  });

  it("expire が 0 のエントリは保存しない", async () => {
    const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });

    await handler.set("k", Promise.resolve(entry("毎回作り直す", { expire: 0 })));

    expect(await handler.get("k", [])).toBeUndefined();
  });

  describe("タグの失効", () => {
    it("別のビルドのハンドラで失効させたタグも、このビルドのエントリを失効させる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const redis = createMemoryRedis();
      const previous = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      const current = createCacheHandler({ redis, keyPrefix: "t", buildId: "b2" });
      await current.set("k", Promise.resolve(entry("本文", { tags: ["work:1"], timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      await previous.updateTags(["work:1"], { expire: 0 });
      await current.refreshTags();

      expect(await current.get("k", [])).toBeUndefined();
    });

    it("タグを失効させると、それより前に作られたそのタグのエントリだけが返らなくなる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("old", Promise.resolve(entry("古い", { tags: ["work:1"], timestamp: Date.now() })));
      await handler.set("other", Promise.resolve(entry("別の作品", { tags: ["work:2"], timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      await handler.updateTags(["work:1"], { expire: 0 });
      vi.advanceTimersByTime(1000);
      await handler.set("new", Promise.resolve(entry("新しい", { tags: ["work:1"], timestamp: Date.now() })));
      await handler.refreshTags();

      expect(await handler.get("old", [])).toBeUndefined();
      expect(await handler.get("other", [])).toBeDefined();
      expect(await handler.get("new", [])).toBeDefined();
    });

    it("失効の期限が先のときは、エントリを返したうえで再生成を求める", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("k", Promise.resolve(entry("本文", { tags: ["work:1"], timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      await handler.updateTags(["work:1"], { expire: 60 });
      await handler.refreshTags();

      expect(await handler.get("k", [])).toMatchObject({ revalidate: -1 });
      vi.advanceTimersByTime(60 * 1000);
      expect(await handler.get("k", [])).toBeUndefined();
    });

    it("refreshTags がまだ成功していなくても、別のタスクで失効させたタグのエントリは返らない", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const redis = createMemoryRedis();
      const starting = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      const other = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      await other.set("k", Promise.resolve(entry("本文", { tags: ["work:1"], timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      await other.updateTags(["work:1"], { expire: 0 });

      expect(await starting.get("k", [])).toBeUndefined();
    });

    it("refreshTags がまだ成功していないときは、getExpiration が Infinity を返し、get が暗黙のタグを確かめる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const redis = createMemoryRedis();
      const starting = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      const other = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      await other.set("k", Promise.resolve(entry("本文", { timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      await other.updateTags(["_N_T_/works/1"], { expire: 0 });

      expect(await starting.getExpiration(["_N_T_/works/1"])).toBe(Infinity);
      expect(await starting.get("k", ["_N_T_/works/1"])).toBeUndefined();
      expect(await starting.get("k", ["_N_T_/works/2"])).toBeDefined();
    });

    it("refreshTags のあとも getExpiration は Infinity を返し、暗黙のタグは get が確かめる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const handler = createCacheHandler({ redis: createMemoryRedis(), keyPrefix: "t", buildId: "b1" });
      await handler.set("k", Promise.resolve(entry("本文", { timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      await handler.updateTags(["_N_T_/works/1"], { expire: 0 });
      await handler.refreshTags();

      expect(await handler.getExpiration(["_N_T_/works/1"])).toBe(Infinity);
      expect(await handler.get("k", ["_N_T_/works/1"])).toBeUndefined();
      expect(await handler.get("k", ["_N_T_/works/2"])).toBeDefined();
    });

    it("前の破棄を書き込んでいる間に同じタグの破棄が来ても、あとの破棄の時刻で失効させる", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const redis = createMemoryRedis();
      const handler = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      // 1 回目の HSET は Redis に反映されるが、応答が 2 回目の破棄が来るまで返らない
      const { promise: firstWriteReleased, resolve: releaseFirstWrite } = Promise.withResolvers<void>();
      const hset = redis.hset.bind(redis);
      vi.spyOn(redis, "hset").mockImplementationOnce(async (...args) => {
        const result = await hset(...args);
        await firstWriteReleased;
        return result;
      });

      const first = handler.updateTags(["work:1"], { expire: 0 });
      vi.advanceTimersByTime(1000);
      // 1 回目の破棄のあと、2 回目の変更の前のデータで作られたエントリ
      await handler.set("k", Promise.resolve(entry("2 回目の変更の前", { tags: ["work:1"], timestamp: Date.now() })));
      vi.advanceTimersByTime(1000);
      const second = handler.updateTags(["work:1"], { expire: 0 });
      releaseFirstWrite();
      await Promise.all([first, second]);

      expect(await handler.get("k", [])).toBeUndefined();
    });

    it("別のタスクから種類の違う失効を同時に受けても、両方の失効が残る", async () => {
      vi.useFakeTimers({ now: 1_000_000 });
      const redis = createMemoryRedis();
      const taskA = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      const taskB = createCacheHandler({ redis, keyPrefix: "t", buildId: "b1" });
      await taskA.set("k", Promise.resolve(entry("本文", { tags: ["work:1"], timestamp: Date.now() })));

      vi.advanceTimersByTime(1000);
      // durations なしは expired だけ、expire なしの durations は stale だけを更新する
      await Promise.all([taskA.updateTags(["work:1"]), taskB.updateTags(["work:1"], {})]);

      expect(await taskA.get("k", [])).toBeUndefined();
    });

  });
});
