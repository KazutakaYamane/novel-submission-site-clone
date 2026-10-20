import { Suspense } from "react";
import { HealthStatus } from "./health-status";

// ALB のヘルスチェック(/)は 200 のみ見る。事前描画したシェルを返すので、API が落ちていても 200 になる
export default function Home() {
  return (
    <main>
      <h1>novel-site-clone 動作確認</h1>
      <p>Next.js サーバーから INTERNAL_API_URL 経由で /api/health を呼んだ結果</p>
      <Suspense fallback={<p>確認中…</p>}>
        <HealthStatus />
      </Suspense>
    </main>
  );
}
