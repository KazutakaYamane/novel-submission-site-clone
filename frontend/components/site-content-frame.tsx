import type { ReactNode } from "react";
import styles from "./site-content-frame.module.css";

// サイト共通のヘッダーを出すページの本文の枠。話ページは本家と同じく帯も幅も別なので使わない
export function SiteContentFrame({ children }: { children: ReactNode }) {
  return <div className={styles.frame}>{children}</div>;
}
