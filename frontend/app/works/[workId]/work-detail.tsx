import Link from "next/link";
import { notFound } from "next/navigation";
import { getPublishedWork } from "@/lib/api/works";
import { genreLabel, ratingFlagLabels, statusLabel } from "@/lib/work-attributes";
import { formatDate } from "./format";

export async function WorkDetail({ params }: { params: Promise<{ workId: string }> }) {
  const { workId } = await params;
  const work = await getPublishedWork(workId);
  if (!work) notFound();
  const ratings = ratingFlagLabels(work.ratingFlags);
  return (
    <article>
      <h1>{work.title}</h1>
      {work.catchphrase && <p>{work.catchphrase}</p>}
      <dl>
        <dt>作者</dt>
        <dd>{work.authorName}</dd>
        <dt>ジャンル</dt>
        <dd>{genreLabel(work.genre)}</dd>
        <dt>連載状態</dt>
        <dd>{statusLabel(work.status)}</dd>
        <dt>セルフレイティング</dt>
        <dd>{ratings.length > 0 ? ratings.join("・") : "なし"}</dd>
        <dt>タグ</dt>
        <dd>{work.tags.length > 0 ? work.tags.join("、") : "なし"}</dd>
        {work.lastEpisodePublishedAt && (
          <>
            <dt>最終更新</dt>
            <dd>
              <time dateTime={work.lastEpisodePublishedAt}>{formatDate(work.lastEpisodePublishedAt)}</time>
            </dd>
          </>
        )}
      </dl>
      <section>
        <h2>あらすじ</h2>
        {/* 作者が入れた改行をそのまま見せる */}
        <p style={{ whiteSpace: "pre-wrap" }}>{work.introduction}</p>
      </section>
      <section>
        <h2>目次</h2>
        <ol>
          {work.episodes.map((episode) => (
            <li key={episode.id}>
              <Link href={`/works/${work.id}/episodes/${episode.id}`}>{episode.title}</Link>
              {episode.publishedAt && (
                <>
                  {" "}
                  <time dateTime={episode.publishedAt}>{formatDate(episode.publishedAt)}</time>
                </>
              )}
            </li>
          ))}
        </ol>
      </section>
    </article>
  );
}
