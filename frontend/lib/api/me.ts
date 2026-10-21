import "server-only";
import { readToken } from "@/lib/auth";
import { apiFetchAsUser } from "./client";
import type { operations } from "./schema";

export type CurrentUser = { id: string; name: string };

// ヘッダーなど任意の表示に使うため、401 でもリダイレクトしない
export async function getCurrentUser(): Promise<CurrentUser | null> {
  if (!(await readToken())) return null;
  const res = await apiFetchAsUser("/me");
  if (res.status === 401) return null;
  if (!res.ok) throw new Error(`/me が ${res.status} を返した`);
  const { data }: operations["me"]["responses"][200]["content"]["application/json"] = await res.json();
  return { id: data.id, name: data.name };
}
