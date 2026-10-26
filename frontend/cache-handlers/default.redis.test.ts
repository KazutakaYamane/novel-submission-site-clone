import { randomUUID } from "node:crypto";
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { createCacheHandler } from "./default.mjs";
import { createRedisClient } from "./redis.mjs";

const DAY = 60 * 60 * 24;

function entry(body: string, tags: string[] = []) {
  return {
    value: new ReadableStream<Uint8Array>({
      start(controller) {
        controller.enqueue(new TextEncoder().encode(body));
        controller.close();
      },
    }),
    tags,
    stale: 300,
    timestamp: Date.now(),
    expire: 7 * DAY,
    revalidate: DAY,
  };
}

async function readBody(stream: ReadableStream<Uint8Array>) {
  return new Response(stream).text();
}

// compose の web コンテナ(REDIS_URL がある)で実行する
describe.runIf(process.env.REDIS_URL)("Redis に繋いだ cacheHandlers.default", () => {
  const redis = createRedisClient(process.env.REDIS_URL!);
  // 開発中のキャッシュと混ざらないよう、テストごとに接頭辞を変え、終わったら自分のキーだけを消す
  const keyPrefix = `test:${randomUUID()}`;

  beforeAll(async () => {
    if (redis.status !== "ready") await new Promise((resolve) => redis.once("ready", resolve));
  });

  afterAll(async () => {
    const keys = await redis.keys(`${keyPrefix}:*`);
    if (keys.length > 0) await redis.del(...keys);
    redis.disconnect();
  });

  it("保存したエントリを読み出すと同じ値が返り、Redis のキーに expire の TTL が付く", async () => {
    const handler = createCacheHandler({ redis, keyPrefix, buildId: "b1" });

    await handler.set("k", Promise.resolve(entry("作品の本文")));
    const got = await handler.get("k", []);

    expect(await readBody(got!.value)).toBe("作品の本文");
    expect(await redis.ttl(`${keyPrefix}:use-cache:b1:entry:k`)).toBeGreaterThan(7 * DAY - 10);
  });

  it("接続した直後に呼んでも、保存して読み出せる", async () => {
    const fresh = createRedisClient(process.env.REDIS_URL!);
    const handler = createCacheHandler({ redis: fresh, keyPrefix, buildId: "fresh" });

    await handler.set("k", Promise.resolve(entry("起動直後の本文")));
    const got = await handler.get("k", []);
    fresh.disconnect();

    expect(await readBody(got!.value)).toBe("起動直後の本文");
  });

  it("あるハンドラでタグを失効させると、別のハンドラでもそのタグのエントリだけが返らなくなる", async () => {
    const taskA = createCacheHandler({ redis, keyPrefix, buildId: "b1" });
    const taskB = createCacheHandler({ redis, keyPrefix, buildId: "b1" });
    await taskB.set("work1", Promise.resolve(entry("作品1", ["work:1"])));
    await taskB.set("work2", Promise.resolve(entry("作品2", ["work:2"])));

    await new Promise((resolve) => setTimeout(resolve, 5));
    await taskA.updateTags(["work:1"], { expire: 0 });
    await taskB.refreshTags();

    expect(await taskB.get("work1", [])).toBeUndefined();
    expect(await taskB.get("work2", [])).toBeDefined();
  });
});

describe("Redis に繋がらないときの cacheHandlers.default", () => {
  let redis: ReturnType<typeof createRedisClient>;
  let handler: ReturnType<typeof createCacheHandler>;

  beforeAll(() => {
    // 再接続のたびに出る接続エラーのログでテストの出力を埋めない
    vi.spyOn(console, "error").mockImplementation(() => {});
    redis = createRedisClient("redis://127.0.0.1:1");
    handler = createCacheHandler({ redis, keyPrefix: "down", buildId: "b1" });
  });

  afterEach(() => {
    vi.mocked(console.error).mockClear();
  });

  afterAll(() => {
    redis.disconnect();
    vi.restoreAllMocks();
  });

  it("get はキャッシュなしとして undefined を返す", async () => {
    expect(await handler.get("k", [])).toBeUndefined();
  });

  it("set は例外を投げずにログを出す", async () => {
    await expect(handler.set("k", Promise.resolve(entry("本文")))).resolves.toBeUndefined();
    expect(console.error).toHaveBeenCalledWith("[cache-handlers/default] set に失敗", "k", expect.any(Error));
  });

  it("再接続に失敗し続けても、接続エラーのログは切れたときに 1 回だけ出す", async () => {
    const other = createRedisClient("redis://127.0.0.1:1");
    await new Promise((resolve) => setTimeout(resolve, 500));
    other.disconnect();

    const connectionErrors = vi.mocked(console.error).mock.calls.filter(([message]) => message === "[cache-handlers] Redis の接続エラー");
    expect(connectionErrors).toHaveLength(1);
  });

  it("refreshTags は例外を投げず、それまでのタグの状態のまま動く", async () => {
    await expect(handler.refreshTags()).resolves.toBeUndefined();
  });
});
