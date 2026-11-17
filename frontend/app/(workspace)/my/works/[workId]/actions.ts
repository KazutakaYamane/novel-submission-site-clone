"use server";
import { refresh } from "next/cache";
import { redirect } from "next/navigation";
import type { ActionState } from "@/lib/action-state";
import { createEpisode } from "@/lib/api/my-episodes";
import { failedActionState } from "@/lib/api/my-request";
import { updateWork } from "@/lib/api/my-works";
import { isUuid } from "@/lib/uuid";
import { parseWorkForm } from "@/lib/work-form";

// 誰でも任意の引数で呼べる前提で、形だけ検証する。所有者の確認は Laravel がする
export async function updateWorkAction(_: ActionState, form: FormData): Promise<ActionState> {
  const workId = form.get("workId");
  if (typeof workId !== "string" || !isUuid(workId)) return { status: "error", message: "不正なリクエストです" };
  const parsed = parseWorkForm(form);
  if (!parsed.ok) return parsed.state;

  const result = await updateWork(workId, parsed.input);
  if (!result.ok) return failedActionState(result);
  refresh();
  return { status: "ok" };
}

// 上限は Laravel の max:100 より緩くし、極端に大きい値を Laravel へ送らないためだけに置く
const MAX_TITLE_LENGTH = 1000;

export async function createEpisodeAction(_: ActionState, form: FormData): Promise<ActionState> {
  const workId = form.get("workId");
  const title = form.get("title");
  if (typeof workId !== "string" || !isUuid(workId) || typeof title !== "string" || title.length > MAX_TITLE_LENGTH) {
    return { status: "error", message: "不正なリクエストです" };
  }

  const result = await createEpisode(workId, { title });
  if (!result.ok) return failedActionState(result);
  redirect(`/my/works/${workId}/episodes/${result.data.id}`);
}
