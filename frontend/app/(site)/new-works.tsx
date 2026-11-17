import Link from "next/link";
import { connection } from "next/server";
import { getRecentlyUpdatedWorks, type WorkSummary } from "@/lib/api/works";
import { formatDate } from "@/lib/format-date";
import { genreLabel, statusLabel } from "@/lib/work-attributes";
import { MetaSeparator } from "@/components/meta-separator";
import styles from "./new-works.module.css";

export async function NewWorks() {
  // / は build 時に事前描画されるので、ここで止めて Laravel をリクエスト時に呼ぶ。結果は getRecentlyUpdatedWorks がキャッシュする
  await connection();
  const works = await getRecentlyUpdatedWorks();
  if (works.length === 0) return <p className={styles.empty}>まだ公開された作品はありません</p>;
  return (
    <ul role="list" className={styles.list}>
      {works.map((work) => (
        <WorkItem key={work.id} work={work} />
      ))}
    </ul>
  );
}

function WorkItem({ work }: { work: WorkSummary }) {
  return (
    <li className={styles.item}>
      {work.catchphrase && <p className={styles.catchphrase}>{work.catchphrase}</p>}
      <h3 className={styles.title}>
        <Link href={`/works/${work.id}`}>{work.title}</Link>
        <span className={styles.author}>／{work.authorName}</span>
      </h3>
      <p className={styles.meta}>
        <span className={styles.metaItem}>{genreLabel(work.genre)}</span>
        <MetaSeparator />
        <span className={styles.metaItem}>
          {statusLabel(work.status)} {work.episodeCount}話
        </span>
        {work.lastEpisodePublishedAt && (
          <>
            <MetaSeparator />
            <span className={styles.metaItem}>
              <time dateTime={work.lastEpisodePublishedAt}>{formatDate(work.lastEpisodePublishedAt)}</time> 更新
            </span>
          </>
        )}
      </p>
    </li>
  );
}
