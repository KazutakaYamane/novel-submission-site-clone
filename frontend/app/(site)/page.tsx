import { Suspense } from "react";
import { SectionErrorBoundary } from "@/components/section-error-boundary";
import { SiteHeader } from "@/components/site-header";
import { NewWorks } from "./new-works";
import styles from "./page.module.css";
import { SectionHeading } from "./section-heading";
import { TopHero } from "./top-hero";

// ALB のヘルスチェック(/)は 200 のみ見る。新着を Suspense と SectionErrorBoundary の中に置き、
// 事前描画した外枠を先に返すので、Laravel が落ちていても 200 になる
export default function Home() {
  return (
    <>
      <SiteHeader />
      <main className={styles.main}>
        <TopHero />
        <section className={styles.newWorks} aria-labelledby="new-works-title">
          <SectionHeading id="new-works-title" title="新着の更新作品" description="最近エピソードが公開された作品" />
          <SectionErrorBoundary message="新着の作品を表示できませんでした">
            <Suspense fallback={<p className={styles.loading}>読み込み中…</p>}>
              <NewWorks />
            </Suspense>
          </SectionErrorBoundary>
        </section>
      </main>
    </>
  );
}
