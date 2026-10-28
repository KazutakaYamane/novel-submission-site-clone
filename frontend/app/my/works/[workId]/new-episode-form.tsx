"use client";
import { useActionState } from "react";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
import { createEpisodeAction } from "./actions";

export function NewEpisodeForm({ workId }: { workId: string }) {
  const [state, action, pending] = useActionState(createEpisodeAction, { status: "idle" });
  return (
    <Form action={action} noValidate>
      <input type="hidden" name="workId" value={workId} />
      <p>
        <label htmlFor="episode-title">話のタイトル</label>
        <input id="episode-title" name="title" />
      </p>
      <FieldErrors state={state} name="title" />
      {state.status === "error" && state.message && <p role="alert">{state.message}</p>}
      <button disabled={pending}>話を追加</button>
    </Form>
  );
}
