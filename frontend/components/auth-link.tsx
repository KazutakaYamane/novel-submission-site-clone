import Link from "next/link";
import type { ReactNode } from "react";
import styles from "./auth-link.module.css";

// ログインと会員登録を行き来するリンク。本家の「新規登録・ログインにお困りですか？」と同じ形
export function AuthLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link href={href} className={styles.link}>
      {children}
    </Link>
  );
}
