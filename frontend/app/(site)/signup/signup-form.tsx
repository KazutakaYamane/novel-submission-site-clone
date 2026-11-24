"use client";
import { useActionState } from "react";
import { AuthTextField } from "@/components/auth-text-field";
import { ErrorBox } from "@/components/error-box";
import { Form } from "@/components/form";
import { PillButton } from "@/components/pill-button";
import { signupAction } from "./actions";
import styles from "./signup-form.module.css";

export function SignupForm() {
  const [state, action, pending] = useActionState(signupAction, { status: "idle" });
  return (
    <Form action={action}>
      <ErrorBox messages={state.status === "error" && state.message ? [state.message] : []} />
      <AuthTextField label="名前" name="name" autoComplete="nickname" required state={state} />
      <AuthTextField label="メールアドレス" name="email" type="email" autoComplete="email" required state={state} />
      <AuthTextField label="パスワード" name="password" type="password" autoComplete="new-password" required state={state} />
      <div className={styles.actions}>
        <PillButton variant="fill" disabled={pending}>
          登録する
        </PillButton>
      </div>
    </Form>
  );
}
