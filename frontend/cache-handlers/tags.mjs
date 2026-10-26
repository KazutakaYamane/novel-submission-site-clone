// @ts-check

/**
 * @typedef {import("./redis.mjs").RedisLike} RedisLike
 * @typedef {{ stale?: number; expired?: number }} TagState
 * @typedef {ReturnType<typeof createTagStore>} TagStore
 */

/** @type {WeakMap<RedisLike, Map<string, TagStore>>} */
const stores = new WeakMap();

/**
 * 同じ Redis と接頭辞には同じタグストアを返し、2 つのハンドラで共有する。Next.js の revalidateTag は
 * 2 つのハンドラを同期的に続けて呼ぶので、共有したストアで同じ書き込みを 1 回にまとめるため
 * @param {RedisLike} redis
 * @param {string} keyPrefix
 * @returns {TagStore}
 */
export function tagStoreFor(redis, keyPrefix) {
  const byPrefix = stores.get(redis) ?? new Map();
  stores.set(redis, byPrefix);
  const store = byPrefix.get(keyPrefix) ?? createTagStore(redis, keyPrefix);
  byPrefix.set(keyPrefix, store);
  return store;
}

/**
 * タグごとの失効時刻を Redis のハッシュに持つ。失効時刻は絶対時刻なのでビルドをまたいで共有し、
 * ローリングデプロイ中に旧ビルドのタスクが破棄を受けても、新ビルドのエントリが失効するようにする。
 * stale と expired は別のフィールドに置く。書き込みが自分の変えるフィールドの HSET 1 回で済み、
 * 別のタスクの書き込みと競合しても互いのフィールドを消さない
 * @param {RedisLike} redis
 * @param {string} keyPrefix
 */
function createTagStore(redis, keyPrefix) {
  const key = `${keyPrefix}:tags`;
  // 同じ同期処理の中で呼ばれた同じ内容の update だけをまとめる。書き込み中に来た別の破棄は、
  // それより前に作られたエントリを失効させるため、自分の時刻で書く
  /** @type {Map<string, Promise<void>>} */
  const sameTick = new Map();

  // durations の扱いは Next.js の既定のハンドラ(server/lib/cache-handlers/default.js)に合わせる。
  // Next.js はこれをレスポンスのあとに呼び、例外はログに出すだけなので、Redis が落ちていても
  // /api/revalidate は 200 を返し、Laravel はリトライしない
  /**
   * @param {string[]} tags
   * @param {{ expire?: number }} [durations]
   */
  async function write(tags, durations) {
    const now = Date.now();
    /** @type {Record<string, string>} */
    const fields = {};
    for (const tag of tags) {
      if (durations) {
        fields[`${tag}:stale`] = String(now);
        if (durations.expire !== undefined) fields[`${tag}:expired`] = String(now + durations.expire * 1000);
      } else {
        fields[`${tag}:expired`] = String(now);
      }
    }
    await redis.hset(key, fields);
  }

  return {
    /**
     * @param {string[]} tags
     * @returns {Promise<Map<string, TagState>>}
     */
    async read(tags) {
      if (tags.length === 0) return new Map();
      const raw = await redis.hmget(key, ...tags.flatMap((tag) => [`${tag}:stale`, `${tag}:expired`]));
      /** @type {Map<string, TagState>} */
      const states = new Map();
      tags.forEach((tag, i) => {
        const [stale, expired] = [raw[i * 2], raw[i * 2 + 1]];
        if (stale === null && expired === null) return;
        states.set(tag, {
          stale: stale === null ? undefined : Number(stale),
          expired: expired === null ? undefined : Number(expired),
        });
      });
      return states;
    },

    /**
     * @param {string[]} tags
     * @param {{ expire?: number }} [durations]
     */
    update(tags, durations) {
      if (tags.length === 0) return Promise.resolve();
      const id = JSON.stringify([tags, durations ?? null]);
      const running = sameTick.get(id);
      if (running) return running;
      const writing = write(tags, durations);
      sameTick.set(id, writing);
      queueMicrotask(() => sameTick.delete(id));
      return writing;
    },
  };
}

/**
 * @param {TagState | undefined} state
 * @param {number} createdAt
 */
export function isExpired(state, createdAt) {
  const expired = state?.expired;
  return expired !== undefined && expired <= Date.now() && expired > createdAt;
}

/**
 * @param {TagState | undefined} state
 * @param {number} createdAt
 */
export function isStale(state, createdAt) {
  return (state?.stale ?? 0) > createdAt;
}
