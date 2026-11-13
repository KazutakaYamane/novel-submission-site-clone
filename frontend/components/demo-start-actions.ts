"use server";
import { redirect } from "next/navigation";
import type { ActionState } from "@/lib/action-state";
import { startDemoSession, type DemoRole } from "@/lib/api/demo";
import { setToken } from "@/lib/auth";

const MESSAGES = {
  rate_limited: "1 時間に始められるデモの回数の上限に達しました。しばらく待ってから試してください",
  failed: "デモを始められませんでした。時間をおいて試してください",
} as const;

// 誰でも任意の引数で呼べる前提で、role の形だけ検証する
export async function startDemoAction(_: ActionState, form: FormData): Promise<ActionState> {
  const role = form.get("role");
  if (!isDemoRole(role)) return { status: "error", message: "不正なリクエストです" };

  const result = await startDemoSession(role);
  if (!result.ok) return { status: "error", message: MESSAGES[result.reason] };
  await setToken(result.token, result.expiresAt);
  // 初期作品は作者にだけ作られるので、作者はその編集画面、読者はトップへ送る
  redirect(result.sampleWorkId ? `/my/works/${result.sampleWorkId}` : "/");
}

function isDemoRole(value: unknown): value is DemoRole {
  return value === "author" || value === "reader";
}
