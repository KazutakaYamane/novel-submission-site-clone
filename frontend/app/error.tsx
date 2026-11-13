"use client";
import { SiteHeaderFrame } from "@/components/site-header-frame";

// page ごと置き換わるのでヘッダーも消える。Client Component からはログイン状態を取れないので、帯とロゴだけ出す
export default function ErrorPage({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <>
      <SiteHeaderFrame />
      <main>
        <h1>エラーが発生しました</h1>
        <button type="button" onClick={() => retry()}>
          再試行
        </button>
      </main>
    </>
  );
}
