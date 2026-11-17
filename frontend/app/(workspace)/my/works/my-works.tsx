import Link from "next/link";
import { buttonClassName } from "@/components/button";
import { StatusBadge } from "@/components/status-badge";
import { getCurrentUser } from "@/lib/api/me";
import { listMyWorks } from "@/lib/api/my-works";
import { redirectUnauthenticated } from "@/lib/auth";
import styles from "./my-works.module.css";

export async function MyWorks() {
  // listMyWorks は 401 で自分でリダイレクトするので、ユーザーの確認を待たずに並べて呼ぶ
  const [user, works] = await Promise.all([getCurrentUser(), listMyWorks()]);
  if (!user) return redirectUnauthenticated();
  return (
    <main className={styles.main}>
      {/* 本家はここに絞り込みと並び順を置く。機能が無いので、代わりに見出しを置く */}
      <div className={styles.toolbar}>
        <h1 className={styles.heading}>
          <span data-testid="current-user">{user.name} さん</span>の作品
        </h1>
        <Link href="/my/works/new" className={buttonClassName("secondary")}>
          新しい作品を書く
        </Link>
      </div>
      <ul role="list" className={styles.list}>
        {works.map((work) => (
          <li key={work.id} data-testid="my-work" className={styles.row}>
            <StatusBadge tone={work.published ? "published" : "unpublished"}>
              {work.published ? "公開中" : "未公開"}
            </StatusBadge>
            <Link href={`/my/works/${work.id}`} className={styles.title}>
              {work.title}
            </Link>
          </li>
        ))}
      </ul>
    </main>
  );
}
