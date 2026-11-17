import type { ReactNode } from "react";
import styles from "./workspace-box.module.css";

// 本家のワークスペースの見出しの帯つきの箱。見出しを region の名前にする
export function WorkspaceBox({ id, title, children }: { id: string; title: string; children: ReactNode }) {
  return (
    <section aria-labelledby={id} className={styles.box}>
      <h2 id={id} className={styles.heading}>
        {title}
      </h2>
      {children}
    </section>
  );
}
