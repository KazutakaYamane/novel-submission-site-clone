import type { ComponentProps } from "react";
import type { ActionState } from "@/lib/action-state";
import { FieldErrors } from "./field-errors";
import styles from "./auth-text-field.module.css";

type Props = Omit<ComponentProps<"input">, "className" | "id" | "name"> & {
  label: string;
  name: string;
  // 渡すと、その欄のエラーを欄の下に出す。ログインはフォームの上にまとめて出すので渡さない
  state?: ActionState;
};

export function AuthTextField({ label, name, state, ...props }: Props) {
  return (
    <div className={styles.field}>
      <label htmlFor={name} className={styles.label}>
        {label}
      </label>
      <input id={name} name={name} className={styles.input} {...props} />
      {state && <FieldErrors state={state} name={name} />}
    </div>
  );
}
