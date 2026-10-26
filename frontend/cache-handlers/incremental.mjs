// @ts-check
// 事前描画したページ(APP_PAGE)などを置く cacheHandler。Cache Components の構成でも Next.js が使う
import { runtimeOptions, ttlSeconds } from "./redis.mjs";
import { isExpired, isStale, tagStoreFor } from "./tags.mjs";

/**
 * @typedef {import("./redis.mjs").RedisLike} RedisLike
 * @typedef {{ lastModified: number; value: unknown }} CachedPage
 * @typedef {{
 *   get(key: string, ctx: unknown): Promise<CachedPage | null>;
 *   set(key: string, data: unknown, ctx?: { tags?: string[]; cacheControl?: { revalidate?: number | false; expire?: number } }): Promise<void>;
 *   revalidateTag(tags: string | string[], durations?: { expire?: number }): Promise<void>;
 *   resetRequestCache(): void;
 * }} IncrementalCacheHandler
 */

// Buffer と Map(APP_PAGE の segmentData)は JSON のままでは復元できない
/**
 * @this {Record<string, unknown>}
 * @param {string} key
 * @param {unknown} value
 */
function replacer(key, value) {
  const raw = this[key];
  if (Buffer.isBuffer(raw)) return { __type: "Buffer", data: raw.toString("base64") };
  if (raw instanceof Map) return { __type: "Map", entries: [...raw.entries()] };
  return value;
}

/**
 * @param {string} _key
 * @param {any} value
 */
function reviver(_key, value) {
  if (value?.__type === "Buffer") return Buffer.from(value.data, "base64");
  if (value?.__type === "Map") return new Map(value.entries);
  return value;
}

// 事前描画したページでは、Next.js は set の ctx.tags を空で渡し、タグ(暗黙のタグと、静的シェルで使った
// "use cache" の cacheTag)はページのヘッダーに入れる。Next.js のファイルキャッシュもここから読む
/** @param {any} value */
function tagsInHeader(value) {
  const header = value?.headers?.["x-next-cache-tags"];
  return typeof header === "string" ? header.split(",") : [];
}

/**
 * @param {{ redis: RedisLike; keyPrefix: string; buildId: string }} options
 * @returns {IncrementalCacheHandler}
 */
export function createIncrementalCacheHandler({ redis, keyPrefix, buildId }) {
  /** @param {string} key */
  const entryKey = (key) => `${keyPrefix}:incremental:${buildId}:entry:${key}`;
  const tagStore = tagStoreFor(redis, keyPrefix);

  return {
    // Next.js はこのハンドラの get の例外を描画のエラーにするので、読めないときはキャッシュなしとする
    async get(key) {
      try {
        const raw = await redis.get(entryKey(key));
        if (!raw) return null;
        const entry = JSON.parse(raw, reviver);
        const states = await tagStore.read([...entry.tags, ...tagsInHeader(entry.value)]);
        // このハンドラからは Next.js に「返しつつ作り直す」を伝えられないので、期限が先のタグでも作り直させる
        for (const state of states.values()) {
          if (isExpired(state, entry.lastModified) || isStale(state, entry.lastModified)) return null;
        }
        return { lastModified: entry.lastModified, value: entry.value };
      } catch (error) {
        console.error("[cache-handlers/incremental] get に失敗", key, error);
        return null;
      }
    },

    async set(key, data, ctx) {
      try {
        if (data === null) {
          await redis.del(entryKey(key));
          return;
        }
        const entry = { lastModified: Date.now(), tags: ctx?.tags ?? [], value: data };
        await redis.set(entryKey(key), JSON.stringify(entry, replacer), "EX", ttlSeconds(ctx?.cacheControl?.expire));
      } catch (error) {
        console.error("[cache-handlers/incremental] set に失敗", key, error);
      }
    },

    async revalidateTag(tags, durations) {
      await tagStore.update([tags].flat(), durations);
    },

    resetRequestCache() {},
  };
}

/** @type {IncrementalCacheHandler | undefined} */
let handler;
const runtime = () => (handler ??= createIncrementalCacheHandler(runtimeOptions()));

// Next.js はリクエストごとに new する。ハンドラはモジュールで 1 つにし、Redis への接続と BUILD_ID の読み込みは
// 最初に使うときに行う
export default class RedisIncrementalCacheHandler {
  /** @param {Parameters<IncrementalCacheHandler["get"]>} args */
  get(...args) {
    return runtime().get(...args);
  }

  /** @param {Parameters<IncrementalCacheHandler["set"]>} args */
  set(...args) {
    return runtime().set(...args);
  }

  /** @param {Parameters<IncrementalCacheHandler["revalidateTag"]>} args */
  revalidateTag(...args) {
    return runtime().revalidateTag(...args);
  }

  resetRequestCache() {}
}
