import type { ReactNode } from "react";
import styles from "./status-badge.module.css";

export type BadgeTone = "unpublished" | "draft" | "published";

// 本家のワークスペースの「未公開」「下書き」の札(55×16)
export function StatusBadge({ tone, children }: { tone: BadgeTone; children: ReactNode }) {
  return <span className={`${styles.badge} ${styles[tone]}`}>{children}</span>;
}
