import Link from "next/link";
import { Suspense } from "react";
import { WorkForm } from "@/components/work-form";
import { getCurrentUser } from "@/lib/api/me";
import { redirectUnauthenticated } from "@/lib/auth";
import { createWorkAction } from "./actions";

export default function NewWorkPage() {
  return (
    <main>
      <h1>新しい作品</h1>
      <Suspense fallback={<p>読み込み中…</p>}>
        <NewWork />
      </Suspense>
    </main>
  );
}

// ゲストにフォームを見せないためだけに確かめる。作成の可否は Laravel が決める
async function NewWork() {
  if (!(await getCurrentUser())) return redirectUnauthenticated();
  return (
    <>
      <WorkForm action={createWorkAction} submitLabel="作品を作る" />
      <p>
        <Link href="/my/works">作品管理へ戻る</Link>
      </p>
    </>
  );
}
