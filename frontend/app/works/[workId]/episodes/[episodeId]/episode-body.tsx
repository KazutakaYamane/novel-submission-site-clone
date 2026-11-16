import Link from "next/link";
import { notFound } from "next/navigation";
import { getPublishedEpisode } from "@/lib/api/episodes";
import styles from "./episode-body.module.css";
import { EpisodeText } from "./episode-text";

export async function EpisodeBody({ params }: { params: Promise<{ workId: string; episodeId: string }> }) {
  const { workId, episodeId } = await params;
  const episode = await getPublishedEpisode(workId, episodeId);
  if (!episode) notFound();
  const { work, previous, next } = episode;
  return (
    <>
      <article className={styles.column}>
        <header className={styles.heading}>
          <p className={styles.workTitle}>
            <Link href={`/works/${work.id}`}>{work.title}</Link>
          </p>
          <h1 className={styles.episodeTitle}>{episode.title}</h1>
        </header>
        <div className={styles.text} data-testid="episode-text">
          <EpisodeText body={episode.body} />
        </div>
      </article>
      {(previous || next) && (
        <nav className={styles.nav} aria-label="前後の話">
          {next && (
            <Link href={`/works/${work.id}/episodes/${next.id}`} rel="next" className={styles.next}>
              <span className={styles.nextLabel}>次の話:</span>{" "}
              <span className={styles.nextTitle}>{next.title}</span>
              <svg className={styles.nextArrow} viewBox="0 0 40 40" aria-hidden="true">
                <circle cx="20" cy="20" r="18.5" fill="none" stroke="currentColor" strokeWidth="1.6" />
                <path d="M20 10v19M12 21l8 8 8-8" fill="none" stroke="currentColor" strokeWidth="1.6" />
              </svg>
            </Link>
          )}
          {previous && (
            <Link href={`/works/${work.id}/episodes/${previous.id}`} rel="prev" className={styles.previous}>
              前の話: {previous.title}
            </Link>
          )}
        </nav>
      )}
    </>
  );
}
