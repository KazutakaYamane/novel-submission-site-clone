import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { isUuid } from "@/lib/uuid";
import { apiFetch } from "./client";
import type { components, operations } from "./schema";

type PublicWork = components["schemas"]["PublicWorkResource"];

export type PublishedWork = {
  id: string;
  title: string;
  authorName: string;
  catchphrase: string | null;
  introduction: string;
  genre: PublicWork["genre"];
  status: PublicWork["status"];
  ratingFlags: PublicWork["rating_flags"];
  tags: string[];
  lastEpisodePublishedAt: string | null;
  episodes: { id: string; title: string; publishedAt: string | null }[];
};

// URL の値はそのままキャッシュキーとタグになる。形の違う ID で Redis にエントリを増やさないよう、キャッシュの前で弾く
export async function getPublishedWork(workId: string): Promise<PublishedWork | null> {
  if (!isUuid(workId)) return null;
  return getCachedPublishedWork(workId);
}

// 公開データ。ユーザー情報を含まないので共有キャッシュに置き、Laravel が work:{id} を破棄する
async function getCachedPublishedWork(workId: string): Promise<PublishedWork | null> {
  "use cache";
  cacheTag(`work:${workId}`);
  cacheLife("days");
  const res = await apiFetch(`/works/${workId}`);
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`/works/{id} が ${res.status} を返した`);
  const { data }: operations["publicWorks.showPublicWork"]["responses"][200]["content"]["application/json"] =
    await res.json();
  return {
    id: data.id,
    title: data.title,
    authorName: data.author.name,
    catchphrase: data.catchphrase,
    introduction: data.introduction,
    genre: data.genre,
    status: data.status,
    ratingFlags: data.rating_flags,
    tags: data.tags,
    lastEpisodePublishedAt: data.last_episode_published_at,
    episodes: data.episodes.map((episode) => ({
      id: episode.id,
      title: episode.title,
      publishedAt: episode.published_at,
    })),
  };
}
