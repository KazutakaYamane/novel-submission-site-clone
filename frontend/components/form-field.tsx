import type { ReactNode } from "react";
import type { ActionState } from "@/lib/action-state";
import { FieldErrors } from "./field-errors";
import styles from "./form-field.module.css";

type Props = { state: ActionState; name: string; children: ReactNode };

// ラベル・入力欄・その欄のエラーを 1 項目にまとめる
export function FormField({ label, htmlFor, state, name, children }: Props & { label: string; htmlFor: string }) {
  return (
    <div className={styles.field}>
      <label htmlFor={htmlFor} className={styles.label}>
        {label}
      </label>
      {children}
      <FieldErrors state={state} name={name} />
    </div>
  );
}

// チェックボックスの組。FormField と同じ見た目の 1 項目にする
export function FormFieldset({ legend, state, name, children }: Props & { legend: string }) {
  return (
    <fieldset className={`${styles.field} ${styles.group}`}>
      <legend className={styles.label}>{legend}</legend>
      <div className={styles.options}>{children}</div>
      <FieldErrors state={state} name={name} />
    </fieldset>
  );
}
