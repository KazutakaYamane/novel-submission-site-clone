import { Suspense } from "react";
import { SiteHeader } from "@/components/site-header";
import { MyWorks } from "./my-works";

// ページ全体がユーザーごとの表示なので、全体を Suspense の中で描画する
export default function MyWorksPage() {
  return (
    <>
      <SiteHeader />
      <Suspense fallback={<p>読み込み中…</p>}>
        <MyWorks />
      </Suspense>
    </>
  );
}
