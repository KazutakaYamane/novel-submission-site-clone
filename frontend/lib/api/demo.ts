import "server-only";
import { apiFetchAsUser } from "./client";
import type { components, operations } from "./schema";

export type DemoRole = components["schemas"]["DemoRole"];
type StartedSession = operations["demo.startDemoSession"]["responses"][201]["content"]["application/json"];

type DemoSessionResult =
  | { ok: true; token: string; expiresAt: string; sampleWorkId: string | null }
  | { ok: false; reason: "rate_limited" | "failed" };

// 失敗はボタンの横に文言で出すので throw しない。IP 単位のレート制限に X-User-IP が要るので apiFetchAsUser で呼ぶ
export async function startDemoSession(role: DemoRole): Promise<DemoSessionResult> {
  const res = await apiFetchAsUser("/demo/sessions", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ role }),
  });
  if (res.status === 429) return { ok: false, reason: "rate_limited" };
  if (!res.ok) return { ok: false, reason: "failed" };
  const body: StartedSession = await res.json();
  return { ok: true, token: body.token, expiresAt: body.expires_at, sampleWorkId: body.sample_work_id };
}
