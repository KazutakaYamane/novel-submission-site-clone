import Link from "next/link";
import { SITE_NAME } from "./site";
import { SiteLogo } from "./site-logo";
import styles from "./site-header.module.css";

// ログイン状態とデモのボタンは T-0031 で中身を入れる。ここでは帯とロゴの位置だけを置く
export function SiteHeader() {
  return (
    <header className={styles.bar}>
      <div className={styles.inner}>
        <Link href="/" className={styles.logoLink} aria-label={SITE_NAME}>
          <SiteLogo className={styles.logo} />
        </Link>
      </div>
      <div className={styles.subnav} />
    </header>
  );
}
