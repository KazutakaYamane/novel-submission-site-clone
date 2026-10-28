import { Suspense } from "react";
import { MyWorks } from "./my-works";

// ページ全体がユーザーごとの表示なので、全体を Suspense の中で描画する
export default function MyWorksPage() {
  return (
    <Suspense fallback={<p>読み込み中…</p>}>
      <MyWorks />
    </Suspense>
  );
}
