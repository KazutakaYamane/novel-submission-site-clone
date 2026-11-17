import type { ComponentProps } from "react";
import styles from "./form-controls.module.css";

// 作品の設定と話の追加の入力欄。話の編集の題名と本文は本家と同じく枠の無い入力欄で、これを使わない
export function TextInput({ className, ...props }: ComponentProps<"input">) {
  return <input className={className ? `${styles.control} ${className}` : styles.control} {...props} />;
}

export function TextArea(props: Omit<ComponentProps<"textarea">, "className">) {
  return <textarea className={styles.control} {...props} />;
}

export function Select(props: Omit<ComponentProps<"select">, "className">) {
  return <select className={`${styles.control} ${styles.select}`} {...props} />;
}
