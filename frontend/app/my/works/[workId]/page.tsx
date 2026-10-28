import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { WorkForm } from "@/components/work-form";
import { listMyEpisodes } from "@/lib/api/my-episodes";
import { getMyWork } from "@/lib/api/my-works";
import { episodeStatusLabel } from "@/lib/episode-status";
import { updateWorkAction } from "./actions";
import { NewEpisodeForm } from "./new-episode-form";

type Props = PageProps<"/my/works/[workId]">;

export default function EditWorkPage({ params }: Props) {
  return (
    <main>
      <Suspense fallback={<p>読み込み中…</p>}>
        <EditWork params={params} />
      </Suspense>
    </main>
  );
}

async function EditWork({ params }: Pick<Props, "params">) {
  const { workId } = await params;
  const [work, episodes] = await Promise.all([getMyWork(workId), listMyEpisodes(workId)]);
  // 他人の作品も、存在しない作品と同じく 404 にする
  if (!work || !episodes) notFound();
  return (
    <>
      <h1>{work.title}</h1>
      <p>
        <Link href="/my/works">作品管理へ戻る</Link>
      </p>
      <section>
        <h2>作品の設定</h2>
        <WorkForm action={updateWorkAction} submitLabel="作品を保存" work={work} />
      </section>
      <section>
        <h2>話</h2>
        <ol>
          {episodes.map((episode) => (
            <li key={episode.id} data-testid="episode">
              <Link href={`/my/works/${work.id}/episodes/${episode.id}`}>{episode.title}</Link>{" "}
              {episodeStatusLabel(episode.status)}
            </li>
          ))}
        </ol>
        <NewEpisodeForm workId={work.id} />
      </section>
    </>
  );
}
