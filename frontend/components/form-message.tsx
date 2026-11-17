import type { ReactNode } from "react";
import styles from "./form-message.module.css";

// フォームの結果の 1 文。alert はエラー、status は成功の知らせ
export function FormMessage({ role, children }: { role: "alert" | "status"; children: ReactNode }) {
  return (
    <p role={role} className={role === "alert" ? `${styles.message} ${styles.alert}` : styles.message}>
      {children}
    </p>
  );
}
