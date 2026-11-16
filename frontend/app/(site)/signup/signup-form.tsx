"use client";
import { useActionState } from "react";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
import { signupAction } from "./actions";

export function SignupForm() {
  const [state, action, pending] = useActionState(signupAction, { status: "idle" });
  return (
    <Form action={action}>
      <p>
        <label htmlFor="name">名前</label>
        <input id="name" name="name" autoComplete="nickname" required />
      </p>
      <FieldErrors state={state} name="name" />
      <p>
        <label htmlFor="email">メールアドレス</label>
        <input id="email" name="email" type="email" autoComplete="email" required />
      </p>
      <FieldErrors state={state} name="email" />
      <p>
        <label htmlFor="password">パスワード</label>
        <input id="password" name="password" type="password" autoComplete="new-password" required />
      </p>
      <FieldErrors state={state} name="password" />
      {state.status === "error" && state.message && <p role="alert">{state.message}</p>}
      <button disabled={pending}>登録する</button>
    </Form>
  );
}
