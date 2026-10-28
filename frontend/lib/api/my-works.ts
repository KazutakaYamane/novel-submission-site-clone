import "server-only";
import { isUuid } from "@/lib/uuid";
import type { WorkInput } from "@/lib/work-form";
import { getMine, writeMine, type WriteResult } from "./my-request";
import type { components, operations } from "./schema";

type WorkResource = components["schemas"]["WorkResource"];

export type MyWorkSummary = { id: string; title: string; published: boolean };

export type MyWork = {
  id: string;
  title: string;
  catchphrase: string | null;
  introduction: string;
  genre: WorkResource["genre"];
  status: WorkResource["status"];
  ratingFlags: WorkResource["rating_flags"];
  tags: string[];
};

type WorkResponse = { data: WorkResource };

export async function listMyWorks(): Promise<MyWorkSummary[]> {
  const body = await getMine<operations["myWorks.listWorks"]["responses"][200]["content"]["application/json"]>(
    "/my/works",
  );
  // 一覧は作品を指定しないので、403・404 は返らない
  if (!body) throw new Error("/my/works が 403 か 404 を返した");
  return body.data.map((work) => ({ id: work.id, title: work.title, published: work.published_at !== null }));
}

export async function getMyWork(workId: string): Promise<MyWork | null> {
  if (!isUuid(workId)) return null;
  const body = await getMine<WorkResponse>(`/my/works/${workId}`);
  if (!body) return null;
  const { data } = body;
  return {
    id: data.id,
    title: data.title,
    catchphrase: data.catchphrase,
    introduction: data.introduction,
    genre: data.genre,
    status: data.status,
    ratingFlags: data.rating_flags,
    tags: data.tags,
  };
}

export async function createWork(input: WorkInput): Promise<WriteResult<{ id: string }>> {
  return writeMine("/my/works", { method: "POST", body: input }, ({ data }: WorkResponse) => ({ id: data.id }));
}

export async function updateWork(workId: string, input: WorkInput): Promise<WriteResult<void>> {
  return writeMine(`/my/works/${workId}`, { method: "PATCH", body: input }, () => undefined);
}
