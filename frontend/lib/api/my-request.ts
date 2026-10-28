import "server-only";
import type { ActionState } from "@/lib/action-state";
import { endSessionAndRedirect, redirectUnauthenticated } from "@/lib/auth";
import { apiFetchAsUser } from "./client";
import type { components } from "./schema";

type ValidationErrors = components["responses"]["ValidationException"]["content"]["application/json"]["errors"];

export type WriteResult<T> =
  | { ok: true; data: T }
  | { ok: false; reason: "unauthenticated" | "not_found" }
  | { ok: false; reason: "invalid"; fieldErrors: Record<string, string[]> };

// 執筆画面のページから呼ぶ。他人の作品(403)も存在しない作品と同じく null にし、ページが 404 を出す
export async function getMine<T>(path: string): Promise<T | null> {
  const res = await apiFetchAsUser(path);
  if (res.status === 401) return redirectUnauthenticated();
  if (res.status === 403 || res.status === 404) return null;
  if (!res.ok) throw new Error(`${path} が ${res.status} を返した`);
  return res.json();
}

// Server Action から呼ぶ。401 の cookie の削除は action がするので、ここではリダイレクトしない
export async function writeMine<J, T>(
  path: string,
  init: { method: "POST" | "PATCH"; body?: unknown },
  map: (body: J) => T,
): Promise<WriteResult<T>> {
  const res = await apiFetchAsUser(path, {
    method: init.method,
    ...(init.body !== undefined && {
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(init.body),
    }),
  });
  if (res.status === 401) return { ok: false, reason: "unauthenticated" };
  if (res.status === 403 || res.status === 404) return { ok: false, reason: "not_found" };
  if (res.status === 422) {
    const { errors }: { errors: ValidationErrors } = await res.json();
    return { ok: false, reason: "invalid", fieldErrors: fieldErrorsFrom(errors) };
  }
  if (!res.ok) throw new Error(`${path} が ${res.status} を返した`);
  return { ok: true, data: map(await res.json()) };
}

// Server Action の失敗を画面の状態にする。他人の作品も存在しない作品と同じ文言にし、存在を明かさない
export async function failedActionState(result: Extract<WriteResult<unknown>, { ok: false }>): Promise<ActionState> {
  if (result.reason === "invalid") return { status: "error", fieldErrors: result.fieldErrors };
  if (result.reason === "unauthenticated") return endSessionAndRedirect();
  return { status: "error", message: "作品または話が見つかりません" };
}

// 配列の要素のエラーは tags.0 のようなキーで返る。フォームの入力欄は配列ごとに 1 つなので、配列の項目にまとめる
function fieldErrorsFrom(errors: ValidationErrors): Record<string, string[]> {
  const fieldErrors: Record<string, string[]> = {};
  for (const [key, messages] of Object.entries(errors)) {
    const field = key.replace(/\.\d+$/, "");
    fieldErrors[field] = [...new Set([...(fieldErrors[field] ?? []), ...messages])];
  }
  return fieldErrors;
}
