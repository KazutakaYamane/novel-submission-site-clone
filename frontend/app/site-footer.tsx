import { REPOSITORY_URL, SITE_NAME } from "@/lib/site";
import styles from "./site-footer.module.css";

export function SiteFooter() {
  return (
    <footer className={styles.footer}>
      <div className={styles.inner}>
        <div className={styles.about}>
          <p>
            このサイトは小説投稿サイト「カクヨム」の非公式のクローンで、カクヨム・株式会社KADOKAWA・株式会社はてなとは関係ありません。
          </p>
          <p>
            個人が学習とポートフォリオのために作ったものです。ソースコードは <a href={REPOSITORY_URL}>GitHub</a>{" "}
            で公開しています。
          </p>
        </div>
        <p className={styles.copyright}>© {SITE_NAME}</p>
      </div>
    </footer>
  );
}
