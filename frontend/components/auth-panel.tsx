import type { ReactNode } from "react";
import styles from "./auth-panel.module.css";

// ログイン・会員登録・リセット後の案内の本文。本家のメールのログインの画面と同じ幅 508px の列に、見出し・説明・中身を並べる
export function AuthPanel({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <div className={styles.panel}>
      <h1 className={styles.title}>{title}</h1>
      {description && <p className={styles.description}>{description}</p>}
      <div className={styles.body}>{children}</div>
    </div>
  );
}
