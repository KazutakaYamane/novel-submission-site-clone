import { readFileSync } from "node:fs";
import path from "node:path";
import Redis from "ioredis";

// ECS の複数タスクで ISR キャッシュを共有するため Redis に置く。
// キーにビルド ID を含め、デプロイ後は新ビルドのキャッシュだけを読む。
// REDIS_URL がないとき(ビルド時)は Redis に接続せず、プロセス内の Map を使う。
const redis = process.env.REDIS_URL
  ? new Redis(process.env.REDIS_URL, { maxRetriesPerRequest: 2 })
  : memoryStore();

function memoryStore() {
  const m = new Map();
  return {
    get: async (k) => m.get(k) ?? null,
    set: async (k, v) => void m.set(k, String(v)),
    del: async (k) => void m.delete(k),
  };
}

function buildId() {
  try {
    return readFileSync(path.join(process.cwd(), ".next", "BUILD_ID"), "utf8").trim();
  } catch {
    return "dev";
  }
}

const prefix = `next:${buildId()}`;
const entryKey = (key) => `${prefix}:entry:${key}`;
const tagKey = (tag) => `${prefix}:tag:${tag}`;

// Buffer と Map(APP_PAGE の segmentData)は JSON のままでは復元できない
function replacer(k, v) {
  const raw = this[k];
  if (Buffer.isBuffer(raw)) return { __type: "Buffer", data: raw.toString("base64") };
  if (raw instanceof Map) return { __type: "Map", entries: [...raw.entries()] };
  return v;
}

function reviver(_k, v) {
  if (v?.__type === "Buffer") return Buffer.from(v.data, "base64");
  if (v?.__type === "Map") return new Map(v.entries);
  return v;
}

export default class CacheHandler {
  async get(key) {
    const raw = await redis.get(entryKey(key));
    if (!raw) return null;
    const entry = JSON.parse(raw, reviver);
    for (const tag of entry.tags) {
      const revalidatedAt = Number(await redis.get(tagKey(tag)));
      if (revalidatedAt > entry.lastModified) return null;
    }
    return { lastModified: entry.lastModified, value: entry.value };
  }

  async set(key, data, ctx) {
    if (data === null) {
      await redis.del(entryKey(key));
      return;
    }
    const entry = { lastModified: Date.now(), tags: ctx?.tags ?? [], value: data };
    await redis.set(entryKey(key), JSON.stringify(entry, replacer));
  }

  async revalidateTag(tags) {
    const now = Date.now();
    await Promise.all([tags].flat().map((tag) => redis.set(tagKey(tag), now)));
  }

  resetRequestCache() {}
}
