import "server-only";
import { apiFetch } from "./client";
import type { operations } from "./schema";

export type Health = operations["health"]["responses"][200]["content"]["application/json"];

// 死活確認なのでキャッシュしない
export async function getHealth(): Promise<Health> {
  const res = await apiFetch("/health");
  if (!res.ok) throw new Error(`health API が ${res.status} を返した`);
  return res.json();
}
