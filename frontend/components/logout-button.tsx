"use client";
import { useActionState } from "react";
import { Form } from "@/components/form";
import { logoutAction } from "./logout-actions";

export function LogoutButton({ className }: { className?: string }) {
  const [, action, pending] = useActionState(logoutAction, { status: "idle" });
  return (
    <Form action={action}>
      <button className={className} disabled={pending}>
        ログアウト
      </button>
    </Form>
  );
}
