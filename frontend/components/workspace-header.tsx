import Link from "next/link";
import { Suspense } from "react";
import { getCurrentUser } from "@/lib/api/me";
import { SITE_NAME } from "@/lib/site";
import { LogoutButton } from "./logout-button";
import { SectionErrorBoundary } from "./section-error-boundary";
import styles from "./workspace-header.module.css";

// /my/works 以下の各 page.tsx が描画する。site-header.tsx と同じく、layout に置くとトークンが無効になった後も作者名が残る。
// 本家のナビの 5 項目のうち、機能があるのは作品(本家の「小説」)だけなので 1 項目にする。
// current は作品の一覧では "page"、その下の画面では "section"
export function WorkspaceHeader({ current }: { current: "page" | "section" }) {
  return (
    <header className={styles.bar}>
      <div className={styles.inner}>
        <div className={styles.brand}>
          <Link href="/" aria-label={SITE_NAME} className={styles.logoLink}>
            <WorkspaceLogo />
          </Link>
          <span className={styles.title}>ワークスペース</span>
        </div>
        <nav aria-label="ワークスペース" className={styles.nav}>
          <Link
            href="/my/works"
            aria-current={current === "page" ? "page" : "true"}
            className={styles.navLink}
          >
            <PenIcon />
            作品の管理
          </Link>
        </nav>
        <div className={styles.account}>
          <SectionErrorBoundary message="ログイン状態を表示できませんでした">
            <Suspense fallback={null}>
              <WorkspaceAccount />
            </Suspense>
          </SectionErrorBoundary>
        </div>
      </div>
    </header>
  );
}

// ゲストはページ側で /login へ移すので、ここでは何も出さない
async function WorkspaceAccount() {
  const user = await getCurrentUser();
  if (!user) return null;
  return (
    <>
      <span className={styles.userName} data-testid="header-user-name">
        {user.name}
      </span>
      <LogoutButton className={styles.logout} />
    </>
  );
}

// 本家のワークスペースのロゴと同じ 16×16 の四角に収めた自作のダミー
function WorkspaceLogo() {
  return (
    <svg className={styles.logo} viewBox="0 0 16 16" aria-hidden="true">
      <rect className={styles.logoSquare} width="16" height="16" rx="1" />
      <path className={styles.logoMark} d="M5 4h6M5 8h6M5 12h4" strokeWidth="1.6" />
    </svg>
  );
}

function PenIcon() {
  return (
    <svg className={styles.navIcon} viewBox="0 0 20 20" aria-hidden="true">
      <path d="M3 17l1-4L13.5 3.5l3 3L7 16z" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M11.5 5.5l3 3M3 17h14" fill="none" stroke="currentColor" strokeWidth="1.4" />
    </svg>
  );
}
