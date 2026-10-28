import Link from "next/link";
import { notFound } from "next/navigation";
import { getPublishedEpisode } from "@/lib/api/episodes";
import { EpisodeText } from "./episode-text";

export async function EpisodeBody({ params }: { params: Promise<{ workId: string; episodeId: string }> }) {
  const { workId, episodeId } = await params;
  const episode = await getPublishedEpisode(workId, episodeId);
  if (!episode) notFound();
  const { work, previous, next } = episode;
  return (
    <article>
      <p>
        <Link href={`/works/${work.id}`}>{work.title}</Link>
      </p>
      <h1>{episode.title}</h1>
      <EpisodeText body={episode.body} />
      <nav>
        {previous && (
          <Link href={`/works/${work.id}/episodes/${previous.id}`} rel="prev">
            前の話: {previous.title}
          </Link>
        )}
        {next && (
          <Link href={`/works/${work.id}/episodes/${next.id}`} rel="next">
            次の話: {next.title}
          </Link>
        )}
      </nav>
    </article>
  );
}
