"use client";
import { useActionState } from "react";
import { Button } from "@/components/button";
import { Form } from "@/components/form";
import { FormField, FormFieldset } from "@/components/form-field";
import { FormMessage } from "@/components/form-message";
import { Select, TextArea, TextInput } from "@/components/form-controls";
import type { ActionState } from "@/lib/action-state";
import type { MyWork } from "@/lib/api/my-works";
import {
  GENRES,
  RATING_FLAGS,
  WORK_STATUSES,
  genreLabel,
  ratingFlagLabel,
  statusLabel,
} from "@/lib/work-attributes";
import styles from "./work-form.module.css";

// 作成(work なし)と編集で同じ入力欄を使う。required を付けず、Laravel の 422 の文言をそのまま出す
export function WorkForm(props: {
  action: (state: ActionState, form: FormData) => Promise<ActionState>;
  submitLabel: string;
  work?: MyWork;
}) {
  const { work } = props;
  const [state, action, pending] = useActionState(props.action, { status: "idle" });
  return (
    <Form action={action} noValidate>
      {work && <input type="hidden" name="workId" value={work.id} />}
      <FormField label="タイトル" htmlFor="title" state={state} name="title">
        <TextInput id="title" name="title" defaultValue={work?.title} />
      </FormField>
      <FormField label="キャッチコピー" htmlFor="catchphrase" state={state} name="catchphrase">
        <TextInput id="catchphrase" name="catchphrase" defaultValue={work?.catchphrase ?? ""} />
      </FormField>
      <FormField label="あらすじ" htmlFor="introduction" state={state} name="introduction">
        <TextArea id="introduction" name="introduction" rows={6} defaultValue={work?.introduction} />
      </FormField>
      <FormField label="ジャンル" htmlFor="genre" state={state} name="genre">
        <Select id="genre" name="genre" defaultValue={work?.genre ?? ""}>
          {!work && <option value="">選択してください</option>}
          {GENRES.map((genre) => (
            <option key={genre} value={genre}>
              {genreLabel(genre)}
            </option>
          ))}
        </Select>
      </FormField>
      <FormField label="連載状態" htmlFor="status" state={state} name="status">
        <Select id="status" name="status" defaultValue={work?.status ?? "ongoing"}>
          {WORK_STATUSES.map((status) => (
            <option key={status} value={status}>
              {statusLabel(status)}
            </option>
          ))}
        </Select>
      </FormField>
      <FormFieldset legend="セルフレイティング" state={state} name="rating_flags">
        {RATING_FLAGS.map((flag) => (
          <label key={flag}>
            <input
              type="checkbox"
              name="rating_flags"
              value={flag}
              defaultChecked={work?.ratingFlags.includes(flag)}
            />
            {ratingFlagLabel(flag)}
          </label>
        ))}
      </FormFieldset>
      <FormField label="タグ(読点・カンマ・改行で区切る)" htmlFor="tags" state={state} name="tags">
        <TextInput id="tags" name="tags" defaultValue={work?.tags.join("、")} />
      </FormField>
      <div className={styles.footer}>
        <Button variant="primary" disabled={pending}>
          {props.submitLabel}
        </Button>
        {state.status === "error" && state.message && <FormMessage role="alert">{state.message}</FormMessage>}
        {state.status === "ok" && <FormMessage role="status">保存しました</FormMessage>}
      </div>
    </Form>
  );
}
