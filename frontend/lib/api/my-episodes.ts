import "server-only";
import { isUuid } from "@/lib/uuid";
import { getMine, writeMine, type WriteResult } from "./my-request";
import type { components, operations } from "./schema";

type EpisodeStatus = components["schemas"]["EpisodeStatus"];
type EpisodeResponse = { data: components["schemas"]["EpisodeResource"] };

export type MyEpisodeSummary = { id: string; title: string; status: EpisodeStatus };

export type MyEpisode = {
  id: string;
  title: string;
  body: string;
  status: EpisodeStatus;
  publicPath: string | null;
};

export async function listMyEpisodes(workId: string): Promise<MyEpisodeSummary[] | null> {
  if (!isUuid(workId)) return null;
  const body = await getMine<operations["myWorks.listEpisodes"]["responses"][200]["content"]["application/json"]>(
    `/my/works/${workId}/episodes`,
  );
  return body && body.data.map((episode) => ({ id: episode.id, title: episode.title, status: episode.status }));
}

export async function getMyEpisode(workId: string, episodeId: string): Promise<MyEpisode | null> {
  if (!isUuid(workId) || !isUuid(episodeId)) return null;
  const body = await getMine<EpisodeResponse>(`/my/works/${workId}/episodes/${episodeId}`);
  if (!body) return null;
  const { data } = body;
  return { id: data.id, title: data.title, body: data.body, status: data.status, publicPath: data.public_path };
}

export async function createEpisode(workId: string, input: { title: string }): Promise<WriteResult<{ id: string }>> {
  return writeMine(`/my/works/${workId}/episodes`, { method: "POST", body: input }, ({ data }: EpisodeResponse) => ({
    id: data.id,
  }));
}

export async function updateEpisode(
  workId: string,
  episodeId: string,
  input: { title: string; body: string },
): Promise<WriteResult<void>> {
  return writeMine(`/my/works/${workId}/episodes/${episodeId}`, { method: "PATCH", body: input }, () => undefined);
}

export async function publishEpisode(workId: string, episodeId: string): Promise<WriteResult<void>> {
  return writeMine(`/my/works/${workId}/episodes/${episodeId}/publish`, { method: "POST" }, () => undefined);
}

export async function unpublishEpisode(workId: string, episodeId: string): Promise<WriteResult<void>> {
  return writeMine(`/my/works/${workId}/episodes/${episodeId}/unpublish`, { method: "POST" }, () => undefined);
}
