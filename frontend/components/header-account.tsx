import Link from "next/link";
import { DemoStartForm } from "./demo-start-form";
import { getCurrentUser } from "@/lib/api/me";
import { LogoutButton } from "./logout-button";
import styles from "./header-account.module.css";

// cookie を読むので、Suspense の中に置く(site-header.tsx)
export async function HeaderAccount() {
  const user = await getCurrentUser();
  if (user) {
    return (
      <div className={styles.account}>
        <span className={styles.userName} data-testid="header-user-name">
          {user.name}
        </span>
        <Link href="/my/works" className={styles.link}>
          作品の管理
        </Link>
        <LogoutButton className={styles.link} />
      </div>
    );
  }
  return (
    <div className={styles.account}>
      <div className={styles.demo}>
        <DemoStartForm variant="header" />
      </div>
      <Link href="/login" className={styles.link}>
        ログイン
      </Link>
      <Link href="/signup" className={styles.signup}>
        新規登録<span className={styles.pcOnly}>(無料)</span>
      </Link>
    </div>
  );
}
