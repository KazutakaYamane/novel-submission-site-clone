"use client";

// ルートレイアウトごと置き換わるため、html と body を自前で持つ
export default function GlobalError({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <html lang="ja">
      <body>
        <main>
          <h1>エラーが発生しました</h1>
          <button type="button" onClick={() => retry()}>
            再試行
          </button>
        </main>
      </body>
    </html>
  );
}
