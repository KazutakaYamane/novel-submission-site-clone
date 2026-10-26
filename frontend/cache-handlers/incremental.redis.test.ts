import { randomUUID } from "node:crypto";
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { createIncrementalCacheHandler } from "./incremental.mjs";
import { createRedisClient } from "./redis.mjs";

const page = { kind: "APP_PAGE", html: "<p>作品</p>", rscData: Buffer.from("rsc"), headers: {}, status: 200 };

// compose の web コンテナ(REDIS_URL がある)で実行する
describe.runIf(process.env.REDIS_URL)("Redis に繋いだ cacheHandler", () => {
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

  it("ページのキーに TTL が付く(Redis の volatile-lru は TTL のないキーを追い出さない)", async () => {
    const handler = createIncrementalCacheHandler({ redis, keyPrefix, buildId: "b1" });

    await handler.set("/ttl", page, { tags: [], cacheControl: { revalidate: 60, expire: 3600 } });

    const ttl = await redis.ttl(`${keyPrefix}:incremental:b1:entry:/ttl`);
    expect(ttl).toBeGreaterThan(3590);
    expect(ttl).toBeLessThanOrEqual(3600);
  });

  it("あるタスクでタグを失効させると、別のタスクでそのタグのページが返らなくなる", async () => {
    const taskA = createIncrementalCacheHandler({ redis, keyPrefix, buildId: "b1" });
    const taskB = createIncrementalCacheHandler({ redis, keyPrefix, buildId: "b1" });
    await taskB.set("/works/1", page, { tags: ["work:1"] });
    await taskB.set("/works/2", page, { tags: ["work:2"] });
    expect(await taskB.get("/works/1", {})).toMatchObject({ value: page });

    await new Promise((resolve) => setTimeout(resolve, 5));
    await taskA.revalidateTag(["work:1"], { expire: 0 });

    expect(await taskB.get("/works/1", {})).toBeNull();
    expect(await taskB.get("/works/2", {})).toMatchObject({ value: page });
  });
});

describe("Redis に繋がらないときの cacheHandler", () => {
  let redis: ReturnType<typeof createRedisClient>;
  let handler: ReturnType<typeof createIncrementalCacheHandler>;

  beforeAll(() => {
    // 再接続のたびに出る接続エラーのログでテストの出力を埋めない
    vi.spyOn(console, "error").mockImplementation(() => {});
    redis = createRedisClient("redis://127.0.0.1:1");
    handler = createIncrementalCacheHandler({ redis, keyPrefix: "down", buildId: "b1" });
  });

  afterEach(() => {
    vi.mocked(console.error).mockClear();
  });

  afterAll(() => {
    redis.disconnect();
    vi.restoreAllMocks();
  });

  it("get はキャッシュなしとして null を返す", async () => {
    expect(await handler.get("/works/1", {})).toBeNull();
  });

  it("set は例外を投げずにログを出す", async () => {
    await expect(handler.set("/works/1", page, { tags: [] })).resolves.toBeUndefined();
    expect(console.error).toHaveBeenCalledWith("[cache-handlers/incremental] set に失敗", "/works/1", expect.any(Error));
  });
});
