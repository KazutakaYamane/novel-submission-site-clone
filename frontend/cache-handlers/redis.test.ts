import { randomUUID } from "node:crypto";
import { mkdirSync, mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createMemoryRedis, createRedisClient, readBuildId, redisFor, type RedisLike } from "./redis.mjs";

// メモリの実装が、ハンドラの使うコマンドで Redis と同じに振る舞うことを、同じテストで確かめる
const implementations: [string, () => RedisLike & { disconnect?: () => void }][] = [["メモリ", createMemoryRedis]];
if (process.env.REDIS_URL) implementations.push(["Redis", () => createRedisClient(process.env.REDIS_URL!)]);

describe.each(implementations)("%s の RedisLike", (_name, create) => {
  const redis = create();
  // 開発中のキャッシュと混ざらないよう、テストごとに接頭辞を変え、終わったら自分のキーだけを消す
  const prefix = `test:${randomUUID()}`;
  const keys: string[] = [];
  const key = (name: string) => {
    keys.push(`${prefix}:${name}`);
    return `${prefix}:${name}`;
  };

  beforeAll(async () => {
    await redis.get(key("warmup"));
  });

  afterAll(async () => {
    for (const k of keys) await redis.del(k);
    redis.disconnect?.();
  });

  it("set した値を get で読み、del で消す", async () => {
    const k = key("string");
    await redis.set(k, "値");
    expect(await redis.get(k)).toBe("値");

    expect(await redis.del(k)).toBe(1);
    expect(await redis.get(k)).toBeNull();
    expect(await redis.del(k)).toBe(0);
  });

  it("EX に 0 を渡すとエラーになる", async () => {
    await expect(redis.set(key("ex0"), "値", "EX", 0)).rejects.toThrow(/invalid expire time/);
  });

  it("ハッシュは hset で書き、hmget で読み、del で消す", async () => {
    const k = key("hash");
    await redis.hset(k, { a: "1", b: "2" });
    await redis.hset(k, { b: "3" });

    expect(await redis.hmget(k, "a", "b", "x")).toEqual(["1", "3", null]);
    expect(await redis.del(k)).toBe(1);
    expect(await redis.hmget(k, "a")).toEqual([null]);
  });

  it("型の違うキーへの読み書きは WRONGTYPE で失敗し、set はハッシュを上書きする", async () => {
    const hash = key("wrongtype-hash");
    const string = key("wrongtype-string");
    await redis.hset(hash, { a: "1" });
    await redis.set(string, "値");

    await expect(redis.get(hash)).rejects.toThrow(/WRONGTYPE/);
    await expect(redis.hmget(string, "a")).rejects.toThrow(/WRONGTYPE/);
    await expect(redis.hset(string, { a: "1" })).rejects.toThrow(/WRONGTYPE/);

    await redis.set(hash, "上書き");
    expect(await redis.get(hash)).toBe("上書き");
  });
});

describe("readBuildId", () => {
  function projectWithBuildId(buildId: string) {
    const dir = mkdtempSync(path.join(tmpdir(), "build-id-"));
    mkdirSync(path.join(dir, ".next"));
    writeFileSync(path.join(dir, ".next", "BUILD_ID"), `${buildId}\n`);
    return dir;
  }

  it("本番では .next/BUILD_ID を読む", () => {
    expect(readBuildId(projectWithBuildId("abc123"), { NODE_ENV: "production" })).toBe("abc123");
  });

  it("実行時に BUILD_ID が読めなければ、別のビルドと名前空間を共有せずにエラーにする", () => {
    const empty = mkdtempSync(path.join(tmpdir(), "build-id-"));

    expect(() => readBuildId(empty, { NODE_ENV: "production" })).toThrow(/BUILD_ID/);
  });

  it("next build の最中は BUILD_ID がなくてもエラーにしない(Redis を使わない)", () => {
    const empty = mkdtempSync(path.join(tmpdir(), "build-id-"));

    expect(() => readBuildId(empty, { NODE_ENV: "production", NEXT_PHASE: "phase-production-build" })).not.toThrow();
  });

  it("next dev では、残っている本番ビルドの BUILD_ID を使わない", () => {
    expect(readBuildId(projectWithBuildId("abc123"), { NODE_ENV: "development" })).toBe("development");
  });
});

describe("redisFor", () => {
  it("next build の最中は、REDIS_URL があってもメモリの実装を使う", async () => {
    const redis = redisFor({ NEXT_PHASE: "phase-production-build", REDIS_URL: "redis://127.0.0.1:1" });

    await redis.set("k", "値");
    expect(await redis.get("k")).toBe("値");
  });

  it("実行時に REDIS_URL がなければ、メモリにためずにエラーにする", () => {
    expect(() => redisFor({})).toThrow(/REDIS_URL/);
  });
});
