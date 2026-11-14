import { Suspense } from "react";
import { SectionErrorBoundary } from "@/components/section-error-boundary";
import { SITE_NAME } from "@/lib/site";
import { HeroDemo } from "./hero-demo";
import styles from "./top-hero.module.css";

export function TopHero() {
  return (
    <section className={styles.hero} aria-labelledby="top-hero-title">
      <h1 id="top-hero-title" className={styles.title}>
        {SITE_NAME}
      </h1>
      <p className={styles.description}>登録せずにデモのユーザーで、小説を書いて公開するところから読むところまで試せます。</p>
      <div className={styles.demo}>
        <SectionErrorBoundary message="デモのボタンを表示できませんでした">
          {/* ゲストにはボタンが出るので、出る前後で下の行がずれないよう、ボタンと同じ高さを空けておく */}
          <Suspense fallback={<div className={styles.demoPlaceholder} />}>
            <HeroDemo />
          </Suspense>
        </SectionErrorBoundary>
      </div>
      <p className={styles.reset}>データは毎日 4:00 にリセットされます</p>
    </section>
  );
}
