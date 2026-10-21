"use client";
import { useActionState } from "react";
import { Form } from "@/components/form";
import { logoutAction } from "./actions";

export function LogoutButton() {
  const [, action, pending] = useActionState(logoutAction, { status: "idle" });
  return (
    <Form action={action}>
      <button disabled={pending}>ログアウト</button>
    </Form>
  );
}
