import Link from "next/link";
import { notFound } from "next/navigation";
import { SiteLogo } from "@/components/site-logo";
import { getPublishedEpisode } from "@/lib/api/episodes";
import { SITE_NAME } from "@/lib/site";
import styles from "./episode-bar.module.css";

// 本家の話ページの帯。読むことに絞った帯なので、サイト共通のヘッダーと違いログイン状態を出さない。
// 作品名と話の題名を使うので、本文と同じキャッシュ関数から読む(Laravel へのリクエストは 1 回で済む)
export async function EpisodeBar({ params }: { params: Promise<{ workId: string; episodeId: string }> }) {
  const { workId, episodeId } = await params;
  const episode = await getPublishedEpisode(workId, episodeId);
  if (!episode) notFound();
  const { work } = episode;
  const workPath = `/works/${work.id}`;
  return (
    <header className={styles.bar}>
      <Link href={workPath} className={styles.iconLink} aria-label="作品ページに戻る">
        <svg className={styles.icon} viewBox="0 0 20 20" aria-hidden="true">
          <path d="M3 3l14 14M17 3 3 17" stroke="currentColor" strokeWidth="1.6" />
        </svg>
      </Link>
      <div className={styles.titles}>
        <p className={styles.workTitle}>{work.title}</p>
        <p className={styles.episodeTitle}>{episode.title}</p>
      </div>
      <Link href="/" className={styles.logoLink} aria-label={SITE_NAME}>
        <SiteLogo className={styles.logo} />
      </Link>
      <Link href={workPath} className={`${styles.iconLink} ${styles.toc}`} aria-label="目次">
        <span className={styles.tocFrame}>
          <svg className={styles.icon} viewBox="0 0 20 20" aria-hidden="true">
            <path d="M2 4h2M2 10h2M2 16h2M7 4h11M7 10h11M7 16h11" stroke="currentColor" strokeWidth="1.6" />
          </svg>
        </span>
      </Link>
    </header>
  );
}
