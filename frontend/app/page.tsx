import { headers } from "next/headers";
import Link from "next/link";
import { fetchHealth, type Health } from "@/lib/api";

export const dynamic = "force-dynamic";

async function loadHealth(): Promise<{ health?: Health; error?: string }> {
  const h = await headers();
  const userIp = h.get("cloudfront-viewer-address")?.replace(/:\d+$/, "") ?? h.get("x-forwarded-for")?.split(",")[0];
  try {
    return { health: await fetchHealth(userIp) };
  } catch (e) {
    return { error: String(e) };
  }
}

// ALB のヘルスチェック(/)は 200 のみ見るため、API が落ちていてもこのページは 200 を返す
export default async function Home() {
  const { health, error } = await loadHealth();

  return (
    <main>
      <h1>novel-site-clone 動作確認</h1>
      <p>SSR で INTERNAL_API_URL 経由の /api/v1/health を呼んだ結果</p>
      <pre>{health ? JSON.stringify(health, null, 2) : `API に到達できない: ${error}`}</pre>
      <ul>
        <li>
          <Link href="/isr">/isr</Link>: ISR キャッシュ(Redis 共有)の確認
        </li>
        <li>
          <a href="/api/v1/health">/api/v1/health</a>: ブラウザからの API 呼び出し(CloudFront/ALB 経由)
        </li>
      </ul>
    </main>
  );
}
