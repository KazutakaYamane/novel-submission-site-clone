import { Suspense } from "react";
import { WorkspaceHeader } from "@/components/workspace-header";
import { MyWorks } from "./my-works";

// ページ全体がユーザーごとの表示なので、全体を Suspense の中で描画する
export default function MyWorksPage() {
  return (
    <>
      <WorkspaceHeader current="page" />
      <Suspense fallback={<p>読み込み中…</p>}>
        <MyWorks />
      </Suspense>
    </>
  );
}
