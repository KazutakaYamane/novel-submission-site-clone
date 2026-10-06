import { headers } from "next/headers";
import Link from "next/link";

export const dynamic = "force-dynamic";

type Health = { status: string; checks: Record<string, { ok: boolean; error?: string }>; client_ip: string };

async function fetchHealth(): Promise<{ health?: Health; error?: string }> {
  const base = process.env.INTERNAL_API_URL;
  if (!base) return { error: "INTERNAL_API_URL が未設定" };

  const h = await headers();
  const userIp = h.get("cloudfront-viewer-address")?.replace(/:\d+$/, "") ?? h.get("x-forwarded-for")?.split(",")[0];
  try {
    const res = await fetch(`${base}/api/health`, {
      cache: "no-store",
      headers: userIp ? { "X-User-IP": userIp } : {},
      signal: AbortSignal.timeout(3000),
    });
    return { health: await res.json() };
  } catch (e) {
    return { error: String(e) };
  }
}

// ALB のヘルスチェック(/)は 200 のみ見るため、API が落ちていてもこのページは 200 を返す
export default async function Home() {
  const { health, error } = await fetchHealth();

  return (
    <main className="mx-auto max-w-2xl p-8">
      <h1 className="text-2xl font-bold">novel-site-clone 動作確認</h1>
      <p className="mt-2 text-sm text-zinc-600">SSR で {process.env.INTERNAL_API_URL ?? "(未設定)"}/api/health を呼んだ結果</p>
      <pre className="mt-4 overflow-x-auto rounded bg-zinc-900 p-4 text-sm text-zinc-100">
        {health ? JSON.stringify(health, null, 2) : `API に到達できない: ${error}`}
      </pre>
      <ul className="mt-6 list-disc pl-5 text-sm">
        <li>
          <Link className="text-blue-700 underline" href="/isr">/isr</Link>: ISR キャッシュ(Redis 共有)の確認
        </li>
        <li>
          <a className="text-blue-700 underline" href="/api/health">/api/health</a>: ブラウザからの API 呼び出し(CloudFront/ALB 経由)
        </li>
      </ul>
    </main>
  );
}
