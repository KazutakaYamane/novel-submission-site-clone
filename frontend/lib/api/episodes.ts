import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { isUuid } from "@/lib/uuid";
import { apiFetch } from "./client";
import type { operations } from "./schema";

type EpisodeLink = { id: string; title: string };

export type PublishedEpisode = {
  id: string;
  title: string;
  body: string;
  work: { id: string; title: string };
  previous: EpisodeLink | null;
  next: EpisodeLink | null;
};

// URL の値はそのままキャッシュキーとタグになる。形の違う ID で Redis にエントリを増やさないよう、キャッシュの前で弾く
export async function getPublishedEpisode(workId: string, episodeId: string): Promise<PublishedEpisode | null> {
  if (!isUuid(workId) || !isUuid(episodeId)) return null;
  return getCachedPublishedEpisode(workId, episodeId);
}

// 前後の話は作品のほかの話の公開で変わるので、work:{workId} の破棄でも作り直す
async function getCachedPublishedEpisode(workId: string, episodeId: string): Promise<PublishedEpisode | null> {
  "use cache";
  cacheTag(`episode:${episodeId}`, `work:${workId}`);
  cacheLife("days");
  const res = await apiFetch(`/works/${workId}/episodes/${episodeId}`);
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`/works/{id}/episodes/{id} が ${res.status} を返した`);
  const { data }: operations["publicWorks.showPublicEpisode"]["responses"][200]["content"]["application/json"] =
    await res.json();
  return {
    id: data.id,
    title: data.title,
    body: data.body,
    work: { id: data.work.id, title: data.work.title },
    previous: data.previous && { id: data.previous.id, title: data.previous.title },
    next: data.next && { id: data.next.id, title: data.next.title },
  };
}
