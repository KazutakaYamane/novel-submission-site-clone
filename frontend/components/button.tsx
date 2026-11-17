import type { ComponentProps } from "react";
import styles from "./button.module.css";

type Variant = "primary" | "secondary";

// リンクをボタンの見た目にするときにも使う
export function buttonClassName(variant: Variant): string {
  return `${styles.button} ${styles[variant]}`;
}

export function Button({ variant, ...props }: Omit<ComponentProps<"button">, "className"> & { variant: Variant }) {
  return <button className={buttonClassName(variant)} {...props} />;
}
