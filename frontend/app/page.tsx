// サーバーサイド(SSR)で Laravel API を叩き、DB までの縦串疎通を確認するページ。
// fetch 先はコンテナ内部 URL(ローカル: http://nginx:80 / 本番: Service Connect の http://api:80)。
// ブラウザからのクライアント fetch は /api(rewrites 経由)を使うが、このページはサーバー側で完結する。

const INTERNAL_API_URL = process.env.INTERNAL_API_URL ?? "http://nginx:80";

// ヘルスチェックは毎リクエスト評価したいので SSR(動的レンダリング)に固定する。
export const dynamic = "force-dynamic";

type Health = {
  status: string;
  database: string;
  time: string;
};

async function fetchHealth(): Promise<Health | null> {
  try {
    const res = await fetch(`${INTERNAL_API_URL}/api/health`, {
      cache: "no-store",
    });
    return (await res.json()) as Health;
  } catch {
    return null;
  }
}

export default async function Home() {
  const health = await fetchHealth();

  return (
    <main style={{ padding: "2rem", fontFamily: "system-ui, sans-serif" }}>
      <h1>Novel Submission Site</h1>
      <p>SSR 疎通確認: Next.js → Laravel API → DB</p>
      {health ? (
        <dl>
          <dt>API status</dt>
          <dd>{health.status}</dd>
          <dt>Database</dt>
          <dd>{health.database === "ok" ? "DB接続OK" : "DB接続NG"}</dd>
          <dt>Server time</dt>
          <dd>{health.time}</dd>
        </dl>
      ) : (
        <p>API に到達できませんでした（{INTERNAL_API_URL}/api/health）。</p>
      )}
    </main>
  );
}
