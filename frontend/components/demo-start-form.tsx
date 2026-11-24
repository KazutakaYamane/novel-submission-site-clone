"use client";
import { useActionState } from "react";
import { Form } from "./form";
import { startDemoAction } from "./demo-start-actions";
import styles from "./demo-start-form.module.css";

type Variant = "header" | "hero" | "auth";

// ヘッダーでは帯の高さに収め、失敗の文言は帯の下に重ねる。トップの帯では中央に置く。
// ログインと /session-expired では本家のログイン方式のボタンの形で縦に並べる
export function DemoStartForm({ variant }: { variant: Variant }) {
  const [state, action, pending] = useActionState(startDemoAction, { status: "idle" });
  return (
    <Form action={action} className={styles[variant]}>
      <div className={styles.buttons}>
        <button name="role" value="author" className={styles.button} disabled={pending}>
          デモ作者として始める
        </button>
        <button name="role" value="reader" className={styles.button} disabled={pending}>
          デモ読者として始める
        </button>
      </div>
      {state.status === "error" && (
        <p role="alert" className={styles.alert}>
          {state.message}
        </p>
      )}
    </Form>
  );
}
