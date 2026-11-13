"use client";
import { useActionState } from "react";
import { Form } from "./form";
import { startDemoAction } from "./demo-start-actions";
import styles from "./demo-start-form.module.css";

// ヘッダーでは帯の高さに収め、失敗の文言は帯の下に重ねる。/session-expired では本文の中に並べる
export function DemoStartForm({ variant }: { variant: "header" | "page" }) {
  const [state, action, pending] = useActionState(startDemoAction, { status: "idle" });
  return (
    <Form action={action} className={styles[variant]}>
      <div className={styles.buttons}>
        {/* 狭い幅で「として始める」を見た目だけ隠すと、読み上げの名前に空白が入るので、名前を固定する */}
        <button name="role" value="author" aria-label="デモ作者として始める" className={styles.button} disabled={pending}>
          デモ作者<span className={styles.suffix}>として始める</span>
        </button>
        <button name="role" value="reader" aria-label="デモ読者として始める" className={styles.button} disabled={pending}>
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
