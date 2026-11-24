"use client";
import { useActionState } from "react";
import { Form } from "./form";
import { startDemoAction } from "./demo-start-actions";
import { pillButtonClassName } from "./pill-button";
import styles from "./demo-start-form.module.css";

type Variant = "header" | "hero" | "auth";

// ヘッダーでは帯の高さに収め、失敗の文言は帯の下に重ねる。トップの帯では中央に置く。
// ログインと /session-expired では本家のログイン方式のボタンの形で縦に並べる
export function DemoStartForm({ variant }: { variant: Variant }) {
  const [state, action, pending] = useActionState(startDemoAction, { status: "idle" });
  const buttonClassName = variant === "auth" ? pillButtonClassName("outline") : styles.button;
  return (
    <Form action={action} className={styles[variant]}>
      <div className={styles.buttons}>
        {/* 狭い幅で「として始める」を見た目だけ隠すと、読み上げの名前に空白が入るので、名前を固定する */}
        <button name="role" value="author" aria-label="デモ作者として始める" className={buttonClassName} disabled={pending}>
          デモ作者<span className={styles.suffix}>として始める</span>
        </button>
        <button name="role" value="reader" aria-label="デモ読者として始める" className={buttonClassName} disabled={pending}>
          デモ読者<span className={styles.suffix}>として始める</span>
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
