"use client";

export default function ErrorPage({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <main>
      <h1>エラーが発生しました</h1>
      <button type="button" onClick={() => retry()}>
        再試行
      </button>
    </main>
  );
}
