import Link from "next/link";
import styles from "./workspace-breadcrumb.module.css";

// 本家の「小説 > 作品名」。最後の項目をページの見出しにする
export function WorkspaceBreadcrumb({ current }: { current: string }) {
  return (
    <nav aria-label="パンくずリスト" className={styles.breadcrumb}>
      <ol role="list" className={styles.list}>
        <li>
          <Link href="/my/works" className={styles.parent}>
            作品の管理
          </Link>
        </li>
        <li className={styles.current}>
          <h1 className={styles.heading}>{current}</h1>
        </li>
      </ol>
    </nav>
  );
}
