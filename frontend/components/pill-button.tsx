import type { ComponentProps } from "react";
import styles from "./pill-button.module.css";

type Variant = "fill" | "outline";

// デモのボタンのように、ボタンの組を持つ部品が自分の button に付けるときにも使う
export function pillButtonClassName(variant: Variant): string {
  return `${styles.button} ${styles[variant]}`;
}

// 本家のログインの画面のボタン。幅は親に合わせる
export function PillButton({ variant, ...props }: Omit<ComponentProps<"button">, "className"> & { variant: Variant }) {
  return <button className={pillButtonClassName(variant)} {...props} />;
}
