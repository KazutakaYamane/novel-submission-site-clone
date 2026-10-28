"use client";
import { useActionState } from "react";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
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
      <p>
        <label htmlFor="title">タイトル</label>
        <input id="title" name="title" defaultValue={work?.title} />
      </p>
      <FieldErrors state={state} name="title" />
      <p>
        <label htmlFor="catchphrase">キャッチコピー</label>
        <input id="catchphrase" name="catchphrase" defaultValue={work?.catchphrase ?? ""} />
      </p>
      <FieldErrors state={state} name="catchphrase" />
      <p>
        <label htmlFor="introduction">あらすじ</label>
        <textarea id="introduction" name="introduction" rows={6} defaultValue={work?.introduction} />
      </p>
      <FieldErrors state={state} name="introduction" />
      <p>
        <label htmlFor="genre">ジャンル</label>
        <select id="genre" name="genre" defaultValue={work?.genre ?? ""}>
          {!work && <option value="">選択してください</option>}
          {GENRES.map((genre) => (
            <option key={genre} value={genre}>
              {genreLabel(genre)}
            </option>
          ))}
        </select>
      </p>
      <FieldErrors state={state} name="genre" />
      <p>
        <label htmlFor="status">連載状態</label>
        <select id="status" name="status" defaultValue={work?.status ?? "ongoing"}>
          {WORK_STATUSES.map((status) => (
            <option key={status} value={status}>
              {statusLabel(status)}
            </option>
          ))}
        </select>
      </p>
      <FieldErrors state={state} name="status" />
      <fieldset>
        <legend>セルフレイティング</legend>
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
      </fieldset>
      <FieldErrors state={state} name="rating_flags" />
      <p>
        <label htmlFor="tags">タグ(読点・カンマ・改行で区切る)</label>
        <input id="tags" name="tags" defaultValue={work?.tags.join("、")} />
      </p>
      <FieldErrors state={state} name="tags" />
      {state.status === "error" && state.message && <p role="alert">{state.message}</p>}
      {state.status === "ok" && <p role="status">保存しました</p>}
      <button disabled={pending}>{props.submitLabel}</button>
    </Form>
  );
}
