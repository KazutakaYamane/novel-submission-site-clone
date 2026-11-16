"use client";
import { SiteContentFrame } from "@/components/site-content-frame";
import { SiteHeaderFrame } from "@/components/site-header-frame";

// page ごと置き換わるのでヘッダーも消える。Client Component からはログイン状態を取れないので、帯とロゴだけ出す。
// root layout の直下で描画されるので、(site) の layout の枠を自分で付ける
export default function ErrorPage({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <>
      <SiteHeaderFrame />
      <SiteContentFrame>
        <main>
          <h1>エラーが発生しました</h1>
          <button type="button" onClick={() => retry()}>
            再試行
          </button>
        </main>
      </SiteContentFrame>
    </>
  );
}
