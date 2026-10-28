import Link from "next/link";
import { getCurrentUser } from "@/lib/api/me";
import { listMyWorks } from "@/lib/api/my-works";
import { redirectUnauthenticated } from "@/lib/auth";
import { LogoutButton } from "./logout-button";

export async function MyWorks() {
  // listMyWorks は 401 で自分でリダイレクトするので、ユーザーの確認を待たずに並べて呼ぶ
  const [user, works] = await Promise.all([getCurrentUser(), listMyWorks()]);
  if (!user) return redirectUnauthenticated();
  return (
    <main>
      <h1>作品管理</h1>
      <p data-testid="current-user">{user.name} さん</p>
      <p>
        <Link href="/my/works/new">新しい作品を書く</Link>
      </p>
      <ul>
        {works.map((work) => (
          <li key={work.id} data-testid="my-work">
            <Link href={`/my/works/${work.id}`}>{work.title}</Link> {work.published ? "公開中" : "未公開"}
          </li>
        ))}
      </ul>
      <LogoutButton />
    </main>
  );
}
