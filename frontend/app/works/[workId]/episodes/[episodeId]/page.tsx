import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { getPublishedEpisode } from "@/lib/api/episodes";
import { EpisodeBody } from "./episode-body";
import { episodeMetadata } from "./metadata";

type Props = PageProps<"/works/[workId]/episodes/[episodeId]">;

// ページと同じキャッシュ関数を呼ぶので、Laravel へのリクエストは 1 回で済む
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { workId, episodeId } = await params;
  const episode = await getPublishedEpisode(workId, episodeId);
  if (!episode) notFound();
  return episodeMetadata(episode);
}

// page は境界を並べるだけ。params は Suspense の中で await する
export default function EpisodePage({ params }: Props) {
  return (
    <main>
      <Suspense fallback={<p>読み込み中…</p>}>
        <EpisodeBody params={params} />
      </Suspense>
    </main>
  );
}
