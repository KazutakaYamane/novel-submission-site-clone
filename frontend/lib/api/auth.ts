import "server-only";
import { apiFetchAsUser } from "./client";

export async function logout(): Promise<"ok" | "unauthenticated"> {
  const res = await apiFetchAsUser("/auth/logout", { method: "POST" });
  if (res.status === 401) return "unauthenticated";
  if (!res.ok) throw new Error(`/auth/logout が ${res.status} を返した`);
  return "ok";
}
