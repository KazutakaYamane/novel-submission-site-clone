import { Suspense } from "react";
import { getCurrentUser } from "@/lib/api/me";
import { redirectUnauthenticated } from "@/lib/auth";
import { LogoutButton } from "./logout-button";

// ページ全体がユーザーごとの表示なので、全体を Suspense の中で描画する
export default function MyWorksPage() {
  return (
    <Suspense fallback={<p>読み込み中…</p>}>
      <MyWorks />
    </Suspense>
  );
}

async function MyWorks() {
  const user = await getCurrentUser();
  if (!user) return redirectUnauthenticated();
  return (
    <main>
      <h1>作品管理</h1>
      <p data-testid="current-user">{user.name} さん</p>
      <LogoutButton />
    </main>
  );
}
