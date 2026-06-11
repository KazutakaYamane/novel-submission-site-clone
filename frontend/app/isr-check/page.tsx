// ------------------------------------------------------------
// ISR 動作確認ページ(フェーズ1 の疎通確認用)
// - revalidate = 30: 30 秒間は cacheHandler(Redis)から同じ生成結果を配信し、
//   期限切れ後の最初のアクセスで stale を返しつつバックグラウンド再生成する
// - 生成時刻が 30 秒間変わらなければ ISR キャッシュが効いている
// - 本番では複数タスク間で同じ時刻が返ることで「共有キャッシュ」を実証できる
// ------------------------------------------------------------

export const revalidate = 30;

export default function IsrCheckPage() {
  const generatedAt = new Date().toISOString();

  return (
    <main style={{ fontFamily: "monospace", padding: "2rem" }}>
      <h1>ISR check</h1>
      <p>
        generatedAt: <strong>{generatedAt}</strong>
      </p>
      <p>
        このページは revalidate = 30 の ISR。30 秒間は生成時刻が変わらず、
        cacheHandler(Redis backend)から配信される。
      </p>
    </main>
  );
}
