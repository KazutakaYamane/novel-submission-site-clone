import "server-only";
import { headers } from "next/headers";
import { readToken } from "@/lib/auth";

const TIMEOUT_MS = 3000;

function apiUrl(path: string): string {
  const base = process.env.INTERNAL_API_URL;
  if (!base) throw new Error("INTERNAL_API_URL が未設定");
  return `${base}/api${path}`;
}

// "use cache" の中で呼べるよう、cookies()/headers() を読まない
export async function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  return fetch(apiUrl(path), { ...init, signal: init.signal ?? AbortSignal.timeout(TIMEOUT_MS) });
}

export async function apiFetchAsUser(path: string, init: RequestInit = {}): Promise<Response> {
  const h = new Headers(init.headers);
  const token = await readToken();
  if (token) h.set("Authorization", `Bearer ${token}`);
  // Laravel の 8080 は X-User-IP をクライアント IP として扱い、IP 単位のレート制限に使う
  const userIp = userIpFrom(await headers());
  if (userIp) h.set("X-User-IP", userIp);
  return apiFetch(path, { ...init, headers: h });
}

// CloudFront の cloudfront-viewer-address は "IP:ポート" 形式(IPv6 も角括弧なし)
function userIpFrom(h: Headers): string | undefined {
  const viewer = h.get("cloudfront-viewer-address");
  if (viewer) return viewer.replace(/:\d+$/, "");
  return h.get("x-forwarded-for")?.split(",")[0].trim() || undefined;
}
