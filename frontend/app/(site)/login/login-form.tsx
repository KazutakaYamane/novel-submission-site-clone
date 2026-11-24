"use client";
import { useActionState } from "react";
import { AuthTextField } from "@/components/auth-text-field";
import { ErrorBox } from "@/components/error-box";
import { Form } from "@/components/form";
import { PillButton } from "@/components/pill-button";
import type { ActionState } from "@/lib/action-state";
import { loginAction } from "./actions";
import styles from "./login-form.module.css";

export function LoginForm() {
  const [state, action, pending] = useActionState(loginAction, { status: "idle" });
  return (
    <Form action={action}>
      <ErrorBox messages={errorMessages(state)} />
      <AuthTextField label="メールアドレス" name="email" type="email" autoComplete="email" required />
      <AuthTextField label="パスワード" name="password" type="password" autoComplete="current-password" required />
      <div className={styles.actions}>
        <PillButton variant="fill" disabled={pending}>
          ログイン
        </PillButton>
      </div>
    </Form>
  );
}

// 本家と同じく、どの欄の失敗かを分けずにフォームの上にまとめて出す
function errorMessages(state: ActionState): string[] {
  if (state.status !== "error") return [];
  const messages = Object.values(state.fieldErrors ?? {}).flat();
  return state.message ? [state.message, ...messages] : messages;
}
