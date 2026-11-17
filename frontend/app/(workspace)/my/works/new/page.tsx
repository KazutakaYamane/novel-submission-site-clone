import { Suspense } from "react";
import { WorkForm } from "@/components/work-form";
import { WorkspaceBox } from "@/components/workspace-box";
import { WorkspaceBreadcrumb } from "@/components/workspace-breadcrumb";
import { WorkspaceHeader } from "@/components/workspace-header";
import { getCurrentUser } from "@/lib/api/me";
import { redirectUnauthenticated } from "@/lib/auth";
import { createWorkAction } from "./actions";
import styles from "./page.module.css";

export default function NewWorkPage() {
  return (
    <>
      <WorkspaceHeader current="section" />
      <main className={styles.main}>
        <WorkspaceBreadcrumb current="新しい作品" />
        <Suspense fallback={<p>読み込み中…</p>}>
          <NewWork />
        </Suspense>
      </main>
    </>
  );
}

// ゲストにフォームを見せないためだけに確かめる。作成の可否は Laravel が決める
async function NewWork() {
  if (!(await getCurrentUser())) return redirectUnauthenticated();
  return (
    <WorkspaceBox id="work-settings" title="小説設定">
      <WorkForm action={createWorkAction} submitLabel="作品を作る" />
    </WorkspaceBox>
  );
}
