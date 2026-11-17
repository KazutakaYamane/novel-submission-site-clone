"use server";
import { refresh } from "next/cache";
import type { ActionState } from "@/lib/action-state";
import { publishEpisode, unpublishEpisode, updateEpisode } from "@/lib/api/my-episodes";
import { failedActionState } from "@/lib/api/my-request";
import { isUuid } from "@/lib/uuid";

// 上限は Laravel より緩くし、極端に大きい値を Laravel へ送らないためだけに置く。
// 本文は Laravel がバイト数で数える(episode_body_max_bytes)。文字数はバイト数以下なので、これを超える本文は必ず 422 になる
const MAX_TITLE_LENGTH = 1000;
const MAX_BODY_LENGTH = 6_990_474;

const INVALID: ActionState = { status: "error", message: "不正なリクエストです" };

// 「公開」は保存してから公開する。保存していない編集が、古い本文のまま公開されないように
export async function saveEpisodeAction(_: ActionState, form: FormData): Promise<ActionState> {
  const ids = episodeIds(form);
  const title = form.get("title");
  const body = form.get("body");
  const intent = form.get("intent");
  if (!ids || typeof title !== "string" || title.length > MAX_TITLE_LENGTH) return INVALID;
  if (typeof body !== "string" || body.length > MAX_BODY_LENGTH) return INVALID;
  if (intent !== "save" && intent !== "publish") return INVALID;

  const saved = await updateEpisode(ids.workId, ids.episodeId, { title, body });
  if (!saved.ok) return failedActionState(saved);
  // 公開に失敗しても保存は済んでいるので、保存した内容で描き直す
  refresh();
  if (intent === "save") return { status: "ok" };

  const published = await publishEpisode(ids.workId, ids.episodeId);
  if (!published.ok) return failedActionState(published);
  return { status: "ok" };
}

export async function unpublishEpisodeAction(_: ActionState, form: FormData): Promise<ActionState> {
  const ids = episodeIds(form);
  if (!ids) return INVALID;

  const result = await unpublishEpisode(ids.workId, ids.episodeId);
  if (!result.ok) return failedActionState(result);
  refresh();
  return { status: "ok" };
}

function episodeIds(form: FormData): { workId: string; episodeId: string } | null {
  const workId = form.get("workId");
  const episodeId = form.get("episodeId");
  if (typeof workId !== "string" || !isUuid(workId) || typeof episodeId !== "string" || !isUuid(episodeId)) return null;
  return { workId, episodeId };
}
