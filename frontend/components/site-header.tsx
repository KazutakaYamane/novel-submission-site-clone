import { Suspense } from "react";
import { HeaderAccount } from "./header-account";
import { SectionErrorBoundary } from "./section-error-boundary";
import { SiteHeaderFrame } from "./site-header-frame";

// 各 page.tsx が描画する。root layout はクライアントの遷移で描画し直されないので、layout に置くと、
// トークンが無効になった後もログイン中の表示が残る
export function SiteHeader() {
  return (
    <SiteHeaderFrame>
      <SectionErrorBoundary message="ログイン状態を表示できませんでした">
        <Suspense fallback={null}>
          <HeaderAccount />
        </Suspense>
      </SectionErrorBoundary>
    </SiteHeaderFrame>
  );
}
