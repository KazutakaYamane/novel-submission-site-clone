"use client";
import { useActionState } from "react";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
import { loginAction } from "./actions";

export function LoginForm() {
  const [state, action, pending] = useActionState(loginAction, { status: "idle" });
  return (
    <Form action={action}>
      <p>
        <label htmlFor="email">メールアドレス</label>
        <input id="email" name="email" type="email" autoComplete="email" required />
      </p>
      <FieldErrors state={state} name="email" />
      <p>
        <label htmlFor="password">パスワード</label>
        <input id="password" name="password" type="password" autoComplete="current-password" required />
      </p>
      <FieldErrors state={state} name="password" />
      {state.status === "error" && state.message && <p role="alert">{state.message}</p>}
      <button disabled={pending}>ログイン</button>
    </Form>
  );
}
