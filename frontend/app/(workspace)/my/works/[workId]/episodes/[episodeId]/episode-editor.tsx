"use client";
import Link from "next/link";
import { useActionState, useState } from "react";
import { Button } from "@/components/button";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
import { FormMessage } from "@/components/form-message";
import { VisuallyHidden } from "@/components/visually-hidden";
import type { ActionState } from "@/lib/action-state";
import type { MyEpisode } from "@/lib/api/my-episodes";
import { episodeStatusLabel } from "@/lib/episode-status";
import { saveEpisodeAction, unpublishEpisodeAction } from "./actions";
import styles from "./episode-editor.module.css";

export function EpisodeEditor({ workId, workTitle, episode }: { workId: string; workTitle: string; episode: MyEpisode }) {
  const [state, action, pending] = useActionState(saveEpisodeAction, { status: "idle" });
  const [unpublishState, unpublish, unpublishing] = useActionState(unpublishEpisodeAction, { status: "idle" });
  // 2 つの action の状態はそれぞれ残り続けるので、直前の操作の結果だけを出す。入力を始めたら消し、保存済みと誤解させない
  const [lastOperation, setLastOperation] = useState<Operation | null>(null);
  const published = episode.status === "published";
  const busy = pending || unpublishing;
  // 本家と同じく操作のボタンを上の帯にまとめるので、3 つのボタンを 1 つのフォームに入れ、押したボタンの intent で action を選ぶ。
  // 非公開に戻す action は workId・episodeId だけを読むので、最大で数 MB になる本文は送らない
  const submit = (form: FormData) => {
    const intent = form.get("intent");
    if (intent === "unpublish") {
      setLastOperation("unpublish");
      const ids = new FormData();
      ids.set("workId", workId);
      ids.set("episodeId", episode.id);
      unpublish(ids);
      return;
    }
    setLastOperation(intent === "publish" ? "publish" : "save");
    action(form);
  };
  return (
    <Form action={submit} onInput={() => setLastOperation(null)} noValidate>
      <input type="hidden" name="workId" value={workId} />
      <input type="hidden" name="episodeId" value={episode.id} />
      <div className={styles.bar} data-testid="editor-bar">
        <Link href={`/my/works/${workId}`} aria-label="作品の管理に戻る" className={styles.close}>
          <CloseIcon />
        </Link>
        <p className={styles.barTitle}>{episode.title}を編集</p>
        {/* 題名の欄で Enter を押すと先頭のボタンで送信されるので、保存を先頭に置く */}
        <div className={styles.actions}>
          <Button variant="secondary" name="intent" value="save" disabled={busy}>
            {published ? "保存" : "下書き保存"}
          </Button>
          {published && (
            <Button variant="secondary" name="intent" value="unpublish" disabled={busy}>
              非公開に戻す
            </Button>
          )}
          {!published && (
            <Button variant="primary" name="intent" value="publish" disabled={busy}>
              公開
            </Button>
          )}
        </div>
      </div>
      <div className={styles.column}>
        <p className={styles.meta}>
          {workTitle}
          <span className={styles.metaSeparator}>
            状態: <span data-testid="episode-status">{episodeStatusLabel(episode.status)}</span>
          </span>
          {published && episode.publicPath && (
            <Link href={episode.publicPath} className={styles.publicLink}>
              公開ページを見る
            </Link>
          )}
        </p>
        <FieldErrors state={unpublishState} name="status" />
        <FieldErrors state={state} name="status" />
        {/* 実行中は前回の結果がまだ残っているので出さない */}
        {lastOperation && !busy && (
          <Message operation={lastOperation} state={lastOperation === "unpublish" ? unpublishState : state} />
        )}
        <label htmlFor="title">
          <VisuallyHidden>タイトル</VisuallyHidden>
        </label>
        <input id="title" name="title" placeholder="タイトル" defaultValue={episode.title} className={styles.title} />
        <FieldErrors state={state} name="title" />
        <label htmlFor="body">
          <VisuallyHidden>本文</VisuallyHidden>
        </label>
        <textarea id="body" name="body" placeholder="本文" rows={20} defaultValue={episode.body} className={styles.body} />
        <FieldErrors state={state} name="body" />
      </div>
    </Form>
  );
}

type Operation = "save" | "publish" | "unpublish";

const DONE_MESSAGES: Record<Operation, string> = {
  save: "保存しました",
  publish: "公開しました",
  unpublish: "非公開に戻しました",
};

function Message({ operation, state }: { operation: Operation; state: ActionState }) {
  if (state.status === "ok") return <FormMessage role="status">{DONE_MESSAGES[operation]}</FormMessage>;
  if (state.status === "error" && state.message) return <FormMessage role="alert">{state.message}</FormMessage>;
  return null;
}

function CloseIcon() {
  return (
    <svg className={styles.closeIcon} viewBox="0 0 18 18" aria-hidden="true">
      <path d="M3 3l12 12M15 3L3 15" fill="none" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  );
}
