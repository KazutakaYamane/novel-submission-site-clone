import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { getMyEpisode } from "@/lib/api/my-episodes";
import { EpisodeEditor } from "./episode-editor";

type Props = PageProps<"/my/works/[workId]/episodes/[episodeId]">;

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
  const episode = await getMyEpisode(workId, episodeId);
  if (!episode) notFound();
  return (
    <>
      <p>
        <Link href={`/my/works/${workId}`}>作品の編集へ戻る</Link>
      </p>
      <EpisodeEditor workId={workId} episode={episode} />
    </>
  );
}
