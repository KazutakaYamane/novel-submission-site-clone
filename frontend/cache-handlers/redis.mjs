// @ts-check
import { readFileSync } from "node:fs";
import path from "node:path";
import Redis from "ioredis";

export const KEY_PREFIX = "next";

/**
 * @typedef {{
 *   get(key: string): Promise<string | null>;
 *   set: {
 *     (key: string, value: string): Promise<unknown>;
 *     (key: string, value: string, ex: "EX", seconds: number): Promise<unknown>;
 *   };
 *   del(key: string): Promise<number>;
 *   hset(key: string, fields: Record<string, string>): Promise<unknown>;
 *   hmget(key: string, ...fields: string[]): Promise<(string | null)[]>;
 * }} RedisLike
 */

/**
 * @param {string} url
 */
export function createRedisClient(url) {
  // Redis が落ちているときにリクエストを待たせすぎない。コマンドは接続を待つが、
  // 1 秒で失敗させ、ハンドラはキャッシュなしとして続ける
  const redis = new Redis(url, {
    connectTimeout: 1000,
    commandTimeout: 1000,
    maxRetriesPerRequest: 0,
  });
  // 再接続は数秒おきに続くので、切れたときに 1 回だけログに出す
  let reported = false;
  redis.on("error", (error) => {
    if (reported) return;
    reported = true;
    console.error("[cache-handlers] Redis の接続エラー", error);
  });
  redis.on("ready", () => {
    reported = false;
  });
  return redis;
}

const WRONGTYPE = "WRONGTYPE Operation against a key holding the wrong kind of value";

/**
 * ハンドラが使う Redis のコマンドだけを、プロセス内の Map で実装する
 * @returns {RedisLike}
 */
export function createMemoryRedis() {
  /** @type {Map<string, { type: "string"; value: string; expiresAt: number } | { type: "hash"; fields: Map<string, string> }>} */
  const store = new Map();

  /** @param {string} key */
  function lookup(key) {
    const item = store.get(key);
    if (item?.type === "string" && Date.now() >= item.expiresAt) {
      store.delete(key);
      return undefined;
    }
    return item;
  }

  /** @param {string} key */
  function hashOf(key) {
    const item = lookup(key);
    if (item && item.type !== "hash") throw new Error(WRONGTYPE);
    return item?.fields;
  }

  return {
    async get(key) {
      const item = lookup(key);
      if (!item) return null;
      if (item.type !== "string") throw new Error(WRONGTYPE);
      return item.value;
    },
    /**
     * @param {string} key
     * @param {string} value
     * @param {"EX"} [_ex]
     * @param {number} [ttl]
     */
    async set(key, value, _ex, ttl) {
      if (ttl !== undefined && (!Number.isInteger(ttl) || ttl <= 0)) {
        throw new Error("ERR invalid expire time in 'set' command");
      }
      store.set(key, { type: "string", value, expiresAt: Date.now() + (ttl ?? Infinity) * 1000 });
      return "OK";
    },
    async del(key) {
      const existed = lookup(key) !== undefined;
      store.delete(key);
      return existed ? 1 : 0;
    },
    async hset(key, fields) {
      const hash = hashOf(key) ?? new Map();
      let added = 0;
      for (const [field, value] of Object.entries(fields)) {
        if (!hash.has(field)) added++;
        hash.set(field, value);
      }
      store.set(key, { type: "hash", fields: hash });
      return added;
    },
    async hmget(key, ...fields) {
      const hash = hashOf(key);
      return fields.map((field) => hash?.get(field) ?? null);
    },
  };
}

/**
 * next build の最中は Redis に書かない。本番のイメージのビルドには Redis がなく、
 * compose の web でビルドしたときも、まだ確定していないビルド ID のキーを作らないため。
 * 実行時に REDIS_URL がないのは設定の誤りなので、上限のないメモリにためずにエラーにする
 * @param {Record<string, string | undefined>} env
 * @returns {RedisLike}
 */
export function redisFor(env) {
  if (env.NEXT_PHASE === "phase-production-build") return createMemoryRedis();
  if (!env.REDIS_URL) throw new Error("[cache-handlers] REDIS_URL が設定されていない");
  return createRedisClient(env.REDIS_URL);
}

/** @type {RedisLike | undefined} */
let shared;

/**
 * 2 つのハンドラで 1 本の接続を使う
 * @returns {RedisLike}
 */
export function sharedRedis() {
  shared ??= redisFor(process.env);
  return shared;
}

/**
 * next dev は、compose の .next に残った本番ビルドの BUILD_ID を使わず、開発用の名前空間に書く。
 * 実行時に読めないのは設定の誤りなので、別のビルドと名前空間を共有せずにエラーにする。
 * next build の最中は Redis を使わない(redisFor)ので、名前空間は使われない
 * @param {string} [cwd]
 * @param {Record<string, string | undefined>} [env]
 */
export function readBuildId(cwd = process.cwd(), env = process.env) {
  if (env.NODE_ENV === "development") return "development";
  if (env.NEXT_PHASE === "phase-production-build") return "build";
  try {
    return readFileSync(path.join(cwd, ".next", "BUILD_ID"), "utf8").trim();
  } catch (error) {
    throw new Error("[cache-handlers] .next/BUILD_ID を読めない", { cause: error });
  }
}

/**
 * Next.js が読むハンドラの設定。Redis への接続と BUILD_ID の読み込みは、モジュールの import ではなく
 * 最初に使うときに行う。単体テストが同じモジュールのファクトリを import するため
 */
export function runtimeOptions() {
  return { redis: sharedRedis(), keyPrefix: KEY_PREFIX, buildId: readBuildId() };
}

// ElastiCache の maxmemory-policy(default.redis7 の volatile-lru)は TTL のないキーを追い出さないので、
// エントリには必ず TTL を付ける。キーにビルド ID が入り、デプロイのたびに前のビルドのキーが残るため、
// 上限も 7 日にする(cacheLife を付けない "use cache" の expire は約 136 年になる)
const MAX_TTL_SECONDS = 7 * 24 * 60 * 60;

/**
 * Next.js は expire を過ぎたエントリを使わないので、TTL もそこまでにする。expire がないときは上限まで置く。
 * Redis の EX は正の整数しか受け付けないので切り上げる
 * @param {number | undefined} expire 秒
 */
export function ttlSeconds(expire) {
  if (expire === undefined || !(expire > 0)) return MAX_TTL_SECONDS;
  return Math.min(Math.ceil(expire), MAX_TTL_SECONDS);
}
