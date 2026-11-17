import Link from "next/link";
import { notFound } from "next/navigation";
import { Fragment } from "react";
import { getPublishedWork, type PublishedWork } from "@/lib/api/works";
import { genreLabel, ratingFlagLabels, statusLabel } from "@/lib/work-attributes";
import { formatDate } from "@/lib/format-date";
import { MetaSeparator } from "@/components/meta-separator";
import styles from "./work-detail.module.css";
import { VisuallyHidden } from "@/components/visually-hidden";

export async function WorkDetail({ params }: { params: Promise<{ workId: string }> }) {
  const { workId } = await params;
  const work = await getPublishedWork(workId);
  if (!work) notFound();
  const first = work.episodes[0];
  return (
    <article className={styles.work}>
      <div className={styles.info}>
        <h1 className={styles.title}>{work.title}</h1>
        <p className={styles.author}>{work.authorName}</p>
        {first && (
          <div className={styles.actions} data-testid="work-actions">
            <Link href={`/works/${work.id}/episodes/${first.id}`} className={styles.read}>
              1話目から読む
            </Link>
          </div>
        )}
        <div className={styles.overview}>
          {work.catchphrase && <p className={styles.catchphrase}>{work.catchphrase}</p>}
          <p className={styles.introduction}>{work.introduction}</p>
          {work.tags.length > 0 && <Tags tags={work.tags} />}
          <Meta work={work} />
        </div>
      </div>
      <section className={styles.toc} aria-labelledby="toc-heading">
        <h2 id="toc-heading" className={styles.tocHeading}>
          目次
        </h2>
        {work.episodes.length === 0 ? (
          <p className={styles.empty}>まだ公開された話はありません</p>
        ) : (
          <ol role="list" className={styles.episodes}>
            {work.episodes.map((episode) => (
              <li key={episode.id} className={styles.episode}>
                <Link href={`/works/${work.id}/episodes/${episode.id}`} className={styles.episodeTitle}>
                  {episode.title}
                </Link>
                {episode.publishedAt && (
                  <span className={styles.episodeDate}>
                    <time dateTime={episode.publishedAt}>{formatDate(episode.publishedAt)}</time>公開
                  </span>
                )}
              </li>
            ))}
          </ol>
        )}
      </section>
    </article>
  );
}

// 札の間の「、」は画面に出さず、読み上げとコピーでタグの区切りがわかるようにする。
// 目次の <ol> を main の中の唯一のリストに保つため、タグはリストにしない
function Tags({ tags }: { tags: string[] }) {
  return (
    <p className={styles.tags}>
      {tags.map((tag, i) => (
        <Fragment key={tag}>
          {i > 0 && <VisuallyHidden>、</VisuallyHidden>}
          <span className={styles.tag}>{tag}</span>
        </Fragment>
      ))}
    </p>
  );
}

function Meta({ work }: { work: PublishedWork }) {
  const items = [
    genreLabel(work.genre),
    `${statusLabel(work.status)} 全${work.episodes.length}話`,
    ...ratingFlagLabels(work.ratingFlags),
  ];
  return (
    <p className={styles.meta} data-testid="work-meta">
      {items.map((item, i) => (
        <Fragment key={item}>
          {i > 0 && <MetaSeparator />}
          <span className={styles.metaItem}>{item}</span>
        </Fragment>
      ))}
      {work.lastEpisodePublishedAt && (
        <>
          <MetaSeparator />
          <span className={styles.metaItem}>
            <time dateTime={work.lastEpisodePublishedAt}>{formatDate(work.lastEpisodePublishedAt)}</time> 更新
          </span>
        </>
      )}
    </p>
  );
}
