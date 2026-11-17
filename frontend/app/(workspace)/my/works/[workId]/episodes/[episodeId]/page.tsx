import { notFound } from "next/navigation";
import { Suspense } from "react";
import { getMyEpisode } from "@/lib/api/my-episodes";
import { getMyWork } from "@/lib/api/my-works";
import { EpisodeEditor } from "./episode-editor";

type Props = PageProps<"/my/works/[workId]/episodes/[episodeId]">;

// 本家と同じく、ワークスペースの帯の代わりにエディタの帯(episode-editor.tsx)だけを出す全画面のエディタ
export default function EditEpisodePage({ params }: Props) {
  return (
    <main>
      <Suspense fallback={<p>読み込み中…</p>}>
        <EditEpisode params={params} />
      </Suspense>
    </main>
  );
}

async function EditEpisode({ params }: Pick<Props, "params">) {
  const { workId, episodeId } = await params;
  const [work, episode] = await Promise.all([getMyWork(workId), getMyEpisode(workId, episodeId)]);
  if (!work || !episode) notFound();
  return <EpisodeEditor workId={workId} workTitle={work.title} episode={episode} />;
}
