import Link from "next/link";
import type { ReactNode } from "react";
import { SITE_NAME } from "@/lib/site";
import { SiteLogo } from "./site-logo";
import styles from "./site-header-frame.module.css";

// 帯とロゴだけ。Laravel を呼ばないので、Client Component の error.tsx からも使える
export function SiteHeaderFrame({ children }: { children?: ReactNode }) {
  return (
    <header className={styles.bar}>
      <div className={styles.inner}>
        <Link href="/" className={styles.logoLink} aria-label={SITE_NAME}>
          <SiteLogo className={styles.logo} />
        </Link>
        <div className={styles.account}>{children}</div>
      </div>
      <div className={styles.subnav} />
    </header>
  );
}
