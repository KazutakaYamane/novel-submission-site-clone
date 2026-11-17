"use client";
import { useActionState } from "react";
import { Button } from "@/components/button";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
import { FormMessage } from "@/components/form-message";
import { TextInput } from "@/components/form-controls";
import { createEpisodeAction } from "./actions";
import styles from "./new-episode-form.module.css";

// 本家は「次のエピソードを執筆」で題名の無い話を作る。ここは題名を入れてから作る
export function NewEpisodeForm({ workId }: { workId: string }) {
  const [state, action, pending] = useActionState(createEpisodeAction, { status: "idle" });
  return (
    <Form action={action} noValidate>
      <input type="hidden" name="workId" value={workId} />
      <div className={styles.row}>
        <label htmlFor="episode-title" className={styles.label}>
          話のタイトル
        </label>
        <TextInput id="episode-title" name="title" className={styles.input} />
        <Button variant="primary" disabled={pending}>
          話を追加
        </Button>
      </div>
      <FieldErrors state={state} name="title" />
      {state.status === "error" && state.message && <FormMessage role="alert">{state.message}</FormMessage>}
    </Form>
  );
}
