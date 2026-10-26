// @ts-check
// "use cache" の結果を置く cacheHandlers.default
import { runtimeOptions, ttlSeconds } from "./redis.mjs";
import { isExpired, isStale, tagStoreFor } from "./tags.mjs";

/**
 * @typedef {import("next/dist/server/lib/cache-handlers/types").CacheEntry} CacheEntry
 * @typedef {import("next/dist/server/lib/cache-handlers/types").CacheHandler} CacheHandler
 * @typedef {import("./redis.mjs").RedisLike} RedisLike
 */

/**
 * @param {ReadableStream<Uint8Array>} stream
 */
async function readAll(stream) {
  return Buffer.from(await new Response(stream).arrayBuffer());
}

/**
 * @param {{ redis: RedisLike; keyPrefix: string; buildId: string }} options
 * @returns {CacheHandler}
 */
export function createCacheHandler({ redis, keyPrefix, buildId }) {
  /** @param {string} cacheKey */
  const entryKey = (cacheKey) => `${keyPrefix}:use-cache:${buildId}:entry:${cacheKey}`;
  const tagStore = tagStoreFor(redis, keyPrefix);
  /** @type {Map<string, Promise<void>>} */
  const pendingSets = new Map();

  /**
   * @param {string} cacheKey
   * @param {Promise<CacheEntry>} pendingEntry
   */
  async function save(cacheKey, pendingEntry) {
    const { value, ...meta } = await pendingEntry;
    // expire が 0 のエントリは Next.js が読むたびに作り直すので、保存しても使われない
    if (meta.expire === 0) {
      await value.cancel();
      return;
    }
    const bytes = await readAll(value);
    await redis.set(entryKey(cacheKey), JSON.stringify({ ...meta, value: bytes.toString("base64") }), "EX", ttlSeconds(meta.expire));
  }

  return {
    // Next.js は get の例外をキャッシュなしとして扱わず、描画のエラーにする
    async get(cacheKey, softTags) {
      await pendingSets.get(cacheKey);
      try {
        const raw = await redis.get(entryKey(cacheKey));
        if (!raw) return undefined;
        const { value, ...meta } = JSON.parse(raw);
        // タグの失効はプロセス内に写さず、読むたびに Redis で確かめる。写しは起動直後や Redis の瞬断のあと
        // 古いことがあり、その間に破棄済みのエントリを返さないため
        const states = await tagStore.read([...meta.tags, ...softTags]);
        let revalidate = meta.revalidate;
        for (const state of states.values()) {
          if (isExpired(state, meta.timestamp)) return undefined;
          // 失効の期限がまだ先のタグは、エントリを返しつつ Next.js に作り直しを求める
          if (isStale(state, meta.timestamp)) revalidate = -1;
        }
        const bytes = Buffer.from(value, "base64");
        return {
          ...meta,
          revalidate,
          value: new ReadableStream({
            start(controller) {
              controller.enqueue(new Uint8Array(bytes));
              controller.close();
            },
          }),
        };
      } catch (error) {
        console.error("[cache-handlers/default] get に失敗", cacheKey, error);
        return undefined;
      }
    },

    async set(cacheKey, pendingEntry) {
      // 同じキーの get がこれを待つので、失敗しても reject しない
      const saving = save(cacheKey, pendingEntry).catch((error) => {
        console.error("[cache-handlers/default] set に失敗", cacheKey, error);
      });
      pendingSets.set(cacheKey, saving);
      await saving;
      if (pendingSets.get(cacheKey) === saving) pendingSets.delete(cacheKey);
    },

    // タグの失効は get で Redis から読むので、リクエストの前に読み直すものはない
    async refreshTags() {},

    // Infinity を返すと、Next.js は暗黙のタグ(_N_T_/...)を get に渡し、その判定を get に任せる
    async getExpiration() {
      return Infinity;
    },

    async updateTags(tags, durations) {
      await tagStore.update(tags, durations);
    },
  };
}

/** @type {CacheHandler | undefined} */
let handler;
const runtime = () => (handler ??= createCacheHandler(runtimeOptions()));

/** @type {CacheHandler} */
const redisCacheHandler = {
  get: (cacheKey, softTags) => runtime().get(cacheKey, softTags),
  set: (cacheKey, pendingEntry) => runtime().set(cacheKey, pendingEntry),
  refreshTags: () => runtime().refreshTags(),
  getExpiration: (tags) => runtime().getExpiration(tags),
  updateTags: (tags, durations) => runtime().updateTags(tags, durations),
};

export default redisCacheHandler;
