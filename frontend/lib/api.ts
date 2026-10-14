export type Health = {
  status: string;
  checks: Record<string, { ok: boolean; error?: string }>;
  client_ip: string;
};

export function apiUrl(path: string): string {
  const base = process.env.INTERNAL_API_URL;
  if (!base) throw new Error("INTERNAL_API_URL が未設定");
  return `${base}/api${path}`;
}

// userIp は SSR からユーザーの IP を Laravel に渡すためのもの(nginx の 8080 が X-User-IP を REMOTE_ADDR に反映する)
export async function fetchHealth(userIp?: string): Promise<Health> {
  const res = await fetch(apiUrl("/health"), {
    cache: "no-store",
    headers: userIp ? { "X-User-IP": userIp } : {},
    signal: AbortSignal.timeout(3000),
  });
  if (!res.ok) throw new Error(`health API が ${res.status} を返した`);
  return res.json();
}
