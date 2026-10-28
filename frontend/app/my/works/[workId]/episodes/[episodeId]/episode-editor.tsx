"use client";
import Link from "next/link";
import { useActionState, useState } from "react";
import { FieldErrors } from "@/components/field-errors";
import { Form } from "@/components/form";
import type { ActionState } from "@/lib/action-state";
import type { MyEpisode } from "@/lib/api/my-episodes";
import { episodeStatusLabel } from "@/lib/episode-status";
import { saveEpisodeAction, unpublishEpisodeAction } from "./actions";

export function EpisodeEditor({ workId, episode }: { workId: string; episode: MyEpisode }) {
  const [state, action, pending] = useActionState(saveEpisodeAction, { status: "idle" });
  const [unpublishState, unpublish, unpublishing] = useActionState(unpublishEpisodeAction, { status: "idle" });
  // 2 つの action の状態はそれぞれ残り続けるので、直前の操作の結果だけを出す。入力を始めたら消し、保存済みと誤解させない
  const [lastOperation, setLastOperation] = useState<Operation | null>(null);
  const published = episode.status === "published";
  const busy = pending || unpublishing;
  const save = (form: FormData) => {
    setLastOperation(form.get("intent") === "publish" ? "publish" : "save");
    action(form);
  };
  const unpublishEpisode = (form: FormData) => {
    setLastOperation("unpublish");
    unpublish(form);
  };
  return (
    <>
      <p>
        状態: <span data-testid="episode-status">{episodeStatusLabel(episode.status)}</span>
        {published && episode.publicPath && (
          <>
            {" "}
            <Link href={episode.publicPath}>公開ページを見る</Link>
          </>
        )}
      </p>
      <Form action={save} onInput={() => setLastOperation(null)} noValidate>
        <input type="hidden" name="workId" value={workId} />
        <input type="hidden" name="episodeId" value={episode.id} />
        <p>
          <label htmlFor="title">タイトル</label>
          <input id="title" name="title" defaultValue={episode.title} />
        </p>
        <FieldErrors state={state} name="title" />
        <p>
          <label htmlFor="body">本文</label>
          <textarea id="body" name="body" rows={20} cols={80} defaultValue={episode.body} />
        </p>
        <FieldErrors state={state} name="body" />
        <FieldErrors state={state} name="status" />
        {/* 押したボタンの name・value が FormData に入り、action が保存だけか公開までかを分ける */}
        <button name="intent" value="save" disabled={busy}>
          {published ? "保存" : "下書き保存"}
        </button>
        {!published && (
          <button name="intent" value="publish" disabled={busy}>
            公開
          </button>
        )}
      </Form>
      {published && (
        <Form action={unpublishEpisode}>
          <input type="hidden" name="workId" value={workId} />
          <input type="hidden" name="episodeId" value={episode.id} />
          <FieldErrors state={unpublishState} name="status" />
          <button disabled={busy}>非公開に戻す</button>
        </Form>
      )}
      {/* 実行中は前回の結果がまだ残っているので出さない */}
      {lastOperation && !busy && (
        <Message operation={lastOperation} state={lastOperation === "unpublish" ? unpublishState : state} />
      )}
    </>
  );
}

type Operation = "save" | "publish" | "unpublish";

const DONE_MESSAGES: Record<Operation, string> = {
  save: "保存しました",
  publish: "公開しました",
  unpublish: "非公開に戻しました",
};

function Message({ operation, state }: { operation: Operation; state: ActionState }) {
  if (state.status === "ok") return <p role="status">{DONE_MESSAGES[operation]}</p>;
  if (state.status === "error" && state.message) return <p role="alert">{state.message}</p>;
  return null;
}
