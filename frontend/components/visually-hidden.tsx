import type { ReactNode } from "react";
import styles from "./visually-hidden.module.css";

// 画面には出さず、読み上げとコピーにだけ含める文字
export function VisuallyHidden({ children }: { children: ReactNode }) {
  return <span className={styles.hidden}>{children}</span>;
}
