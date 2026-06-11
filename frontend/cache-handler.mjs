// ------------------------------------------------------------
// Next.js custom cache handler: ISR / Data Cache を Redis(ElastiCache)に置く
//
// 背景(ADR-INFRA 決定8 / knowledge doc §11.1):
//   Fargate では既定の .next/cache がタスクローカルかつ揮発のため、
//   - タスク間で ISR キャッシュが共有されず、配信内容が不揃いになる
//   - on-demand revalidate が他タスクに伝搬しない
//   全タスク共有の ElastiCache を backend にすることで解決する。
//
// 動作モード(REDIS_URL の有無で実行時に自動切替):
//   - あり(本番 ECS。Secrets Manager から注入): Redis backend
//   - なし(next build 時・ローカル dev): in-memory Map fallback
//   ※ next.config の cacheHandler パスはビルド時に standalone へ焼き込まれる
//     ため「本番だけ設定する」ことはできない。ハンドラ側で fallback する。
//
// タグ失効は tags-manifest 方式:
//   revalidateTag(tag) は「tag → 失効時刻」を記録するだけにし、get() で
//   entry.lastModified と比較して stale 判定する。tag → キー集合の二重管理が
//   不要で、revalidatePath が使う implicit tag(ctx.softTags)にも対応できる。
//
// キャッシュはベストエフォート: Redis 障害時は例外を握りつぶしてキャッシュ
// ミス扱いにし、ページ生成自体は止めない。
// ------------------------------------------------------------

import { createClient } from "redis";

const KEY_PREFIX = "next:cache:";
const TAGS_MANIFEST_KEY = "next:tags-manifest";

// ElastiCache(cache.t4g.micro, 約0.5GB)を溢れさせないための自己掃除 TTL。
// revalidate 間隔より十分長いので stale-while-revalidate は機能する。
const TTL_SECONDS = 60 * 60 * 24;

/** @type {import("redis").RedisClientType | null} */
let client = null;
/** @type {Promise<unknown> | null} */
let connecting = null;

async function getRedis() {
  if (!process.env.REDIS_URL) return null;
  if (client?.isReady) return client;
  if (!connecting) {
    client = createClient({ url: process.env.REDIS_URL });
    // 接続断のたびに例外で落とさない(再接続は redis クライアントに任せる)
    client.on("error", (err) =>
      console.error("[cache-handler] redis error:", err?.message ?? err),
    );
    connecting = client.connect().catch((err) => {
      console.error("[cache-handler] redis connect failed:", err?.message ?? err);
      client = null;
      connecting = null;
    });
  }
  await connecting;
  return client?.isReady ? client : null;
}

// CacheEntry には Buffer(RSC ペイロード等)が含まれ得る。
// JSON.stringify は Buffer を {type:"Buffer",data:[...]} に変換するので、
// parse 側の reviver で Buffer に復元する。
function parse(json) {
  return JSON.parse(json, (_key, value) =>
    value && value.type === "Buffer" && Array.isArray(value.data)
      ? Buffer.from(value.data)
      : value,
  );
}

// REDIS_URL なし(ビルド時・ローカル dev)用 fallback
const memoryCache = new Map();
const memoryTags = new Map();

export default class CacheHandler {
  constructor(options) {
    this.options = options;
  }

  async get(key, ctx = {}) {
    try {
      const redis = await getRedis();
      let entry;
      if (redis) {
        const json = await redis.get(KEY_PREFIX + key);
        if (!json) return null;
        entry = parse(json);
      } else {
        entry = memoryCache.get(key);
        if (!entry) return null;
      }

      // タグ失効チェック(明示タグ + revalidatePath 等の implicit タグ)
      const tags = [...(entry.tags ?? []), ...(ctx.softTags ?? [])];
      if (tags.length > 0) {
        let stamps;
        if (redis) {
          stamps = await redis.hmGet(TAGS_MANIFEST_KEY, tags);
        } else {
          stamps = tags.map((tag) => memoryTags.get(tag));
        }
        const revalidatedAt = Math.max(
          ...stamps.map((s) => (s ? Number(s) : 0)),
        );
        if (revalidatedAt >= (entry.lastModified ?? 0)) return null;
      }

      return entry;
    } catch (err) {
      console.error("[cache-handler] get failed:", err?.message ?? err);
      return null; // キャッシュミス扱い(生成にフォールバック)
    }
  }

  async set(key, data, ctx = {}) {
    try {
      const entry = {
        value: data,
        lastModified: Date.now(),
        tags: ctx.tags ?? [],
      };
      const redis = await getRedis();
      if (redis) {
        await redis.set(KEY_PREFIX + key, JSON.stringify(entry), {
          EX: TTL_SECONDS,
        });
      } else {
        memoryCache.set(key, entry);
      }
    } catch (err) {
      console.error("[cache-handler] set failed:", err?.message ?? err);
    }
  }

  async revalidateTag(tags) {
    try {
      const list = [tags].flat().filter(Boolean);
      if (list.length === 0) return;
      const now = Date.now();
      const redis = await getRedis();
      if (redis) {
        await redis.hSet(
          TAGS_MANIFEST_KEY,
          Object.fromEntries(list.map((tag) => [tag, String(now)])),
        );
      } else {
        for (const tag of list) memoryTags.set(tag, now);
      }
    } catch (err) {
      console.error("[cache-handler] revalidateTag failed:", err?.message ?? err);
    }
  }

  // リクエスト毎の一時メモリをリセットするフック。
  // このハンドラはリクエスト内キャッシュを持たないため何もしない。
  resetRequestCache() {}
}
