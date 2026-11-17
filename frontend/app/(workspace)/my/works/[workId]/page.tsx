import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { StatusBadge } from "@/components/status-badge";
import { WorkForm } from "@/components/work-form";
import { WorkspaceBox } from "@/components/workspace-box";
import { WorkspaceBreadcrumb } from "@/components/workspace-breadcrumb";
import { WorkspaceHeader } from "@/components/workspace-header";
import { listMyEpisodes } from "@/lib/api/my-episodes";
import { getMyWork } from "@/lib/api/my-works";
import { episodeStatusLabel } from "@/lib/episode-status";
import { updateWorkAction } from "./actions";
import { NewEpisodeForm } from "./new-episode-form";
import styles from "./page.module.css";

type Props = PageProps<"/my/works/[workId]">;

export default function EditWorkPage({ params }: Props) {
  return (
    <>
      <WorkspaceHeader current="section" />
      <main className={styles.main}>
        <Suspense fallback={<p>読み込み中…</p>}>
          <EditWork params={params} />
        </Suspense>
      </main>
    </>
  );
}

async function EditWork({ params }: Pick<Props, "params">) {
  const { workId } = await params;
  const [work, episodes] = await Promise.all([getMyWork(workId), listMyEpisodes(workId)]);
  // 他人の作品も、存在しない作品と同じく 404 にする
  if (!work || !episodes) notFound();
  return (
    <>
      <WorkspaceBreadcrumb current={work.title} />
      <WorkspaceBox id="work-settings" title="小説設定">
        <WorkForm action={updateWorkAction} submitLabel="作品を保存" work={work} />
      </WorkspaceBox>
      <section aria-labelledby="episodes-heading" className={styles.episodes}>
        <div className={styles.episodesHeader}>
          <h2 id="episodes-heading" className={styles.episodesHeading}>
            章とエピソード
          </h2>
          <NewEpisodeForm workId={work.id} />
        </div>
        <ol role="list" className={styles.list}>
          {episodes.map((episode) => (
            <li key={episode.id} data-testid="episode" className={styles.row}>
              <StatusBadge tone={episode.status === "published" ? "published" : "draft"}>
                {episodeStatusLabel(episode.status)}
              </StatusBadge>
              <span className={styles.title}>{episode.title}</span>
              <Link
                href={`/my/works/${work.id}/episodes/${episode.id}`}
                aria-label={`${episode.title}を編集`}
                className={styles.edit}
              >
                編集
              </Link>
            </li>
          ))}
        </ol>
      </section>
    </>
  );
}
